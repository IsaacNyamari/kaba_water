<?php

use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        // If the vendor is actually active, no reason to be here.
        if (auth()->user()->vendor?->isActive()) {
            redirect()->route('vendor.dashboard');
        }
    }
};
?>

    <div class="p-4 lg:p-6 max-w-2xl mx-auto">
        <div class="rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-8 text-center space-y-4">
            <flux:icon name="exclamation-triangle" class="w-14 h-14 mx-auto text-amber-500" />

            <flux:heading size="xl">Subscription inactive</flux:heading>

            <flux:text class="text-zinc-700 dark:text-zinc-300">
                Your workspace is currently
                <strong>{{ auth()->user()->vendor?->status->label() ?? 'suspended' }}</strong>.
                Access to clients, meters, readings, and invoicing is paused until
                your subscription is renewed.
            </flux:text>

            @if (auth()->user()->vendor?->trial_ends_at)
                <flux:text class="text-sm text-zinc-500">
                    Trial ended {{ auth()->user()->vendor->trial_ends_at->diffForHumans() }}
                    ({{ auth()->user()->vendor->trial_ends_at->format('d M Y') }}).
                </flux:text>
            @endif

            <div class="pt-2">
                <flux:button variant="primary" icon="credit-card">
                    Renew subscription
                </flux:button>
            </div>

            <flux:text class="text-xs text-zinc-500 pt-4">
                Need help? Contact support at
                <strong>{{ config('mail.from.address') }}</strong>
            </flux:text>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="text-center mt-6">
            @csrf
            <flux:button type="submit" variant="ghost">Sign out</flux:button>
        </form>
    </div>