<?php

use App\Models\Tariff;
use Livewire\Component;

new class extends Component {
    public function toggleActive(int $id): void
    {
        $tariff = Tariff::findOrFail($id);
        $this->authorize('update', $tariff);

        $tariff->update(['is_active' => !$tariff->is_active]);
        session()->flash('status', $tariff->is_active ? 'Tariff activated.' : 'Tariff deactivated.');
    }

    public function delete(int $id): void
    {
        $tariff = Tariff::findOrFail($id);
        $this->authorize('delete', $tariff);

        if ($tariff->vendor->invoices()->exists() === false) {
            $tariff->delete();
            session()->flash('status', 'Tariff deleted.');
            return;
        }

        $tariff->delete();
        session()->flash('status', 'Tariff deleted.');
    }

    public function with(): array
    {
        return [
            'tariffs' => Tariff::orderByDesc('is_active')->orderBy('name')->get(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Tariffs</h1>
            <p class="text-sm text-zinc-500">
                Pricing rules used to compute invoice charges from meter consumption.
            </p>
        </div>

        <flux:button href="{{ route('vendor.tariffs.create') }}" variant="primary" icon="plus">
            New tariff
        </flux:button>
    </div>

    <flux:table class="p-2">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Rate / bands</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($tariffs as $tariff)
                <flux:table.row :key="$tariff->id">
                    <flux:table.cell variant="strong">{{ $tariff->name }}</flux:table.cell>

                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                        {{ $tariff->type === 'flat' ? 'Flat rate' : 'Tiered' }}
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs text-zinc-700 dark:text-zinc-300">
                        @if ($tariff->type === 'flat')
                            {{ number_format((float) $tariff->rate, 4) }} / m³
                        @else
                            @foreach ($tariff->tiers ?? [] as $i => $tier)
                                <div>
                                    Band {{ $i + 1 }}:
                                    @if ($tier['up_to'] ?? null)
                                        up to {{ $tier['up_to'] }} m³
                                    @else
                                        above
                                    @endif
                                    · {{ number_format((float) $tier['rate'], 4) }} / m³
                                </div>
                            @endforeach
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="py-0">
                        @if ($tariff->is_active)
                            <flux:badge color="emerald" size="sm">Active</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            <flux:button size="sm" variant="ghost" :icon="$tariff->is_active ? 'pause' : 'play'"
                                wire:click="toggleActive({{ $tariff->id }})"
                                :title="$tariff->is_active ? 'Deactivate' : 'Activate'" />

                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('vendor.tariffs.edit', $tariff) }}" />

                            <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                wire:click="delete({{ $tariff->id }})" wire:confirm="Delete this tariff?" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500 py-12">
                        <flux:icon name="currency-dollar" class="w-10 h-10 mx-auto mb-3 text-zinc-300" />
                        <div class="font-medium">No tariffs yet</div>
                        <div class="text-sm">Create one to start billing readings.</div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
