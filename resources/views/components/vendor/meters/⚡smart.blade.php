<?php

use App\Models\Integration;
use App\Models\Meter;
use App\Models\MeterTelemetry;
use App\Services\Metering\SmartMeterReadings;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Smart meters: which meters report on their own, when they last did, the
 * latest value, battery and alerts (leak, tamper, reverse flow…), and the
 * monthly billing readings taken from their data.
 */
new #[Layout('layouts.vendor', ['title' => 'Smart meters'])] class extends Component {
    use WithPagination;

    #[Url(as: 'q', history: true)] public string $search = '';
    #[Url(history: true)] public string $filter = '';

    public ?int $historyFor = null;

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'filter'], true)) {
            $this->resetPage();
        }
    }

    public function showHistory(int $meterId): void
    {
        $this->historyFor = Meter::whereNotNull('device_id')->findOrFail($meterId)->id;
        Flux::modal('history')->show();
    }

    public function createReadings(SmartMeterReadings $readings): void
    {
        abort_unless(auth()->user()->can('vendor.readings.manage'), 403);

        if (! Integration::active(auth()->user()->vendor_id, Integration::SMART_METERS)) {
            Flux::toast(variant: 'warning', heading: 'Smart meters are off', text: 'Switch on smart meter data in Settings first.');

            return;
        }

        $count = $readings->createFor(auth()->user()->vendor_id, now()->startOfMonth());

        Flux::toast(
            variant: $count ? 'success' : 'warning',
            heading: $count ? "{$count} reading(s) created" : 'No new readings',
            text: $count ? 'Find them under Readings, ready to bill.' : 'Every smart meter already has a reading this month, or none has reported yet.',
        );
    }

    public function with(): array
    {
        $base = Meter::query()->whereNotNull('device_id');

        return [
            'meters' => (clone $base)
                ->with('client:id,name,account_no')
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('serial', 'like', "%{$this->search}%")
                    ->orWhere('device_id', 'like', "%{$this->search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))))
                ->when($this->filter === 'offline', fn ($q) => $q->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDay())))
                ->when($this->filter === 'alerts', fn ($q) => $q->whereNotNull('alerts'))
                ->when($this->filter === 'prepaid', fn ($q) => $q->where('billing_mode', 'prepaid'))
                ->orderByRaw('alerts IS NULL')
                ->orderBy('serial')
                ->paginate(30),
            'counts' => [
                'total' => (clone $base)->count(),
                'online' => (clone $base)->where('last_seen_at', '>=', now()->subDay())->count(),
                'alerts' => (clone $base)->whereNotNull('alerts')->count(),
            ],
            'enabled' => (bool) Integration::active(auth()->user()->vendor_id, Integration::SMART_METERS),
            'history' => $this->historyFor
                ? MeterTelemetry::where('meter_id', $this->historyFor)->latest('read_at')->limit(50)->get()
                : collect(),
            'historyMeter' => $this->historyFor ? Meter::find($this->historyFor) : null,
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="stat-card tone-sky flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-sky-500 text-white shadow-sm"><flux:icon.signal variant="mini" class="size-5" /></span>
            <div>
                <flux:heading size="lg">Smart meters</flux:heading>
                <flux:text class="text-sm">Meters that report their own readings over LoRaWAN, NB-IoT or GPRS. Monthly bills are read from their data — no site visit needed.</flux:text>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('vendor.readings.manage')
                <flux:button icon="clipboard-document-check" wire:click="createReadings" wire:confirm="Create this month's readings from the latest smart meter values?">Take this month's readings</flux:button>
            @endcan
            @can('vendor.settings.manage')
                <flux:button variant="ghost" icon="cog-6-tooth" :href="route('vendor.settings.index', ['tab' => 'smart-meters'])" wire:navigate>Connect meters</flux:button>
            @endcan
        </div>
    </div>

    @unless ($enabled)
        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
            <flux:icon.exclamation-triangle variant="mini" class="mt-0.5 size-5 shrink-0" />
            Smart meter data is switched off. Turn it on in Settings → Smart meters to get the webhook address for your LoRaWAN network or meter supplier.
        </div>
    @endunless

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat-card tone-brand p-5"><span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Smart meters</span><div class="mt-2 text-2xl font-semibold">{{ $counts['total'] }}</div></div>
        <div class="stat-card tone-emerald p-5"><span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Reported in the last 24 h</span><div class="mt-2 text-2xl font-semibold">{{ $counts['online'] }}</div></div>
        <div class="stat-card tone-{{ $counts['alerts'] ? 'rose' : 'violet' }} p-5"><span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">With alerts</span><div class="mt-2 text-2xl font-semibold">{{ $counts['alerts'] }}</div></div>
    </div>

    <div class="app-card grid gap-3 p-4 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Meter number, device ID or customer" class="sm:col-span-2" />
        <flux:select wire:model.live="filter">
            <flux:select.option value="">All smart meters</flux:select.option>
            <flux:select.option value="alerts">With alerts</flux:select.option>
            <flux:select.option value="offline">Offline (no data for 24 h)</flux:select.option>
            <flux:select.option value="prepaid">Prepaid</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$meters">
        <flux:table.columns>
            <flux:table.column>Meter</flux:table.column>
            <flux:table.column>Customer</flux:table.column>
            <flux:table.column>Last seen</flux:table.column>
            <flux:table.column align="end">Reading (m³)</flux:table.column>
            <flux:table.column align="end">Battery</flux:table.column>
            <flux:table.column>Alerts</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($meters as $m)
                <flux:table.row :key="'sm-'.$m->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full {{ $m->isOnline() ? 'bg-emerald-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"></span>
                            <span class="font-mono text-sm">{{ $m->serial }}</span>
                            @if ($m->isPrepaid())<flux:badge size="sm" color="violet">Prepaid</flux:badge>@endif
                        </div>
                        <div class="font-mono text-xs text-zinc-500">{{ $m->device_id }} · {{ strtoupper((string) $m->connectivity) ?: '—' }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="text-sm">{{ $m->client?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-sm">{{ $m->last_seen_at?->diffForHumans() ?? 'Never' }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ $m->last_value !== null ? number_format((float) $m->last_value, 3) : '—' }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono {{ $m->battery_percent !== null && $m->battery_percent < 20 ? 'text-rose-600' : '' }}">{{ $m->battery_percent !== null ? $m->battery_percent.'%' : '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @forelse ((array) $m->alerts as $alert)
                            <flux:badge size="sm" color="red">{{ ucfirst(str_replace('_', ' ', $alert)) }}</flux:badge>
                        @empty
                            <span class="text-xs text-zinc-400">None</span>
                        @endforelse
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="chart-bar" wire:click="showHistory({{ $m->id }})">History</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row><flux:table.cell colspan="7" class="py-10 text-center text-sm text-zinc-500">No smart meters yet. Edit a meter and enter its device ID (DevEUI / IMEI) to link it.</flux:table.cell></flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="history" class="w-full max-w-2xl">
        @if ($historyMeter)
            <div class="space-y-4">
                <flux:heading size="lg">Meter {{ $historyMeter->serial }} — latest data</flux:heading>
                <div class="max-h-[60vh] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-zinc-500"><tr><th class="py-2">Time</th><th class="py-2 text-right">m³</th><th class="py-2 text-right">Used</th><th class="py-2 text-right">Battery</th><th class="py-2">Alerts</th></tr></thead>
                        <tbody>
                            @foreach ($history as $i => $row)
                                @php $older = $history[$i + 1] ?? null; @endphp
                                <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                    <td class="py-1.5">{{ $row->read_at->format('d M Y H:i') }}</td>
                                    <td class="py-1.5 text-right font-mono">{{ number_format((float) $row->value, 3) }}</td>
                                    <td class="py-1.5 text-right font-mono text-zinc-500">{{ $older ? number_format((float) $row->value - (float) $older->value, 3) : '—' }}</td>
                                    <td class="py-1.5 text-right font-mono">{{ $row->battery_percent !== null ? $row->battery_percent.'%' : '—' }}</td>
                                    <td class="py-1.5 text-xs text-rose-600">{{ implode(', ', (array) $row->alerts) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($history->isEmpty())<p class="py-6 text-center text-sm text-zinc-500">This meter has not sent any data yet.</p>@endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
