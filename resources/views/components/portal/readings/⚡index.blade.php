<?php

use App\Livewire\Concerns\RefreshesOnActivity;

use App\Enums\ReadingStatus;
use App\Livewire\Concerns\InteractsWithClient;
use App\Models\Meter;
use App\Models\Reading;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Client portal — the signed-in client's OWN readings only.
 * (Approval/rejection lives in the vendor workspace: vendor/readings/index.)
 */
new #[Layout('layouts.portal'), Title('Readings')] class extends Component {
    use InteractsWithClient, WithPagination, RefreshesOnActivity;

    #[Url(as: 'meter', history: true)]
    public ?int $meterId = null;
    #[Url(as: 'status', history: true)]
    public string $status = '';
    #[Url(as: 'from', history: true)]
    public ?string $from = null;
    #[Url(as: 'to', history: true)]
    public ?string $to = null;

    public function updating(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['meterId', 'status', 'from', 'to']);
        $this->resetPage();
    }

    #[Computed]
    public function client()
    {
        return $this->requireClient();
    }

    /** IDs of the meters that belong to this client. */
    #[Computed]
    public function meterIds(): array
    {
        return Meter::where('client_id', $this->client->id)->pluck('id')->all();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return Reading::whereIn('meter_id', $this->meterIds)->pending()->count();
    }

    public function with(): array
    {
        return [
            'readings' => Reading::query()
                ->with('meter')
                ->whereIn('meter_id', $this->meterIds)
                ->when($this->meterId, fn ($q) => $q->where('meter_id', $this->meterId))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->from, fn ($q) => $q->whereDate('read_at', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->whereDate('read_at', '<=', $this->to))
                ->latest('read_at')
                ->paginate(20),

            'meters' => Meter::where('client_id', $this->client->id)->orderBy('serial')->get(['id', 'serial', 'location']),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ photo: null }">

    @if ($this->pendingCount > 0)
        <div class="rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-4">
            <div class="flex items-center gap-3">
                <flux:icon name="clock" class="w-5 h-5 text-amber-500 shrink-0" />
                <div class="text-sm text-amber-900 dark:text-amber-200">
                    <strong>{{ $this->pendingCount }}</strong>
                    reading{{ $this->pendingCount === 1 ? '' : 's' }} waiting for review by your water provider.
                </div>
            </div>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            @if ($meters->count() > 1)
                <flux:select wire:model.live="meterId" class="w-full sm:w-64">
                    <flux:select.option value="">All my meters</flux:select.option>
                    @foreach ($meters as $m)
                        <flux:select.option value="{{ $m->id }}">
                            {{ $m->serial }}{{ $m->location ? ' · ' . $m->location : '' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="status" class="w-full sm:w-44">
                <flux:select.option value="">All statuses</flux:select.option>
                @foreach (ReadingStatus::cases() as $s)
                    <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model.live="from" class="w-full sm:w-40" />
            <flux:input type="date" wire:model.live="to" class="w-full sm:w-40" />

            @if ($meterId || $status || $from || $to)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">Clear</flux:button>
            @endif
        </div>

        @can('client.readings.create')
            <flux:button href="{{ route('portal.readings.create') }}" variant="primary" icon="camera" wire:navigate>
                Submit reading
            </flux:button>
        @endcan
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto app-card">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Meter</th>
                    <th class="px-5 py-3 text-right">Previous</th>
                    <th class="px-5 py-3 text-right">Reading</th>
                    <th class="px-5 py-3 text-right">Consumption</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Photo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readings as $reading)
                    @php $pending = $reading->isPending(); @endphp
                    <tr wire:key="reading-{{ $reading->id }}"
                        class="border-b border-zinc-100 dark:border-zinc-800 last:border-0 {{ $pending ? 'bg-amber-50/40 dark:bg-amber-950/10' : '' }}">
                        <td class="px-5 py-3 whitespace-nowrap text-zinc-700 dark:text-zinc-300">
                            {{ $reading->read_at->format('d M Y') }}
                            <div class="text-xs text-zinc-500">{{ $reading->read_at->format('H:i') }}</div>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                            {{ $reading->meter->serial }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-zinc-500">
                            {{ number_format($reading->previous_reading ?? 0) }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ number_format($reading->reading) }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if ($reading->consumption !== null)
                                <span class="font-mono font-semibold {{ $pending ? 'text-zinc-500' : 'text-accent-600' }}">
                                    +{{ number_format($reading->consumption) }}
                                </span>
                                <span class="text-xs text-zinc-500">m³</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <flux:badge :color="$reading->status->color()" size="sm">
                                {{ $reading->status->label() }}
                            </flux:badge>
                            @if ($reading->status === ReadingStatus::Rejected && $reading->rejection_reason)
                                <div class="text-xs text-rose-600 mt-1 max-w-xs">{{ $reading->rejection_reason }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if ($reading->photo_path)
                                <button type="button"
                                    @click="photo = '{{ \Illuminate\Support\Facades\Storage::url($reading->photo_path) }}'"
                                    class="block w-10 h-10 rounded overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($reading->photo_path) }}" alt=""
                                        class="w-full h-full object-cover">
                                </button>
                            @else
                                <span class="text-zinc-400 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-zinc-500">
                            <flux:icon name="chart-bar" class="w-10 h-10 mx-auto mb-3 text-zinc-300" />
                            <div class="font-medium">No readings yet</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($readings->hasPages())
            <div class="px-5 py-3 border-t border-zinc-200 dark:border-zinc-700">
                {{ $readings->links() }}
            </div>
        @endif
    </div>

    {{-- Photo preview --}}
    <div x-show="photo" x-cloak @keydown.escape.window="photo = null"
        class="fixed inset-0 z-50 bg-zinc-900/80 grid place-items-center p-4" @click.self="photo = null">
        <img :src="photo" alt="" class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl">
        <button @click="photo = null" class="absolute top-4 right-4 text-white/80 hover:text-white">
            <flux:icon name="x-mark" class="w-8 h-8" />
        </button>
    </div>
</div>
