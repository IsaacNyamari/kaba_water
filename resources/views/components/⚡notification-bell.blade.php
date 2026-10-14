<?php

use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Notification bell for every signed-in user.
 *
 * New notifications arrive live over Reverb (Laravel Echo, private channel
 * App.Models.User.{id} — authorised in routes/channels.php). A slow poll is
 * the fallback when the Reverb server or queue worker isn't running.
 */
new class extends Component {
    /** Locked: the browser can't switch this to listen as someone else (the channel is also authorised server-side). */
    #[Locked]
    public int $userId;

    /** Only one bell per page should toast (the desktop and mobile bells are both on the page). */
    #[Locked]
    public bool $toasts = true;

    public function mount(): void
    {
        $this->userId = (int) auth()->id();
    }

    public function getListeners(): array
    {
        return [
            "echo-notification:App.Models.User.{$this->userId}" => 'received',
        ];
    }

    /** A notification was broadcast to this user: show it as a toast; the list re-renders itself. */
    public function received(array $notification = []): void
    {
        if (! $this->toasts) {
            return;
        }

        Flux::toast(
            heading: $notification['title'] ?? 'New notification',
            text: \Illuminate\Support\Str::limit((string) ($notification['body'] ?? ''), 140),
        );
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    #[Computed]
    public function latest()
    {
        return auth()->user()->notifications()->latest()->limit(8)->get();
    }

    /** Mark one as read and go to what it is about (same-site path only). */
    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');
        $path = parse_url($url, PHP_URL_PATH);

        if ($path) {
            $query = parse_url($url, PHP_URL_QUERY);
            $this->redirect($path.($query ? '?'.$query : ''), navigate: true);
        }
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }
};
?>

<div wire:poll.60s.visible>
    <flux:dropdown position="bottom" align="end">
        <button type="button" aria-label="{{ __('Notifications') }}"
            class="relative flex size-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-800/5 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
            <flux:icon.bell variant="outline" class="size-5" />
            @if ($this->unreadCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex min-w-4.5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4.5 text-white ring-2 ring-zinc-50 dark:ring-zinc-900">
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            @endif
        </button>

        <flux:menu class="w-80 max-w-[calc(100vw-2rem)] !p-0">
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-white/10">
                <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Notifications') }}</span>
                @if ($this->unreadCount > 0)
                    <button type="button" wire:click="markAllRead" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                        {{ __('Mark all read') }}
                    </button>
                @endif
            </div>

            <div class="max-h-96 overflow-y-auto">
                @forelse ($this->latest as $notification)
                    @php $data = $notification->data; @endphp
                    <button type="button" wire:key="n-{{ $notification->id }}" wire:click="open('{{ $notification->id }}')"
                        class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-brand-50/60 dark:hover:bg-brand-500/10 {{ $notification->read_at ? '' : 'bg-brand-50/40 dark:bg-brand-500/5' }}">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                            <flux:icon :name="$data['icon'] ?? 'bell'" variant="micro" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $data['title'] ?? __('Notification') }}</span>
                                @unless ($notification->read_at)
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-500"></span>
                                @endunless
                            </span>
                            @if (! empty($data['body']))
                                <span class="mt-0.5 line-clamp-2 block text-xs text-zinc-500 dark:text-zinc-400">{{ $data['body'] }}</span>
                            @endif
                            <span class="mt-1 block text-[11px] text-zinc-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                @empty
                    <div class="px-4 py-10 text-center text-sm text-zinc-500">
                        <flux:icon.bell-slash class="mx-auto mb-2 size-6 text-zinc-400" />
                        {{ __("You're all caught up.") }}
                    </div>
                @endforelse
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
