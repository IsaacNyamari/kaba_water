<?php

use App\Imports\ClientsImport;
use App\Models\Client;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.vendor', ['title' => 'Import clients'])] class extends Component {
    use WithFileUploads;

    #[Validate('required|file|mimes:xlsx,xls,csv|max:10240')]
    public $sheet;

    /** @var array<int, array{name:string,phone:string,email:string,status:string}> */
    public array $rows = [];

    public function mount(): void
    {
        // The route also admits view-only users — importing needs manage rights.
        $this->authorize('create', Client::class);

        $this->rows = [$this->blankRow()];
    }

    protected function blankRow(): array
    {
        return [
            'name'   => '',
            'phone'  => '',
            'email'  => '',
            'status' => 'active',
        ];
    }

    /* ---------- File upload ---------- */

    public function uploadSheet(): void
    {
        $this->authorize('create', Client::class);
        $this->validate();

        try {
            Excel::import(
                new ClientsImport(auth()->user()->vendor_id),
                $this->sheet->getRealPath(),
            );

            Flux::toast(heading: 'Success', text: 'Uploaded successfully', variant: 'success');
        } catch (\Throwable $th) {
            report($th);
            Flux::toast(heading: 'Error', text: 'Upload error: ' . $th->getMessage(), variant: 'warning');
        } finally {
            $this->reset('sheet');
        }
    }

    /* ---------- Manual grid ---------- */

    public function addRow(): void
    {
        $this->rows[] = $this->blankRow();
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);

        if (empty($this->rows)) {
            $this->rows = [$this->blankRow()];
        }
    }

    public function saveRows(): void
    {
        $this->authorize('create', Client::class);

        $this->validate([
            'rows'           => 'required|array|min:1',
            'rows.*.name'    => 'required|string|max:255',
            'rows.*.phone'   => 'nullable|string|max:32',
            'rows.*.email'   => 'nullable|email|max:255',
            'rows.*.status'  => 'required|in:active,inactive',
        ], [], [
            'rows.*.name'   => 'name',
            'rows.*.phone'  => 'phone',
            'rows.*.email'  => 'email',
            'rows.*.status' => 'status',
        ]);

        $vendorId = auth()->user()->vendor_id;
        $created  = 0;
        $skipped  = 0;

        DB::transaction(function () use ($vendorId, &$created, &$skipped) {
            foreach ($this->rows as $row) {
                if (blank($row['name']) && blank($row['phone'])) {
                    continue;
                }

                // Skip duplicate phone for this vendor
                if ($row['phone'] && Client::withoutGlobalScope('vendor')
                        ->where('vendor_id', $vendorId)
                        ->where('phone', $row['phone'])
                        ->exists()) {
                    $skipped++;
                    continue;
                }

                Client::create([
                    'vendor_id'  => $vendorId,
                    'account_no' => Client::nextAccountNo($vendorId),
                    'name'       => trim($row['name']),
                    'phone'      => $row['phone'] ?: null,
                    'email'      => $row['email'] ?: null,
                    'status'     => $row['status'],
                ]);

                $created++;
            }
        });

        $this->rows = [$this->blankRow()];

        Flux::toast(
            heading: 'Saved',
            text: $created . ' client(s) created'
                . ($skipped ? ", {$skipped} skipped (duplicate phone)" : ''),
            variant: $created ? 'success' : 'warning',
        );
    }
};
?>

<div class="mx-auto max-w-4xl space-y-6">

    {{-- ============ Upload card ============ --}}
    <flux:card class="space-y-6">
        <div class="flex items-start gap-4">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                <flux:icon.arrow-up-tray class="size-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Upload clients</flux:heading>
                <flux:subheading>Import clients from an Excel or CSV file.</flux:subheading>
            </div>
        </div>

        {{-- Expected column format --}}
        <div class="space-y-2">
            <div class="flex items-center gap-2">
                <flux:icon.table-cells class="size-4 text-zinc-500" />
                <flux:text size="sm" class="font-medium">Expected columns</flux:text>
            </div>

            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-xs">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                        <tr class="text-left text-zinc-600 dark:text-zinc-300">
                            <th class="px-3 py-2 font-medium">name <span class="text-red-500">*</span></th>
                            <th class="px-3 py-2 font-medium">phone</th>
                            <th class="px-3 py-2 font-medium">email</th>
                            <th class="px-3 py-2 font-medium">status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr class="text-zinc-700 dark:text-zinc-300">
                            <td class="px-3 py-2 font-medium">Antony Keen</td>
                            <td class="px-3 py-2 font-mono">0722524592</td>
                            <td class="px-3 py-2">antony@example.com</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">active</span>
                            </td>
                        </tr>
                        <tr class="text-zinc-500 dark:text-zinc-400">
                            <td class="px-3 py-2 font-medium">Jane Doe</td>
                            <td class="px-3 py-2 font-mono">0712345678</td>
                            <td class="px-3 py-2">jane@example.com</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">inactive</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <flux:text size="sm" class="text-zinc-500">
                <strong>name</strong> is required. <strong>status</strong> accepts <code>active</code> or <code>inactive</code> (defaults to <code>active</code> when blank). Account numbers are generated automatically.
            </flux:text>
        </div>

        <form wire:submit.prevent="uploadSheet" class="space-y-4">
            <flux:input
                type="file"
                wire:model="sheet"
                label="Spreadsheet"
                description="Accepted: .xlsx, .xls, .csv — max 10 MB"
                accept=".xlsx,.xls,.csv"
            />

            <flux:error name="sheet" />

            <div class="flex items-center justify-between gap-3 pt-2">
                <flux:text size="sm" class="text-zinc-500">
                    @if ($sheet)
                        Ready: {{ $sheet->getClientOriginalName() }}
                    @else
                        No file selected
                    @endif
                </flux:text>

                <flux:button
                    type="submit"
                    variant="primary"
                    icon="arrow-up-tray"
                    wire:loading.attr="disabled"
                    wire:target="sheet,uploadSheet"
                >
                    <span wire:loading.remove wire:target="uploadSheet">Upload</span>
                    <span wire:loading wire:target="uploadSheet">Uploading…</span>
                </flux:button>
            </div>
        </form>
    </flux:card>

    {{-- ============ Manual entry grid ============ --}}
    <flux:card class="space-y-4">
        <div class="flex items-start gap-4">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <flux:icon.table-cells class="size-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Add clients manually</flux:heading>
                <flux:subheading>Enter one client per row — just like a spreadsheet.</flux:subheading>
            </div>
        </div>

        <form wire:submit.prevent="saveRows" class="space-y-4">
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th class="w-8 px-2 py-2"></th>
                            <th class="px-2 py-2 font-medium">Name <span class="text-red-500">*</span></th>
                            <th class="px-2 py-2 font-medium">Phone</th>
                            <th class="px-2 py-2 font-medium">Email</th>
                            <th class="w-36 px-2 py-2 font-medium">Status</th>
                            <th class="w-10 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($rows as $index => $row)
                            <tr wire:key="row-{{ $index }}" class="align-top">
                                <td class="px-2 py-2 text-xs font-mono text-zinc-400">
                                    {{ $index + 1 }}
                                </td>

                                <td class="px-2 py-2">
                                    <input
                                        type="text"
                                        wire:model="rows.{{ $index }}.name"
                                        placeholder="Antony Keen"
                                        class="w-full rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-900"
                                    />
                                    @error("rows.$index.name")
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>

                                <td class="px-2 py-2">
                                    <input
                                        type="text"
                                        wire:model="rows.{{ $index }}.phone"
                                        placeholder="0722524592"
                                        class="w-full rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-sm font-mono shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-900"
                                    />
                                    @error("rows.$index.phone")
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>

                                <td class="px-2 py-2">
                                    <input
                                        type="email"
                                        wire:model="rows.{{ $index }}.email"
                                        placeholder="antony@example.com"
                                        class="w-full rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-900"
                                    />
                                    @error("rows.$index.email")
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </td>

                                <td class="px-2 py-2">
                                    <select
                                        wire:model="rows.{{ $index }}.status"
                                        class="w-full rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-zinc-700 dark:bg-zinc-900"
                                    >
                                        <option value="active">active</option>
                                        <option value="inactive">inactive</option>
                                    </select>
                                </td>

                                <td class="px-2 py-2 text-right">
                                    <button
                                        type="button"
                                        wire:click="removeRow({{ $index }})"
                                        class="rounded-md p-1.5 text-zinc-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        title="Remove row"
                                    >
                                        <flux:icon.trash class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between gap-3">
                <flux:button
                    type="button"
                    variant="ghost"
                    icon="plus"
                    wire:click="addRow"
                >
                    Add row
                </flux:button>

                <flux:button
                    type="submit"
                    variant="primary"
                    icon="check"
                    wire:loading.attr="disabled"
                    wire:target="saveRows"
                >
                    <span wire:loading.remove wire:target="saveRows">Save clients</span>
                    <span wire:loading wire:target="saveRows">Saving…</span>
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>