<?php

use App\Support\Modules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Module portal — the vendor-side landing page when a user can open more
 * than one module (Water Billing, Project Management).
 */
new #[Layout('layouts.vendor'), Title('Choose a module')] class extends Component {
    public function mount(): void
    {
        $modules = Modules::for(auth()->user());

        // Only one module — go straight in. With none (e.g. a project-only role while
        // Project Management is switched off) the page explains why.
        if (count($modules) === 1) {
            $this->redirect(reset($modules)['url'], navigate: true);
        }
    }

    public function with(): array
    {
        return ['modules' => Modules::for(auth()->user())];
    }
};
?>

<div class="mx-auto max-w-5xl space-y-8 p-4 lg:p-10">
    <div>
        <flux:heading size="xl">Welcome back, {{ str(auth()->user()->name)->before(' ') }}</flux:heading>
        <flux:text class="mt-1">Where would you like to work today? You can switch any time from the sidebar.</flux:text>
    </div>

    @if (empty($modules))
        <div class="app-card flex items-start gap-3 p-6">
            <flux:icon.lock-closed class="size-6 shrink-0 text-zinc-400" />
            <div>
                <flux:heading size="sm">No modules available for your account</flux:heading>
                <flux:text class="mt-1 text-sm">The module your role works in is switched off. Ask your workspace owner for another role, or contact the platform administrator.</flux:text>
            </div>
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($modules as $key => $module)
            <a wire:key="module-{{ $key }}" href="{{ $module['url'] }}" wire:navigate
                class="app-card group flex flex-col gap-5 p-6 transition hover:-translate-y-0.5 hover:ring-2 hover:ring-brand-400/60 sm:p-8">
                <span class="flex size-14 items-center justify-center rounded-2xl {{ $key === 'projects' ? 'bg-amber-500' : 'bg-brand-500' }} text-white shadow-sm">
                    <flux:icon :name="$module['icon']" class="size-7" />
                </span>
                <div class="flex-1">
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $module['name'] }}</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $module['description'] }}</p>
                </div>
                <span class="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 dark:text-brand-300">
                    Open {{ $module['name'] }}
                    <flux:icon.arrow-right variant="micro" class="transition group-hover:translate-x-1" />
                </span>
            </a>
        @endforeach
    </div>
</div>
