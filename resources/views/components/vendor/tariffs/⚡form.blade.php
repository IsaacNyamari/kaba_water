<?php

use App\Models\Tariff;
use Livewire\Component;

new class extends Component {
    public ?Tariff $tariff = null;

    public string $name = '';
    public string $type = 'flat';
    public string $rate = '';
    public array $tiers = [];
    public bool $is_active = true;

    public function mount(?Tariff $tariff = null): void
    {
        if ($tariff && $tariff->exists) {
            $this->authorize('update', $tariff);
            $this->tariff = $tariff;
            $this->name = $tariff->name;
            $this->type = $tariff->type;
            $this->rate = (string) ($tariff->rate ?? '');
            $this->tiers = $tariff->tiers ?? [];
            $this->is_active = (bool) $tariff->is_active;
        } else {
            $this->authorize('create', Tariff::class);
            $this->tiers = [['up_to' => 10, 'rate' => 0], ['up_to' => null, 'rate' => 0]];
        }
    }

    protected function rules(): array
    {
        $base = [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:flat,tiered'],
            'is_active' => ['boolean'],
        ];

        if ($this->type === 'flat') {
            $base['rate'] = ['required', 'numeric', 'min:0'];
        } else {
            $base['tiers'] = ['required', 'array', 'min:1'];
            $base['tiers.*.up_to'] = ['nullable', 'integer', 'min:1'];
            $base['tiers.*.rate'] = ['required', 'numeric', 'min:0'];
        }

        return $base;
    }

    public function addTier(): void
    {
        $this->tiers[] = ['up_to' => null, 'rate' => 0];
    }

    public function removeTier(int $index): void
    {
        unset($this->tiers[$index]);
        $this->tiers = array_values($this->tiers);
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'name' => $this->name,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'rate' => $this->type === 'flat' ? $this->rate : null,
            'tiers' =>
                $this->type === 'tiered'
                    ? array_map(
                        fn($t) => [
                            'up_to' => $t['up_to'] !== '' ? (int) $t['up_to'] : null,
                            'rate' => (float) $t['rate'],
                        ],
                        $this->tiers,
                    )
                    : null,
        ];

        if ($this->tariff) {
            $this->tariff->update($payload);
            session()->flash('status', 'Tariff updated.');
        } else {
            Tariff::create($payload);
            session()->flash('status', 'Tariff created.');
        }

        $this->redirectRoute('vendor.tariffs.index', navigate: true);
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl">
    <form wire:submit="save" class="space-y-6">

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-5">
            <flux:heading size="sm">{{ $tariff ? 'Edit tariff' : 'New tariff' }}</flux:heading>

            <flux:input wire:model="name" label="Name" placeholder="e.g. Residential Standard" />

            <flux:radio.group wire:model.live="type" label="Pricing model">
                <flux:radio value="flat" label="Flat rate" description="Single price per m³." />
                <flux:radio value="tiered" label="Tiered" description="Different price per consumption band." />
            </flux:radio.group>

            @if ($type === 'flat')
                <flux:input wire:model="rate" type="number" step="0.0001" min="0" label="Rate per m³" />
            @else
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            Bands
                        </label>
                        <flux:button size="sm" variant="ghost" icon="plus" type="button" wire:click="addTier">
                            Add band
                        </flux:button>
                    </div>

                    @foreach ($tiers as $i => $tier)
                        <div wire:key="tier-{{ $i }}" class="grid grid-cols-[1fr_1fr_auto] gap-3 items-end">
                            <flux:input type="number" min="1" wire:model="tiers.{{ $i }}.up_to"
                                label="Up to (m³)" placeholder="∞" />

                            <flux:input type="number" step="0.0001" min="0"
                                wire:model="tiers.{{ $i }}.rate" label="Rate per m³" />

                            @if (count($tiers) > 1)
                                <flux:button size="sm" variant="ghost" icon="trash" type="button"
                                    class="!text-rose-600" wire:click="removeTier({{ $i }})" />
                            @else
                                <span class="w-8"></span>
                            @endif
                        </div>
                        @error("tiers.{$i}.rate")
                            <p class="text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    @endforeach

                    <p class="text-xs text-zinc-500">
                        Leave the last band's "Up to" empty to catch everything above the highest threshold.
                    </p>
                </div>
            @endif

            <flux:checkbox wire:model="is_active" label="Active"
                description="Only active tariffs can be used to generate invoices." />
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.tariffs.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $tariff ? 'Save changes' : 'Create tariff' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>
