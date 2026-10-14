<?php

use App\Enums\ReadingStatus;
use App\Models\Client;
use App\Models\Meter;
use App\Models\Reading;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor'), Title('Readings')] class extends Component {
    use WithPagination;

    #[Url(as: 'meter', history: true)]
    public ?int $meterId = null;
    #[Url(as: 'client', history: true)]
    public ?int $clientId = null;
    #[Url(as: 'status', history: true)]
    public string $status = '';
    #[Url(as: 'from', history: true)]
    public ?string $from = null;
    #[Url(as: 'to', history: true)]
    public ?string $to = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Reading::class);
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['meterId', 'clientId', 'status', 'from', 'to']);
        $this->resetPage();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return Reading::pending()->count();
    }

    public function approve(int $id): void
    {
        $reading = Reading::findOrFail($id);
        $this->authorize('update', $reading);

        if (! $reading->isPending()) {
            return;
        }

        // Recomputes previous reading + consumption at approval time.
        $reading->approve();

        Flux::toast(variant: 'success', heading: 'Approved', text: 'Reading approved — ' . number_format($reading->consumption) . ' m³ consumed.');
    }

    public function reject(int $id, string $reason = ''): void
    {
        $reading = Reading::findOrFail($id);
        $this->authorize('update', $reading);

        if (! $reading->isPending()) {
            return;
        }

        $reading->reject(mb_substr(trim($reason), 0, 500));

        Flux::toast(variant: 'warning', heading: 'Rejected', text: 'Reading rejected.');
    }

    public function delete(int $id): void
    {
        $reading = Reading::findOrFail($id);
        $this->authorize('delete', $reading);

        if ($reading->invoice()->exists()) {
            Flux::toast(variant: 'danger', heading: 'Cannot delete', text: 'This reading has been invoiced. Delete the invoice first.');
            return;
        }

        $reading->delete();
        Flux::toast(variant: 'success', heading: 'Deleted', text: 'Reading deleted.');
    }

    public function with(): array
    {
        return [
            'readings' => Reading::query()
                ->with(['meter.client', 'creator'])
                ->when($this->meterId, fn ($q) => $q->where('meter_id', $this->meterId))
                ->when($this->clientId, fn ($q) => $q->whereHas('meter', fn ($q) => $q->where('client_id', $this->clientId)))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->from, fn ($q) => $q->whereDate('read_at', '>=', $this->from))
                ->when($this->to, fn ($q) => $q->whereDate('read_at', '<=', $this->to))
                ->latest('read_at')
                ->paginate(20),

            'meters'  => Meter::with('client')->orderBy('serial')->get(),
            'clients' => Client::orderBy('name')->get(['id', 'name', 'account_no']),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4" x-data="{ photo: null, reject: null, rejectReason: '' }">

    {{-- Pending banner --}}
    @if ($this->pendingCount > 0)
        <div class="rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <flux:icon name="clock" class="w-5 h-5 text-amber-500 shrink-0" />
                    <div class="text-sm text-amber-900 dark:text-amber-200">
                        <strong>{{ $this->pendingCount }}</strong>
                        reading{{ $this->pendingCount === 1 ? '' : 's' }} from clients
                        awaiting your review.
                    </div>
                </div>
                <flux:button size="sm" variant="filled" wire:click="$set('status', 'pending')">
                    Show pending
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:select wire:model.live="meterId" class="w-full sm:w-64">
                <flux:select.option value="">All meters</flux:select.option>
                @foreach ($meters as $m)
                    <flux:select.option value="{{ $m->id }}">
                        {{ $m->serial }} · {{ $m->client->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="clientId" class="w-full sm:w-56">
                <flux:select.option value="">All clients</flux:select.option>
                @foreach ($clients as $c)
                    <flux:select.option value="{{ $c->id }}">
                        {{ $c->account_no }} · {{ $c->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" class="w-full sm:w-44">
                <flux:select.option value="">All statuses</flux:select.option>
                @foreach (\App\Enums\ReadingStatus::cases() as $s)
                    <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model.live="from" class="w-full sm:w-40" />
            <flux:input type="date" wire:model.live="to" class="w-full sm:w-40" />

            @if ($meterId || $clientId || $status || $from || $to)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">Clear</flux:button>
            @endif
        </div>

        <flux:button href="{{ route('vendor.readings.create') }}" variant="primary" icon="camera">
            New reading
        </flux:button>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden app-card">
        <table class="w-full text-sm">
            <thead>
                <tr
                    class="text-left text-xs uppercase tracking-wide text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Meter</th>
                    <th class="px-5 py-3 text-right">Previous</th>
                    <th class="px-5 py-3 text-right">Reading</th>
                    <th class="px-5 py-3 text-right">Consumption</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Source</th>
                    <th class="px-5 py-3">Photo</th>
                    <th class="px-5 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($readings as $reading)
                    @php $pending = $reading->isPending(); @endphp
                    <tr wire:key="reading-{{ $reading->id }}"
                        class="border-b border-zinc-100 dark:border-zinc-800 last:border-0
                           {{ $pending ? 'bg-amber-50/40 dark:bg-amber-950/10' : '' }}">
                        <td class="px-5 py-3 whitespace-nowrap text-zinc-700 dark:text-zinc-300">
                            {{ $reading->read_at->format('d M Y') }}
                            <div class="text-xs text-zinc-500">{{ $reading->read_at->format('H:i') }}</div>
                        </td>
                        <td class="px-5 py-3">
                            <div class="font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $reading->meter->serial }}
                            </div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $reading->meter->client->name }}
                            </div>
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-zinc-500">
                            {{ number_format($reading->previous_reading ?? 0) }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ number_format($reading->reading) }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if ($reading->consumption !== null)
                                <span
                                    class="font-mono font-semibold {{ $pending ? 'text-zinc-500' : 'text-accent-600' }}">
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
                        </td>
                        <td class="px-5 py-3">
                            @if ($reading->creator?->hasRole('client'))
                                <flux:badge color="violet" size="sm" icon="user">Client</flux:badge>
                            @elseif ($reading->source === 'ocr')
                                <flux:badge color="blue" size="sm" icon="sparkles">OCR</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Manual</flux:badge>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if ($reading->photo_path)
                                <button type="button"
                                    @click="photo = '{{ \Illuminate\Support\Facades\Storage::url($reading->photo_path) }}'"
                                    class="block w-10 h-10 rounded overflow-hidden border border-zinc-200 dark:border-zinc-700 hover:ring-2 hover:ring-brand-500/40">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($reading->photo_path) }}"
                                        alt="" class="w-full h-full object-cover">
                                </button>
                            @else
                                <span class="text-zinc-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="inline-flex gap-1">
                                @if ($pending)
                                    <flux:button size="sm" variant="filled" icon="check"
                                        class="!bg-accent-600 !text-white" wire:click="approve({{ $reading->id }})"
                                        wire:confirm="Approve this reading? It will be eligible for invoicing."
                                        title="Approve" />

                                    <flux:button size="sm" variant="filled" icon="x-mark"
                                        class="!bg-rose-600 !text-white"
                                        x-on:click="reject = {{ $reading->id }}; rejectReason = ''" title="Reject" />
                                @endif

                                <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                    wire:click="delete({{ $reading->id }})"
                                    wire:confirm="Delete this reading permanently?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-12 text-center text-zinc-500">
                            <flux:icon name="chart-bar" class="w-10 h-10 mx-auto mb-3 text-zinc-300" />
                            <div class="font-medium">No readings found</div>
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

    {{-- Reject reason modal --}}
    <div x-show="reject" x-cloak @keydown.escape.window="reject = null"
        class="fixed inset-0 z-50 bg-zinc-900/40 grid place-items-center p-4" @click.self="reject = null">
        <div class="w-full max-w-md rounded-xl bg-white dark:bg-zinc-900 p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Reject reading</h3>
                <p class="text-sm text-zinc-500 mt-1">
                    Optionally tell the client why (they'll see this in their portal).
                </p>
            </div>

            <flux:textarea x-model="rejectReason" label="Reason (optional)"
                placeholder="e.g. Photo is unreadable, or the value looks too high." rows="3" />

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="reject = null">Cancel</flux:button>
                <flux:button variant="danger" x-on:click="$wire.reject(reject, rejectReason); reject = null">
                    Reject reading
                </flux:button>
            </div>
        </div>
    </div>
</div>
