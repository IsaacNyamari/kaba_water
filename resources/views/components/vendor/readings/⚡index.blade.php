<?php

use App\Models\Client;
use App\Models\Meter;
use App\Models\Reading;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.vendor', ['title' => 'Readings'])] class extends Component {
    use WithPagination;

    #[Url(as: 'meter', history: true)]
    public ?int $meterId = null;
    #[Url(as: 'client', history: true)]
    public ?int $clientId = null;
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
        $this->reset(['meterId', 'clientId', 'from', 'to']);
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $reading = Reading::findOrFail($id);
        $this->authorize('delete', $reading);

        $reading->delete();
        session()->flash('status', 'Reading deleted.');
    }

    public function with(): array
    {
        return [
            'readings' => Reading::query()
                ->with(['meter.client'])
                ->when($this->meterId, fn($q) => $q->where('meter_id', $this->meterId))
                ->when($this->clientId, fn($q) => $q->whereHas('meter', fn($q) => $q->where('client_id', $this->clientId)))
                ->when($this->from, fn($q) => $q->whereDate('read_at', '>=', $this->from))
                ->when($this->to, fn($q) => $q->whereDate('read_at', '<=', $this->to))
                ->latest('read_at')
                ->paginate(20),

            'meters' => Meter::with('client')->orderBy('serial')->get(),
            'clients' => Client::orderBy('name')->get(['id', 'name', 'account_no']),
        ];
    }
    public function approve(Reading $reading)
    {
        $reading->status = 'approved';

        $reading->save();
        Flux::toast(heading: 'Approved', text: 'Reading approved', variant: 'success');
    }
};
?>

<div class="space-y-4" x-data="{ photo: null }">

    {{-- Toolbar --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex flex-1 flex-wrap gap-3">
            <flux:select wire:model.live="meterId" class="w-full sm:w-72">
                <flux:select.option value="">All meters</flux:select.option>
                @foreach ($meters as $m)
                    <flux:select.option value="{{ $m->id }}">
                        {{ $m->serial }} · {{ $m->client->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="clientId" class="w-full sm:w-64">
                <flux:select.option value="">All clients</flux:select.option>
                @foreach ($clients as $c)
                    <flux:select.option value="{{ $c->id }}">
                        {{ $c->account_no }} · {{ $c->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model.live="from" class="w-full sm:w-40" />
            <flux:input type="date" wire:model.live="to" class="w-full sm:w-40" />

            @if ($meterId || $clientId || $from || $to)
                <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">
                    Clear
                </flux:button>
            @endif
        </div>

        <flux:button href="{{ route('vendor.readings.create') }}" variant="primary" icon="camera">
            New reading
        </flux:button>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <flux:table :paginate="$readings">
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Meter</flux:table.column>
                <flux:table.column align="end">Previous</flux:table.column>
                <flux:table.column align="end">Reading</flux:table.column>
                <flux:table.column align="end">Consumption</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Source</flux:table.column>
                <flux:table.column>Photo</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($readings as $reading)
                    @php $pending = $reading->isPending(); @endphp
                    <flux:table.row :key="$reading->id"
                        class="{{ $pending ? 'bg-amber-50/40 dark:bg-amber-950/10' : '' }}">
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $reading->read_at->format('d M Y') }}
                            <div class="text-xs text-zinc-500">{{ $reading->read_at->format('H:i') }}</div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $reading->meter->serial }}
                            </div>
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $reading->meter->client->name }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell align="end" class="font-mono text-zinc-500">
                            {{ number_format($reading->previous_reading ?? 0) }}
                        </flux:table.cell>

                        <flux:table.cell align="end" variant="strong" class="font-mono">
                            {{ number_format($reading->reading) }}
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @if ($reading->consumption !== null)
                                <span
                                    class="font-mono font-semibold {{ $pending ? 'text-zinc-500' : 'text-accent-600' }}">
                                    +{{ number_format($reading->consumption) }}
                                </span>
                                <span class="text-xs text-zinc-500">m³</span>
                            @else
                                —
                            @endif
                        </flux:table.cell>

                        <flux:table.cell class="py-0">
                            <flux:badge :color="$reading->status->color()" size="sm">
                                {{ $reading->status->label() }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell class="py-0">
                            @if ($reading->creator?->hasRole('client'))
                                <flux:badge color="violet" size="sm" icon="user">Client</flux:badge>
                            @elseif ($reading->source === 'ocr')
                                <flux:badge color="blue" size="sm" icon="sparkles">OCR</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Manual</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>
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
                        </flux:table.cell>

                        <flux:table.cell align="end">
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
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9" class="text-center text-zinc-500 py-10">
                            No readings found.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($readings->hasPages())
            <div class="px-5 py-3 border-t border-slate-200">{{ $readings->links() }}</div>
        @endif
    </div>

    {{-- Photo preview modal --}}
    <div x-show="photo" x-cloak @keydown.escape.window="photo = null"
        class="fixed inset-0 z-50 bg-slate-900/80 grid place-items-center p-4" @click.self="photo = null">
        <img :src="photo" alt="" class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl">
        <button @click="photo = null" class="absolute top-4 right-4 text-white/80 hover:text-white">
            <flux:icon name="x-mark" class="w-8 h-8" />
        </button>
    </div>
</div>
