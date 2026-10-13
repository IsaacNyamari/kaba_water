<?php

use App\Livewire\Concerns\UsesAccounting;
use App\Models\FiscalPeriod;
use App\Services\Accounting\FiscalPeriodService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Fiscal Periods')] class extends Component {
    use UsesAccounting;

    public int $year;
    public string $name = '';
    public string $start_date = '';
    public string $end_date = '';

    public ?int $actingId = null;
    public string $reason = '';

    public function mount(): void
    {
        $this->bootAccounting();
        $this->year = (int) now()->year;
    }

    public function createQuarters(FiscalPeriodService $periods): void
    {
        $this->authorize('accounting.close-period');
        $this->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);

        try {
            $periods->createQuarters($this->vendorId(), $this->year);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', heading: 'Could not create', text: collect($e->errors())->flatten()->first());
            return;
        }

        Flux::toast(variant: 'success', heading: 'Periods ready', text: "Quarters for {$this->year} are set up.");
    }

    public function createCustom(FiscalPeriodService $periods): void
    {
        $this->authorize('accounting.close-period');
        $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $periods->create($this->vendorId(), $this->name, CarbonImmutable::parse($this->start_date), CarbonImmutable::parse($this->end_date));
        $this->reset(['name', 'start_date', 'end_date']);
        Flux::modal('custom-period')->close();
        Flux::toast(variant: 'success', heading: 'Period created');
    }

    public function confirm(int $id, string $modal): void
    {
        $this->actingId = FiscalPeriod::findOrFail($id)->id;
        $this->reason = '';
        $this->resetValidation();
        Flux::modal($modal)->show();
    }

    public function close(FiscalPeriodService $periods): void
    {
        $this->authorize('accounting.close-period');
        $period = FiscalPeriod::findOrFail($this->actingId);

        $periods->close($period, auth()->user(), $this->reason ?: null);

        Flux::modal('close-period')->close();
        Flux::toast(variant: 'success', heading: 'Period closed', text: "{$period->name} is locked. No entry can be dated inside it.");
    }

    public function reopen(FiscalPeriodService $periods): void
    {
        $this->authorize('accounting.reopen-period');
        $period = FiscalPeriod::findOrFail($this->actingId);

        try {
            $periods->reopen($period, auth()->user(), $this->reason);
        } catch (AuthorizationException $e) {
            Flux::toast(variant: 'danger', heading: 'Not allowed', text: $e->getMessage());
            return;
        } catch (ValidationException $e) {
            $this->addError('reason', collect($e->errors())->flatten()->first());
            return;
        }

        Flux::modal('reopen-period')->close();
        Flux::toast(variant: 'warning', heading: 'Period reopened', text: "{$period->name} accepts entries again. This was logged.");
    }

    public function with(): array
    {
        return [
            'periods' => FiscalPeriod::with(['closer', 'events.user'])->orderByDesc('start_date')->get(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4 max-w-5xl">
    <flux:card class="space-y-4">
        <x-accounting.card-header icon="calendar-days" title="Fiscal periods"
            subtitle="Close a period once its books are final — nothing can then be posted, reversed or back-dated into it." />

        @can('accounting.close-period')
            <div class="flex flex-wrap items-end gap-3">
                <flux:input type="number" wire:model="year" label="Year" class="w-28" />
                <flux:button wire:click="createQuarters" icon="squares-plus">Set up quarters</flux:button>
                <flux:modal.trigger name="custom-period">
                    <flux:button variant="ghost" icon="plus">Custom period</flux:button>
                </flux:modal.trigger>
            </div>
        @endcan
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Period</flux:table.column>
            <flux:table.column>From</flux:table.column>
            <flux:table.column>To</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>History</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($periods as $p)
                <flux:table.row :key="$p->id">
                    <flux:table.cell variant="strong">{{ $p->name }}</flux:table.cell>
                    <flux:table.cell>{{ $p->start_date->format('d M Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $p->end_date->format('d M Y') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($p->is_closed)
                            <flux:badge size="sm" color="zinc" icon="lock-closed">Closed</flux:badge>
                            <div class="text-xs text-zinc-500 mt-1">{{ $p->closed_at?->format('d M Y') }} {{ $p->closer ? 'by ' . $p->closer->name : '' }}</div>
                        @else
                            <flux:badge size="sm" color="emerald">Open</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-xs text-zinc-500">
                        @foreach ($p->events->take(3) as $ev)
                            <div wire:key="ev-{{ $ev->id }}">{{ $ev->created_at->format('d M Y') }} · {{ $ev->action }} {{ $ev->user ? 'by ' . $ev->user->name : '' }}{{ $ev->reason ? ' — ' . $ev->reason : '' }}</div>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if (! $p->is_closed)
                            @can('accounting.close-period')
                                <flux:button size="sm" variant="filled" icon="lock-closed" wire:click="confirm({{ $p->id }}, 'close-period')">Close</flux:button>
                            @endcan
                        @else
                            @can('accounting.reopen-period')
                                <flux:button size="sm" variant="ghost" icon="lock-open" wire:click="confirm({{ $p->id }}, 'reopen-period')">Reopen</flux:button>
                            @endcan
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 py-10">No periods yet — set up the quarters for this year.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="custom-period" class="w-full max-w-md">
        <form wire:submit="createCustom" class="space-y-5">
            <flux:heading size="lg">Custom period</flux:heading>
            <flux:input wire:model="name" label="Name" placeholder="e.g. FY2026" />
            <div class="grid grid-cols-2 gap-3">
                <flux:input type="date" wire:model="start_date" label="Start" />
                <flux:input type="date" wire:model="end_date" label="End" />
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="close-period" class="w-full max-w-md">
        <form wire:submit="close" class="space-y-5">
            <div>
                <flux:heading size="lg">Close this period?</flux:heading>
                <flux:subheading>Invoices, payments, expenses and manual entries dated inside it will be refused.</flux:subheading>
            </div>
            <flux:input wire:model="reason" label="Note (optional)" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Close period</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="reopen-period" class="w-full max-w-md">
        <form wire:submit="reopen" class="space-y-5">
            <div>
                <flux:heading size="lg">Reopen this period?</flux:heading>
                <flux:subheading>Reopening is recorded in the audit trail with your name and reason.</flux:subheading>
            </div>
            <flux:input wire:model="reason" label="Reason" required />
            <flux:error name="reason" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Reopen</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
