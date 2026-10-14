<?php

use App\Enums\VendorStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Vendor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Admin Dashboard')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        return [
            'vendors_total' => Vendor::count(),
            'vendors_active' => Vendor::where('status', VendorStatus::Active->value)->count(),
            'vendors_trial' => Vendor::where('status', VendorStatus::Trial->value)->count(),
            'vendors_susp' => Vendor::where('status', VendorStatus::Suspended->value)->count(),
            'clients' => Client::withoutGlobalScope('vendor')->count(),
            'mrr' => Vendor::where('status', VendorStatus::Active->value)->join('plans', 'plans.id', '=', 'vendors.plan_id')->sum('plans.monthly_price'),
            'invoices_issued' => Invoice::withoutGlobalScope('vendor')->count(),
            'payments_total' => Payment::withoutGlobalScope('vendor')->sum('amount'),
        ];
    }

    #[Computed]
    public function recentVendors()
    {
        return Vendor::with('plan')->latest()->take(8)->get();
    }

    #[Computed]
    public function planBreakdown()
    {
        return Plan::withCount('vendors')->orderBy('monthly_price')->get();
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">

    {{-- KPI strip --}}
    <div class="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-card tone-brand p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Total vendors</span>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                {{ number_format($this->stats['vendors_total']) }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                {{ $this->stats['vendors_active'] }} active ·
                {{ $this->stats['vendors_trial'] }} trial ·
                {{ $this->stats['vendors_susp'] }} suspended
            </div>
        </div>

        <div class="stat-card tone-emerald p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Monthly recurring revenue</span>
            <div class="text-2xl font-semibold text-accent-600 mt-1">
                {{ number_format($this->stats['mrr'], 2) }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">From active subscriptions</div>
        </div>

        <div class="stat-card tone-brand p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">End clients</span>
            <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1">
                {{ number_format($this->stats['clients']) }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">Across all vendors</div>
        </div>

        <div class="stat-card tone-brand p-5">
            <span class="text-xs uppercase tracking-wide text-zinc-500">Invoices issued</span>
            <div class="text-2xl font-semibold text-brand-600 mt-1">
                {{ number_format($this->stats['invoices_issued']) }}
            </div>
            <div class="text-xs text-zinc-500 mt-1">
                Payments total: {{ number_format($this->stats['payments_total'], 2) }}
            </div>
        </div>
    </div>

    {{-- Plan breakdown --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div
            class="lg:col-span-2 app-card overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-zinc-100">Recent Vendors</h2>
                <a href="{{ route('admin.vendors.index') }}" class="text-sm text-brand-600 hover:underline">View all</a>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Plan</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column align="end">Joined</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->recentVendors as $v)
                        <flux:table.row :key="$v->id">
                            <flux:table.cell>
                                <a href="{{ route('admin.vendors.edit', $v) }}"
                                    class="text-brand-600 hover:underline font-medium">
                                    {{ $v->name }}
                                </a>
                                <div class="text-xs text-zinc-500">{{ $v->email }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $v->plan?->name ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell class="py-0">
                                <flux:badge :color="$v->status->color()" size="sm">
                                    {{ $v->status->label() }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="text-zinc-500 whitespace-nowrap">
                                {{ $v->created_at->format('d M Y') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                                No vendors yet.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="app-card p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Plans</h2>
            <div class="space-y-4">
                @foreach ($this->planBreakdown as $plan)
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $plan->name }}</span>
                            <span class="text-sm font-mono text-zinc-600 dark:text-zinc-400">
                                {{ number_format((float) $plan->monthly_price, 2) }}
                            </span>
                        </div>
                        <div class="text-xs text-zinc-500 mt-1">
                            {{ $plan->vendors_count }} vendor{{ $plan->vendors_count === 1 ? '' : 's' }}
                        </div>
                        <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 mt-2 overflow-hidden">
                            <div class="h-full bg-brand-600"
                                style="width: {{ $this->stats['vendors_total'] > 0 ? ($plan->vendors_count / $this->stats['vendors_total']) * 100 : 0 }}%">
                            </div>
                        </div>
                    </div>
                @endforeach

                <flux:button href="{{ route('admin.plans.index') }}" variant="ghost" class="w-full">
                    Manage plans
                </flux:button>
            </div>
        </div>
    </div>
</div>
