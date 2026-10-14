<?php

use App\Models\Project;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Borehole technical record (stored as structured JSON on the project),
 * defects, and project documents (permits, drawings, contracts, inspection
 * reports, handover documents, completion certificates).
 */
new class extends Component {
    use WithFileUploads;

    public const FIELDS = [
        'Drilling' => [
            'survey_geology' => ['Site survey & geology', 'textarea'],
            'formation_log' => ['Drilling log & formations', 'textarea'],
            'final_depth_m' => ['Final drilled depth (m)', 'number'],
        ],
        'Construction' => [
            'casing_screen_specs' => ['Casing & screen specification', 'textarea'],
            'gravel_pack' => ['Gravel pack', 'text'],
            'development_record' => ['Development record', 'textarea'],
        ],
        'Pumping test & yield' => [
            'static_water_level_m' => ['Static water level (m)', 'number'],
            'dynamic_water_level_m' => ['Dynamic water level (m)', 'number'],
            'pumping_test_hours' => ['Pumping test duration (h)', 'number'],
            'yield_m3_per_hour' => ['Tested yield (m³/h)', 'number'],
            'recovery' => ['Recovery', 'text'],
            'pumping_test_result' => ['Pumping test results', 'textarea'],
        ],
        'Pump, quality & inspection' => [
            'pump_specs' => ['Pump specification', 'text'],
            'pump_install_depth_m' => ['Pump install depth (m)', 'number'],
            'water_quality_result' => ['Water quality lab result', 'textarea'],
            'electrical_plumbing_tests' => ['Electrical & plumbing tests', 'textarea'],
            'final_inspection_date' => ['Final inspection date', 'date'],
            'final_inspection_result' => ['Final inspection result', 'text'],
        ],
    ];

    public const DOCUMENT_TYPES = [
        'permit' => 'Permit', 'drawing' => 'Drawing', 'contract' => 'Contract', 'survey' => 'Survey report',
        'lab_result' => 'Lab result', 'inspection' => 'Inspection report', 'handover' => 'Handover document',
        'completion_certificate' => 'Completion certificate', 'other' => 'Other',
    ];

    public Project $project;

    public array $technical = [];

    public string $defect = '';

    public string $documentType = 'permit';
    public $document = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->technical = collect(self::FIELDS)->collapse()->keys()
            ->mapWithKeys(fn ($k) => [$k => (string) ($project->technical[$k] ?? '')])->all();
    }

    public function save(): void
    {
        $this->authorize('report', $this->project);

        $rules = [];
        foreach (collect(self::FIELDS)->collapse() as $key => [$label, $type]) {
            $rules["technical.{$key}"] = match ($type) {
                'number' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'date' => ['nullable', 'date'],
                'textarea' => ['nullable', 'string', 'max:5000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }
        $this->validate($rules);

        $technical = array_merge($this->project->technical ?? [], collect($this->technical)->map(fn ($v) => $v === '' ? null : $v)->all());
        $this->project->update(['technical' => $technical] + (filled($technical['final_depth_m'] ?? null) ? ['actual_depth_m' => $technical['final_depth_m']] : []));
        $this->project->log('technical', 'Technical record updated', ['fields' => array_keys(array_filter($this->technical, 'filled'))]);

        Flux::toast(variant: 'success', text: 'Technical record saved.');
        $this->dispatch('project-changed');
    }

    public function addDefect(): void
    {
        $this->authorize('report', $this->project);
        $this->validate(['defect' => ['required', 'string', 'max:500']]);

        $technical = $this->project->technical ?? [];
        $technical['defects'][] = ['id' => (string) Str::uuid(), 'text' => $this->defect, 'resolved' => false, 'logged_at' => now()->toDateTimeString(), 'by' => auth()->user()->name];
        $this->project->update(['technical' => $technical]);
        $this->project->log('technical', "Defect logged: {$this->defect}");

        $this->defect = '';
        Flux::toast(text: 'Defect logged.');
    }

    public function resolveDefect(string $id): void
    {
        $this->authorize('report', $this->project);
        $technical = $this->project->technical ?? [];
        foreach ($technical['defects'] ?? [] as $i => $d) {
            if ($d['id'] === $id) {
                $technical['defects'][$i]['resolved'] = true;
                $technical['defects'][$i]['resolved_at'] = now()->toDateTimeString();
                $this->project->log('technical', "Defect resolved: {$d['text']}");
            }
        }
        $this->project->update(['technical' => $technical]);
        Flux::toast(variant: 'success', text: 'Defect marked resolved.');
    }

    public function upload(): void
    {
        $this->authorize('report', $this->project);
        $this->validate([
            'documentType' => ['required', Rule::in(array_keys(self::DOCUMENT_TYPES))],
            'document' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,dwg'],
        ]);

        $attachments = $this->project->attachments ?? [];
        $attachments[] = [
            'id' => (string) Str::uuid(),
            'type' => $this->documentType,
            'name' => $this->document->getClientOriginalName(),
            'path' => $this->document->store("projects/{$this->project->id}/documents", 'public'),
            'uploaded_by' => auth()->user()->name,
            'uploaded_at' => now()->toDateTimeString(),
        ];
        $this->project->update(['attachments' => $attachments]);
        $this->project->log('document', self::DOCUMENT_TYPES[$this->documentType].' uploaded: '.$this->document->getClientOriginalName());

        $this->reset('document');
        Flux::toast(variant: 'success', text: 'Document uploaded.');
        $this->dispatch('project-changed');
    }
};
?>

<div class="grid gap-6 lg:grid-cols-3">
    <form wire:submit="save" class="space-y-6 lg:col-span-2">
        @foreach ($this::FIELDS as $section => $fields)
            <flux:card class="space-y-4" wire:key="tech-{{ $loop->index }}">
                <flux:heading size="sm">{{ $section }}</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($fields as $key => [$label, $type])
                        @if ($type === 'textarea')
                            <flux:textarea wire:model="technical.{{ $key }}" :label="$label" rows="2" class="sm:col-span-2" :disabled="! auth()->user()->can('report', $project)" />
                        @else
                            <flux:input :type="$type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text')" step="0.01" wire:model="technical.{{ $key }}" :label="$label" :disabled="! auth()->user()->can('report', $project)" />
                        @endif
                    @endforeach
                </div>
            </flux:card>
        @endforeach
        @can('report', $project)
            <div class="flex justify-end"><flux:button type="submit" variant="primary">Save technical record</flux:button></div>
        @endcan
    </form>

    <div class="space-y-6">
        <flux:card class="space-y-3">
            <flux:heading size="sm">Planned vs actual</flux:heading>
            <div class="flex justify-between text-sm"><span class="text-zinc-500">Planned depth</span><span class="font-mono">{{ $project->planned_depth_m ?? '—' }} m</span></div>
            <div class="flex justify-between text-sm"><span class="text-zinc-500">Actual depth</span><span class="font-mono">{{ $project->actual_depth_m ?? '—' }} m</span></div>
            <flux:text class="text-xs">Technical completion is not closure — closure also needs costs, stock and paperwork settled.</flux:text>
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="sm">Defects & corrective actions</flux:heading>
            @forelse ($project->technical['defects'] ?? [] as $d)
                <div wire:key="defect-{{ $d['id'] }}" class="flex items-start justify-between gap-2 rounded-lg p-2 text-sm {{ $d['resolved'] ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-rose-50 dark:bg-rose-500/10' }}">
                    <div>
                        <p class="{{ $d['resolved'] ? 'line-through text-zinc-500' : '' }}">{{ $d['text'] }}</p>
                        <p class="text-xs text-zinc-500">{{ $d['by'] ?? '' }} · {{ $d['logged_at'] ?? '' }}</p>
                    </div>
                    @if (! $d['resolved'])
                        @can('report', $project)
                            <flux:button size="xs" wire:click="resolveDefect('{{ $d['id'] }}')">Resolve</flux:button>
                        @endcan
                    @endif
                </div>
            @empty
                <flux:text class="text-sm">No defects logged.</flux:text>
            @endforelse
            @can('report', $project)
                <form wire:submit="addDefect" class="flex gap-2">
                    <flux:input wire:model="defect" placeholder="Describe a defect" class="flex-1" />
                    <flux:button type="submit" icon="plus" />
                </form>
            @endcan
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="sm">Documents</flux:heading>
            @forelse ($project->attachments ?? [] as $doc)
                <a wire:key="doc-{{ $doc['id'] }}" href="{{ Storage::disk('public')->url($doc['path']) }}" target="_blank" class="flex items-center gap-2 rounded-lg p-2 text-sm hover:bg-zinc-50 dark:hover:bg-white/5">
                    <flux:icon.paper-clip variant="micro" class="text-zinc-400" />
                    <span class="min-w-0 flex-1 truncate">{{ $doc['name'] }}</span>
                    <flux:badge size="sm" color="zinc">{{ $this::DOCUMENT_TYPES[$doc['type']] ?? $doc['type'] }}</flux:badge>
                </a>
            @empty
                <flux:text class="text-sm">No documents yet.</flux:text>
            @endforelse
            @can('report', $project)
                <form wire:submit="upload" class="space-y-3 border-t border-zinc-100 pt-3 dark:border-white/10">
                    <flux:select wire:model="documentType" label="Type">
                        @foreach ($this::DOCUMENT_TYPES as $k => $l) <flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option> @endforeach
                    </flux:select>
                    <flux:input type="file" wire:model="document" />
                    <flux:button type="submit" size="sm" icon="arrow-up-tray" wire:loading.attr="disabled">Upload</flux:button>
                </form>
            @endcan
        </flux:card>
    </div>
</div>
