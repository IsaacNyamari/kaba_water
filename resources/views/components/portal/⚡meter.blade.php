<?php

use App\Livewire\Concerns\InteractsWithClient;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.portal'), Title('My Meter')] class extends Component {
    use InteractsWithClient;

    #[Computed]
    public function client()
    {
        return $this->requireClient();
    }

    #[Computed]
    public function meters()
    {
        return $this->client->meters()->with('latestReading')->withCount('readings')->get();
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">

    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">My Meters</h1>
        <p class="text-sm text-zinc-500 mt-1">
            Details of every meter linked to your account.
        </p>
    </div>

    @forelse ($this->meters as $meter)
        <div wire:key="meter-{{ $meter->id }}"
            class="app-card overflow-hidden">

            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                <div>
                    <div class="font-mono text-sm text-zinc-700 dark:text-zinc-300">{{ $meter->serial }}</div>
                    <div class="text-xs text-zinc-500">{{ $meter->location ?? 'No location recorded' }}</div>
                </div>
                <flux:badge :color="$meter->status->color()" size="sm">
                    {{ $meter->status->label() }}
                </flux:badge>
            </div>

            <div class="grid gap-6 sm:grid-cols-3 p-5">

                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Current reading</div>
                    <div class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1 font-mono">
                        @if ($meter->latestReading)
                            {{ number_format($meter->latestReading->reading) }}
                            <span class="text-sm text-zinc-400 font-normal">m³</span>
                        @else
                            —
                        @endif
                    </div>
                    @if ($meter->latestReading)
                        <div class="text-xs text-zinc-500 mt-1">
                            Read {{ $meter->latestReading->read_at->format('d M Y') }}
                        </div>
                    @endif
                </div>

                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Last consumption</div>
                    <div class="text-2xl font-semibold text-brand-600 mt-1 font-mono">
                        @if ($meter->latestReading?->consumption)
                            +{{ number_format($meter->latestReading->consumption) }}
                            <span class="text-sm text-zinc-400 font-normal">m³</span>
                        @else
                            —
                        @endif
                    </div>
                </div>

                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500">Installed</div>
                    <div class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ $meter->installed_at?->format('d M Y') ?? '—' }}
                    </div>
                    <div class="text-xs text-zinc-500 mt-1">
                        {{ $meter->readings_count }} reading{{ $meter->readings_count === 1 ? '' : 's' }} recorded
                    </div>
                </div>
            </div>

            @if ($meter->latestReading?->photo_path)
                <div class="px-5 pb-5">
                    <div class="text-xs uppercase tracking-wide text-zinc-500 mb-2">Latest photo</div>
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($meter->latestReading->photo_path) }}"
                        alt="" class="max-w-xs rounded-lg border border-zinc-200 dark:border-zinc-700">
                </div>
            @endif
        </div>
    @empty
        <div class="app-card p-12 text-center">
            <flux:icon name="cog-6-tooth" class="w-12 h-12 mx-auto text-zinc-300" />
            <div class="mt-4 font-medium text-zinc-700 dark:text-zinc-300">No meters on your account</div>
            <p class="text-sm text-zinc-500 mt-1">
                Contact {{ $this->client->vendor->name }} to have a meter installed.
            </p>
        </div>
    @endforelse
</div>
