<?php

use App\Actions\GenerateInvoiceFromReading;
use App\Models\Reading;
use App\Models\Tariff;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Component;

new #[Layout('layouts.vendor', ['title' => 'New invoice'])] class extends Component {
    public ?int $reading_id = null;
    public ?int $tariff_id = null;
    public float $tax_rate = 0;
    public int $due_days = 14;

    public function mount(): void
    {
        $this->authorize('create', \App\Models\Invoice::class);

        $this->tariff_id = Tariff::where('is_active', true)->value('id');

        if ($readingId = request()->integer('reading')) {
            $this->reading_id = $readingId;
        }
    }

    #[Computed]
    public function readings()
    {
        return Reading::query()
            ->approved()
            ->whereDoesntHave('invoice')
            ->where('consumption', '>', 0)
            ->with(['meter.client'])
            ->latest('read_at')
            ->limit(200)
            ->get();
    }

    #[Computed]
    public function tariffs()
    {
        return Tariff::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function preview(): ?array
    {
        if (!$this->reading_id || !$this->tariff_id) {
            return null;
        }

        $reading = $this->readings->firstWhere('id', $this->reading_id);
        $tariff = $this->tariffs->firstWhere('id', $this->tariff_id);

        if (!$reading || !$tariff) {
            return null;
        }

        $consumption = (float) $reading->consumption;
        $subtotal = $tariff->chargeFor($consumption);
        $tax = round($subtotal * ($this->tax_rate / 100), 2);

        return [
            'reading' => $reading,
            'tariff' => $tariff,
            'consumption' => $consumption,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => round($subtotal + $tax, 2),
            'issued_at' => GenerateInvoiceFromReading::issueDateFor($reading),
            'due_at' => GenerateInvoiceFromReading::issueDateFor($reading)->addDays($this->due_days),
        ];
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;

        return [
            'reading_id' => ['required', "exists:readings,id,vendor_id,{$vendorId}"],
            'tariff_id' => ['required', "exists:tariffs,id,vendor_id,{$vendorId}"],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'due_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function generate(GenerateInvoiceFromReading $action): void
    {
        $this->authorize('create', \App\Models\Invoice::class);
        $this->validate();

        $reading = Reading::findOrFail($this->reading_id);
        $tariff = Tariff::findOrFail($this->tariff_id);

        try {
            $invoice = $action->handle($reading, $tariff, (float) $this->tax_rate, $this->due_days);

            \Flux::toast(variant: 'success', heading: 'Invoice created', text: "{$invoice->number} · " . number_format($invoice->total, 2));

            $this->redirectRoute('vendor.invoices.show', $invoice, navigate: true);
        } catch (\Throwable $e) {
            \Flux::toast(variant: 'danger', heading: 'Could not create invoice', text: $e->getMessage());
        }
    }
};
?>

<div class="max-w-6xl space-y-6">

    <form wire:submit="generate" class="grid gap-6 lg:grid-cols-[1fr_400px]">

        {{-- ─── Left: form ─── --}}
        <div class="space-y-6">

            <div class="card">
                <div class="card-header flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                        <flux:icon.document-text class="size-4" />
                    </div>
                    <div>
                        <flux:heading size="sm">Bill a reading</flux:heading>
                        <flux:subheading>Select a reading, apply a tariff, and preview the invoice.</flux:subheading>
                    </div>
                </div>

                <div class="card-body space-y-6">

                    {{-- Reading --}}
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500">
                            <span class="inline-flex size-4 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">1</span>
                            Reading
                        </label>

                        <flux:select wire:model.live="reading_id" searchable
                            placeholder="— Pick an un-invoiced reading —">
                            <flux:select.option> choose</flux:select.option>
                            @forelse ($this->readings as $r)
                                <flux:select.option value="{{ $r->id }}">
                                    {{ $r->read_at->format('d M Y') }} ·
                                    {{ $r->meter->client->name }} ·
                                    {{ $r->meter->serial }} ·
                                    +{{ number_format($r->consumption) }} m³
                                </flux:select.option>
                            @empty
                                <flux:select.option value="" disabled>
                                    No un-invoiced readings available
                                </flux:select.option>
                            @endforelse
                        </flux:select>
                        @error('reading_id')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tariff --}}
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500">
                            <span class="inline-flex size-4 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">2</span>
                            Tariff
                        </label>

                        <flux:select wire:model.live="tariff_id">
                            @foreach ($this->tariffs as $t)
                                <flux:select.option value="{{ $t->id }}">
                                    {{ $t->name }} —
                                    {{ $t->type === 'flat'
                                        ? number_format((float) $t->rate, 2) . ' / m³'
                                        : 'tiered (' . count($t->tiers ?? []) . ' bands)' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('tariff_id')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Tax + due --}}
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-zinc-500">
                            <span class="inline-flex size-4 items-center justify-center rounded-full bg-zinc-900 text-[10px] font-bold text-white dark:bg-zinc-100 dark:text-zinc-900">3</span>
                            Adjustments
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model.live.debounce.300ms="tax_rate" type="number" step="0.01"
                                min="0" max="100" label="Tax rate (%)" description="Use 0 for no tax." />
                            <flux:input wire:model.live.debounce.300ms="due_days" type="number" min="1"
                                max="365" label="Due in (days)" />
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ─── Right: preview ─── --}}
        <div class="space-y-6">
            <div class="card sticky top-6 overflow-hidden">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:icon.eye class="size-4 text-zinc-500" />
                        <flux:heading size="sm">Preview</flux:heading>
                    </div>
                    @if ($this->preview)
                        <flux:badge color="brand" size="sm">Live</flux:badge>
                    @endif
                </div>

                @if ($this->preview)
                    @php $p = $this->preview; @endphp
                    <div class="card-body space-y-5 text-sm">

                        {{-- Client card --}}
                        <div class="flex items-start gap-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/40">
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white text-zinc-500 shadow-sm dark:bg-zinc-900">
                                <flux:icon.user class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-900 dark:text-slate-100 truncate">
                                    {{ $p['reading']->meter->client->name }}
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    {{ $p['reading']->meter->client->account_no }}
                                </div>
                            </div>
                        </div>

                        {{-- Meter --}}
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-xs uppercase tracking-wide text-slate-500">
                                <flux:icon.cpu-chip class="size-3.5" />
                                Meter
                            </span>
                            <span class="font-mono text-xs">{{ $p['reading']->meter->serial }}</span>
                        </div>

                        {{-- Dates --}}
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-xs uppercase tracking-wide text-slate-500">
                                <flux:icon.calendar class="size-3.5" />
                                Reading date
                            </span>
                            <span class="text-xs">{{ $p['reading']->read_at->format('d M Y') }}</span>
                        </div>

                        <flux:separator />

                        {{-- Consumption breakdown --}}
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Previous</span>
                                <span class="font-mono">{{ number_format($p['reading']->previous_reading ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Current</span>
                                <span class="font-mono">{{ number_format($p['reading']->reading) }}</span>
                            </div>

                            <div class="flex items-center justify-between rounded-lg bg-brand-50 px-3 py-2 dark:bg-brand-500/10">
                                <span class="text-xs font-semibold uppercase tracking-wide text-brand-700 dark:text-brand-400">
                                    Consumption
                                </span>
                                <span class="font-mono font-bold text-brand-700 dark:text-brand-400">
                                    {{ number_format($p['consumption']) }} m³
                                </span>
                            </div>
                        </div>

                        <flux:separator />

                        {{-- Money --}}
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Subtotal</span>
                                <span class="font-mono">{{ number_format($p['subtotal'], 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Tax ({{ $tax_rate }}%)</span>
                                <span class="font-mono">{{ number_format($p['tax'], 2) }}</span>
                            </div>
                        </div>

                        {{-- Total --}}
                        <div class="flex items-center justify-between rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-3 py-3 text-white shadow-sm">
                            <span class="text-xs font-semibold uppercase tracking-wide opacity-90">Total</span>
                            <span class="font-mono text-lg font-bold">{{ number_format($p['total'], 2) }}</span>
                        </div>

                        {{-- Invoice date (a past month's reading is billed in that month) --}}
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span class="flex items-center gap-1.5">
                                <flux:icon.calendar class="size-3.5" />
                                Invoice date
                            </span>
                            <span>{{ $p['issued_at']->format('d M Y') }}</span>
                        </div>

                        {{-- Due --}}
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span class="flex items-center gap-1.5">
                                <flux:icon.clock class="size-3.5" />
                                Due date
                            </span>
                            <span>{{ $p['due_at']->format('d M Y') }}</span>
                        </div>

                        {{-- Submit --}}
                        <flux:button type="submit" variant="primary" icon="document-check" class="w-full"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="generate">Generate invoice</span>
                            <span wire:loading wire:target="generate">Generating…</span>
                        </flux:button>
                    </div>
                @else
                    <div class="card-body text-center text-sm text-slate-500 py-16">
                        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon name="document-text" class="w-7 h-7 text-slate-400" />
                        </div>
                        <div class="mt-4 font-medium text-slate-600 dark:text-slate-300">
                            No preview yet
                        </div>
                        <div class="mt-1 text-xs text-slate-400">
                            Pick a reading and tariff to see the invoice summary here.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>