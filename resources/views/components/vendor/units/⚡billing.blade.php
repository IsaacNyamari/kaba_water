<?php

use App\Actions\BillUnit;
use App\Actions\SendInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Unit;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Bill houses')] class extends Component {
    /** Month billed, YYYY-MM. */
    #[Url(history: true)]
    public string $period = '';

    #[Url(history: true)]
    public string $property = '';

    public float $tax_rate = 0;
    public int $due_days = 7;

    /** Issue the bills and text each tenant straight away (otherwise they stay drafts). */
    public bool $send_now = true;

    /** @var list<int> */
    public array $selected = [];

    public function mount(): void
    {
        $this->authorize('create', Invoice::class);

        $this->period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period) ? $this->period : now()->format('Y-m');
        $this->selectAllReady();
    }

    public function updatedPeriod(): void
    {
        $this->selectAllReady();
    }

    public function updatedProperty(): void
    {
        $this->selectAllReady();
    }

    public function selectAllReady(): void
    {
        unset($this->rows);
        $this->selected = $this->rows->reject(fn (array $row) => $row['invoice'] !== null || ! $row['billable'])
            ->pluck('unit.id')->map(fn ($id) => (string) $id)->values()->all();
    }

    /**
     * Occupied houses and, for the chosen month, the live bill each already has.
     *
     * @return \Illuminate\Support\Collection<int, array{unit: Unit, invoice: ?Invoice, billable: bool}>
     */
    #[Computed]
    public function rows()
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->period)) {
            return collect();
        }

        $units = Unit::query()
            ->occupied()
            ->with(['property', 'client'])
            ->when($this->property !== '', fn ($q) => $q->where('property_id', $this->property))
            ->orderBy('property_id')
            ->orderBy('house_number')
            ->get();

        $invoices = Invoice::query()
            ->whereIn('unit_id', $units->pluck('id'))
            ->where('billing_period', $this->period)
            ->where('status', '!=', InvoiceStatus::Void)
            ->get()
            ->keyBy('unit_id');

        return $units->map(fn (Unit $unit) => [
            'unit' => $unit,
            'invoice' => $invoices->get($unit->id),
            'billable' => (float) $unit->monthlyCharge() > 0,
        ]);
    }

    #[Computed]
    public function properties()
    {
        return Property::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function selectedTotal(): float
    {
        $ids = array_map('intval', $this->selected);

        return (float) $this->rows->filter(fn (array $row) => in_array($row['unit']->id, $ids, true) && ! $row['invoice'])
            ->sum(fn (array $row) => (float) $row['unit']->monthlyCharge());
    }

    public function generate(BillUnit $billUnit, SendInvoice $sender): void
    {
        $this->authorize('create', Invoice::class);
        $this->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer'],
        ], ['selected.required' => 'Tick at least one house to bill.']);

        $units = Unit::whereIn('id', $this->selected)->occupied()->with('property')->get();
        $billed = $sent = 0;
        $errors = [];

        foreach ($units as $unit) {
            try {
                $invoice = $billUnit->handle($unit, $this->period, (float) $this->tax_rate, $this->due_days);
                $billed++;

                if ($this->send_now) {
                    $sender->handle($invoice);
                    $sent++;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$unit->house_number}: {$e->getMessage()}";
            }
        }

        unset($this->rows);
        $this->selectAllReady();

        $month = BillUnit::periodLabel($this->period);
        if ($billed > 0) {
            Flux::toast(
                variant: $errors ? 'warning' : 'success',
                heading: "{$billed} house(s) billed for {$month}",
                text: $this->send_now ? "{$sent} bill(s) issued and texted to tenants." : 'Saved as drafts. Send them from Invoices.',
            );
        }
        if ($errors) {
            Flux::toast(variant: 'danger', heading: count($errors).' house(s) not billed', text: implode(' · ', array_slice($errors, 0, 3)));
        }
    }
};
?>

<div class="max-w-6xl space-y-6 p-4 lg:p-6">
    <form wire:submit="generate" class="grid gap-6 lg:grid-cols-[1fr_340px]">

        <div class="card">
            <div class="card-header flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <flux:icon.document-plus class="size-4" />
                </div>
                <div>
                    <flux:heading size="sm">Bill houses</flux:heading>
                    <flux:subheading>Bill each occupied house its rent and service charge for the month.</flux:subheading>
                </div>
            </div>

            <div class="card-body space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <flux:input wire:model.live="period" type="month" label="Month" class="sm:w-48" />
                    <flux:select wire:model.live="property" label="Property" class="sm:w-56">
                        <flux:select.option value="">All properties</flux:select.option>
                        @foreach ($this->properties as $p)
                            <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                @error('selected')
                    <p class="text-xs text-rose-600">{{ $message }}</p>
                @enderror

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-8"></flux:table.column>
                        <flux:table.column>House</flux:table.column>
                        <flux:table.column>Tenant</flux:table.column>
                        <flux:table.column align="end">Rent</flux:table.column>
                        <flux:table.column align="end">Service</flux:table.column>
                        <flux:table.column align="end">Status</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->rows as $row)
                            @php $unit = $row['unit']; @endphp
                            <flux:table.row :key="$unit->id">
                                <flux:table.cell>
                                    @if (! $row['invoice'] && $row['billable'])
                                        <flux:checkbox wire:model.live="selected" value="{{ $unit->id }}" />
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="font-mono font-semibold">{{ $unit->house_number }}</div>
                                    @if ($unit->property)
                                        <div class="text-xs text-zinc-500">{{ $unit->property->name }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $unit->client->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $unit->client->phone ?: 'No phone' }}</div>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="font-mono">{{ number_format((float) $unit->rent, 2) }}</flux:table.cell>
                                <flux:table.cell align="end" class="font-mono">{{ number_format((float) $unit->service_charge, 2) }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($row['invoice'])
                                        <flux:link href="{{ route('vendor.invoices.show', $row['invoice']) }}" wire:navigate class="text-sm">
                                            {{ $row['invoice']->number }}
                                        </flux:link>
                                        <flux:badge size="sm" :color="$row['invoice']->status->color()">{{ $row['invoice']->status->label() }}</flux:badge>
                                    @elseif (! $row['billable'])
                                        <flux:badge size="sm" color="zinc">No charges set</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="amber">Not billed</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">
                                    No occupied houses. Add houses and their tenants under
                                    <flux:link href="{{ route('vendor.units.index') }}" wire:navigate>Houses</flux:link>.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>

        <div class="card h-fit lg:sticky lg:top-6">
            <div class="card-header flex items-center gap-2">
                <flux:icon.adjustments-horizontal class="size-4 text-zinc-500" />
                <flux:heading size="sm">Options</flux:heading>
            </div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model.live.debounce.300ms="tax_rate" type="number" step="0.01" min="0" max="100" label="Tax (%)" />
                    <flux:input wire:model="due_days" type="number" min="0" max="365" label="Due in (days)" />
                </div>

                <flux:checkbox wire:model="send_now" label="Issue and text each tenant now"
                    description="Unticked, the bills are saved as drafts for you to check first." />

                <div class="flex items-center justify-between rounded-lg bg-gradient-to-r from-brand-600 to-brand-500 px-3 py-3 text-white shadow-sm">
                    <span class="text-xs font-semibold uppercase tracking-wide opacity-90">{{ count($selected) }} house(s)</span>
                    <span class="font-mono text-lg font-bold">{{ number_format($this->selectedTotal * (1 + $tax_rate / 100), 2) }}</span>
                </div>

                <flux:button type="submit" variant="primary" icon="document-check" class="w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generate">Bill {{ count($selected) }} house(s)</span>
                    <span wire:loading wire:target="generate">Billing…</span>
                </flux:button>

                <flux:text class="text-xs">
                    Each house is billed at most once a month. Water from meters in a house is billed separately from its readings.
                </flux:text>
            </div>
        </div>
    </form>
</div>
