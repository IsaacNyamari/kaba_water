<?php

use App\Models\Plan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Plans')] class extends Component
{
    public function delete(int $id): void
    {
        $plan = Plan::findOrFail($id);
        $this->authorize('delete', $plan);

        $plan->delete();
        session()->flash('status', 'Plan deleted.');
    }

    public function with(): array
    {
        return [
            'plans' => Plan::withCount('vendors')->orderBy('monthly_price')->get(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ confirmDelete: null }">

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Plans</h1>
            <p class="text-sm text-zinc-500 mt-1">Subscription tiers available to vendors.</p>
        </div>
        <flux:button href="{{ route('admin.plans.create') }}" variant="primary" icon="plus">
            New plan
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Plan</flux:table.column>
            <flux:table.column align="end">Monthly</flux:table.column>
            <flux:table.column align="end">Max clients</flux:table.column>
            <flux:table.column align="end">Max meters</flux:table.column>
            <flux:table.column align="end">Vendors</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($plans as $plan)
                <flux:table.row :key="$plan->id">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $plan->name }}</div>
                        <div class="text-xs text-zinc-500 font-mono">{{ $plan->slug }}</div>
                    </flux:table.cell>

                    <flux:table.cell align="end" variant="strong" class="font-mono">
                        {{ number_format((float) $plan->monthly_price, 2) }}
                    </flux:table.cell>

                    <flux:table.cell align="end" class="font-mono">
                        {{ number_format($plan->max_clients) }}
                    </flux:table.cell>

                    <flux:table.cell align="end" class="font-mono">
                        {{ number_format($plan->max_meters) }}
                    </flux:table.cell>

                    <flux:table.cell align="end" class="font-mono">
                        {{ number_format($plan->vendors_count) }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                href="{{ route('admin.plans.edit', $plan) }}" />
                            <flux:button size="sm" variant="ghost" icon="trash"
                                class="!text-rose-600"
                                x-on:click="confirmDelete = {{ $plan->id }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-12 text-zinc-500">
                        No plans configured.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-plan" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete plan?</flux:heading>
                <flux:subheading>This cannot be undone. Plans with active vendors cannot be deleted.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete(confirmDelete)"
                    x-on:click="confirmDelete = null">Delete plan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>