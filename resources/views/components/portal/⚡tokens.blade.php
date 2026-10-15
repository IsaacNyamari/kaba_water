<?php

use App\Livewire\Concerns\InteractsWithClient;
use App\Models\Meter;
use App\Models\PrepaidToken;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/* Client portal: buy tokens for your prepaid meters and see past tokens. */
new #[Layout('layouts.portal'), Title('Water tokens')] class extends Component {
    use InteractsWithClient;

    public function with(): array
    {
        $client = $this->requireClient();

        return [
            'meters' => Meter::where('client_id', $client->id)->where('billing_mode', 'prepaid')->orderBy('serial')->get(),
            'tokens' => PrepaidToken::where('client_id', $client->id)->with('meter')->latest()->limit(30)->get(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div>
        <flux:heading size="xl">Water tokens</flux:heading>
        <flux:text class="text-sm">Buy water for your prepaid meter with M-Pesa. Your token appears here and comes by SMS.</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        @forelse ($meters as $meter)
            <div wire:key="pm-{{ $meter->id }}" class="app-card flex items-center justify-between gap-4 p-5">
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-500">Prepaid meter</p>
                    <p class="font-mono text-lg font-semibold">{{ $meter->serial }}</p>
                    <p class="text-xs text-zinc-500">Paybill account: <span class="font-mono">{{ $meter->serial }}</span></p>
                </div>
                @can('client.payments.make')
                    <flux:button variant="primary" icon="key" :href="$meter->tokenUrl()">Buy token</flux:button>
                @endcan
            </div>
        @empty
            <div class="app-card p-6 text-sm text-zinc-500 sm:col-span-2">You have no prepaid meters.</div>
        @endforelse
    </div>

    <div class="app-card overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 text-left text-xs uppercase tracking-wide text-zinc-500 dark:border-zinc-700">
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Meter</th>
                    <th class="px-5 py-3">Token</th>
                    <th class="px-5 py-3 text-right">Units (m³)</th>
                    <th class="px-5 py-3 text-right">Paid</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tokens as $t)
                    <tr wire:key="tk-{{ $t->id }}" class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                        <td class="whitespace-nowrap px-5 py-3">{{ $t->created_at->format('d M Y H:i') }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $t->meter?->serial }}</td>
                        <td class="px-5 py-3 font-mono tracking-wider">
                            {{ $t->status === 'issued' ? $t->formattedToken() : 'Being issued — the office will send it' }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono">{{ number_format((float) $t->units, 3) }}</td>
                        <td class="px-5 py-3 text-right font-mono">{{ number_format((float) $t->amount_paid, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-zinc-500">No tokens bought yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
