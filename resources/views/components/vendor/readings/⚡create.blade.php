<?php

use App\Models\Meter;
use App\Models\Reading;
use App\Services\GeminiOcrService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.vendor'), Title('New reading')] class extends Component {
    use WithFileUploads;

    public string $meter_id = '';
    public string $previous_reading = '';
    public string $reading = '';
    public string $read_at = '';
    public string $source = 'manual';

    public $photo = null;
    public ?string $extractedReading = null;
    public ?string $ocrError = null;
    public bool $scanning = false;

    public function mount(): void
    {
        $this->authorize('create', Reading::class);
        $this->read_at = now()->format('Y-m-d\TH:i');

        if ($meterId = request()->integer('meter')) {
            $this->meter_id = (string) $meterId;
            $this->syncMeterState();
        }
    }

    /** Re-check the meter whenever the selection changes. */
    public function updatedMeterId(): void
    {
        $this->syncMeterState();
    }

    /**
     * Reset reading fields and pre-fill the previous reading when the
     * selected meter already has history.
     */
    protected function syncMeterState(): void
    {
        $this->reading = '';
        $this->previous_reading = '';
        $this->extractedReading = null;
        $this->ocrError = null;

        if ($this->source === 'ocr') {
            $this->source = 'manual';
        }

        if (!$this->meter_id) {
            return;
        }

        // Same baseline the model uses to compute consumption: last APPROVED reading.
        $last = Reading::lastApprovedValueFor((int) $this->meter_id);

        if ($last !== null) {
            $this->previous_reading = (string) $last;
        }
    }

    #[Computed]
    public function selectedMeter(): ?Meter
    {
        if (!$this->meter_id) {
            return null;
        }

        return Meter::with('client')->find($this->meter_id);
    }

    /** True when the selected meter has never had a reading recorded. */
    #[Computed]
    public function isNewMeter(): bool
    {
        if (!$this->meter_id) {
            return false;
        }

        return ! Reading::where('meter_id', $this->meter_id)->approved()->exists();
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;

        $rules = [
            'meter_id' => ['required', "exists:meters,id,vendor_id,{$vendorId}"],
            'reading' => ['required', 'integer', 'min:0'],
            'read_at' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:4096', 'mimes:jpeg,png,jpg,webp'],
        ];

        // Only require the previous reading when this meter is brand new.
        if ($this->isNewMeter) {
            $rules['previous_reading'] = ['required', 'integer', 'min:0', 'lte:reading'];
        } elseif ($this->meter_id && ($last = Reading::lastApprovedValueFor((int) $this->meter_id)) !== null) {
            // Meters only count up — a lower value is a misread.
            $rules['reading'][] = 'gte:' . $last;
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'previous_reading.required' => 'Enter the initial reading for this new meter.',
            'previous_reading.lte' => 'Previous reading cannot be greater than the current reading.',
            'reading.gte' => 'The reading cannot be lower than the last approved reading (:value).',
        ];
    }

    /** Auto-run OCR the moment a photo is dropped in. */
    public function updatedPhoto(): void
    {
        $this->validateOnly('photo', ['photo' => 'image|max:4096|mimes:jpeg,png,jpg,webp']);
        $this->scan();
    }

    public function scan(): void
    {
        if (!$this->photo) {
            $this->ocrError = 'Upload a meter photo first.';
            return;
        }

        $this->scanning = true;
        $this->ocrError = null;

        try {
            $digits = app(GeminiOcrService::class)->extract($this->photo->getRealPath(), $this->photo->getMimeType());

            if ($digits === '') {
                $this->ocrError = 'No digits detected. Try a clearer, closer photo or enter the value manually.';
                return;
            }

            $this->extractedReading = $digits;
            $this->reading = $digits;
            $this->source = 'ocr';

            \Flux::toast(variant: 'success', heading: 'OCR complete', text: "Extracted: {$digits}");
        } catch (\Throwable $e) {
            $this->ocrError = 'OCR failed: ' . $e->getMessage();
            \Log::error('Reading OCR failed', ['error' => $e->getMessage()]);
        } finally {
            $this->scanning = false;
        }
    }

    public function clearPhoto(): void
    {
        $this->photo = null;
        $this->extractedReading = null;
        $this->ocrError = null;

        if ($this->source === 'ocr') {
            $this->source = 'manual';
            $this->reading = '';
        }
    }

    public function save(): void
    {
        $this->authorize('create', Reading::class);
        $this->validate();

        $photoPath = null;
        if ($this->photo) {
            $photoPath = $this->photo->store('meter-readings', 'public');
        }

        $payload = [
            'meter_id' => $this->meter_id,
            'reading' => $this->reading,
            'source' => $this->source,
            'photo_path' => $photoPath,
            'read_at' => $this->read_at,
            'created_by' => auth()->id(),
        ];

        // Seed previous_reading explicitly for a brand-new meter so the
        // very first consumption = current - previous is correct.
        // (If your Reading model's booted() hook overrides this, keep the
        // explicit value only when you're sure the hook respects it.)
        if ($this->isNewMeter) {
            $payload['previous_reading'] = (int) $this->previous_reading;
        }

        // The model computes previous_reading (when not seeded) and consumption.
        $reading = Reading::create($payload);

        session()->flash('status', 'Reading recorded: ' . number_format($reading->reading) . ' m³ ('
            . number_format((int) $reading->consumption) . ' m³ consumed since last reading).');

        $this->redirectRoute('vendor.readings.index', navigate: true);
    }

    #[Computed]
    public function meters()
    {
        return Meter::with('client')->where('status', 'active')->orderBy('serial')->get();
    }
};
?>

<div class="p-4 lg:p-6 max-w-3xl space-y-6">

    <form wire:submit="save" class="space-y-6">

        {{-- ─── Meter + date ─── --}}
        <flux:card class="space-y-5">
            <flux:heading size="sm">Meter & date</flux:heading>

            <flux:select wire:model.live="meter_id" label="Meter" searchable placeholder="Choose Meter">
                @forelse ($this->meters as $m)
                    <flux:select.option value="{{ $m->id }}">
                        {{ $m->serial }} · {{ $m->client->name }} ({{ $m->client->account_no }})
                    </flux:select.option>
                @empty
                    <flux:select.option value="" disabled>
                        No active meters available
                    </flux:select.option>
                @endforelse
            </flux:select>

            <flux:input type="datetime-local" wire:model="read_at" label="Reading date & time" />

            {{-- New meter badge --}}
            @if ($this->isNewMeter)
                <div
                    class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <flux:icon.sparkles class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="text-sm">
                        <div class="font-medium text-amber-800 dark:text-amber-300">
                            New meter detected
                        </div>
                        <div class="text-amber-700 dark:text-amber-400/90 mt-0.5">
                            This meter has no reading history. Enter the initial reading below — it will be stored as
                            the baseline.
                        </div>
                    </div>
                </div>
            @endif
        </flux:card>

        {{-- ─── Photo + OCR ─── --}}
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Meter photo (optional)</flux:heading>
                @if ($photo)
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearPhoto"
                        class="!text-rose-600">
                        Remove
                    </flux:button>
                @endif
            </div>

            @if (!$photo)
                <label
                    class="block border-2 border-dashed border-zinc-300 dark:border-zinc-600
                              hover:border-brand-400 rounded-xl p-8 text-center
                              cursor-pointer transition
                              bg-zinc-50/50 dark:bg-zinc-800/30 hover:bg-brand-50/30 dark:hover:bg-brand-950/20">
                    <input type="file" wire:model="photo" accept="image/*" class="hidden">
                    <flux:icon name="camera" class="w-10 h-10 mx-auto text-zinc-400" />
                    <div class="mt-3 font-medium text-zinc-700 dark:text-zinc-300">
                        Click to upload a meter photo
                    </div>
                    <div class="text-xs text-zinc-500 mt-1">
                        JPG, PNG, or WEBP · max 4 MB · the AI will try to read the digits
                    </div>
                    <div wire:loading wire:target="photo" class="mt-3 text-xs text-brand-600 font-medium">
                        Uploading…
                    </div>
                </label>
            @else
                <div class="grid gap-4 sm:grid-cols-[220px_1fr]">
                    <div class="rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-50">
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="w-full h-full object-cover">
                    </div>

                    <div class="space-y-3">
                        @if ($scanning)
                            <div class="flex items-center gap-2 text-sm text-brand-600">
                                <flux:icon.loading class="w-4 h-4" />
                                Reading digits from the image…
                            </div>
                        @elseif ($extractedReading)
                            <div class="rounded-lg bg-accent-50 border border-accent-200 p-3">
                                <div class="text-xs uppercase tracking-wide text-accent-700 font-semibold">
                                    Extracted reading
                                </div>
                                <div class="font-mono text-2xl font-bold text-accent-700 mt-1">
                                    {{ $extractedReading }}
                                </div>
                                <div class="text-xs text-accent-600 mt-1">
                                    Review it — edit the field below if the AI misread it.
                                </div>
                            </div>
                        @endif

                        @if ($ocrError)
                            <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700">
                                {{ $ocrError }}
                            </div>
                        @endif

                        <flux:button size="sm" variant="filled" icon="sparkles" wire:click="scan"
                            wire:loading.attr="disabled" wire:target="scan">
                            <span wire:loading.remove wire:target="scan">Re-scan</span>
                            <span wire:loading wire:target="scan">Scanning…</span>
                        </flux:button>
                    </div>
                </div>
            @endif
        </flux:card>

        {{-- ─── Reading values ─── --}}
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Reading values</flux:heading>
                @if ($source === 'ocr')
                    <flux:badge color="violet" size="sm" icon="sparkles">From OCR</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">Manual</flux:badge>
                @endif
            </div>

            @if (!$meter_id)
                <div
                    class="rounded-lg border border-dashed border-zinc-300 bg-zinc-50 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/40">
                    Select a meter above to enter its reading.
                </div>
            @else
                {{-- Previous reading input: only for a brand-new meter --}}
                @if ($this->isNewMeter)
                    <flux:input wire:model.live="previous_reading" label="Previous reading (initial baseline)"
                        description="Starting value for this meter. Usually 0 if it was just installed."
                        placeholder="e.g. 0" class="font-mono" inputmode="numeric" pattern="[0-9]*" />
                @else
                    <div
                        class="rounded-lg bg-zinc-50 border border-zinc-200 px-4 py-3 dark:bg-zinc-800/40 dark:border-zinc-700">
                        <div class="text-xs uppercase tracking-wide text-zinc-500 font-semibold">
                            Previous reading
                        </div>
                        <div class="font-mono text-lg font-semibold text-zinc-800 dark:text-zinc-200 mt-0.5">
                            {{ $previous_reading !== '' ? $previous_reading : '—' }} m³
                        </div>
                    </div>
                @endif

                <flux:input wire:model.live="reading" label="Current reading"
                    description="Whole cubic meters only — ignore red digits and fraction dials."
                    placeholder="e.g. 012806" class="font-mono text-lg" inputmode="numeric" pattern="[0-9]*" />

                {{-- Live consumption preview --}}
                @if ($previous_reading !== '' && $reading !== '' && is_numeric($previous_reading) && is_numeric($reading))
                    @php
                        $consumed = (int) $reading - (int) $previous_reading;
                    @endphp
                    <div
                        class="rounded-lg border px-4 py-3 text-sm
                                {{ $consumed < 0
                                    ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' }}">
                        @if ($consumed < 0)
                            Current reading is lower than the previous reading — please double-check.
                        @else
                            Consumption since last reading:
                            <span class="font-semibold">{{ number_format($consumed) }} m³</span>
                        @endif
                    </div>
                @endif

                @if ($source === 'ocr')
                    <p class="text-xs text-zinc-500">
                        The current reading was auto-filled by OCR. Adjust it if needed before saving.
                    </p>
                @endif
            @endif
        </flux:card>

        {{-- ─── Actions ─── --}}
        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.readings.index') }}">
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save"
                :disabled="!$meter_id">
                <span wire:loading.remove wire:target="save">Save reading</span>
                <span wire:loading wire:target="save">Saving…</span>
            </flux:button>
        </div>
    </form>
</div>
