<?php

use App\Models\Plan;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] class extends Component
{
    public ?Plan $plan = null;

    public string $name         = '';
    public string $slug         = '';
    public string $monthly_price = '0';
    public int    $max_clients  = 50;
    public int    $max_meters   = 50;
    public string $features     = '';

    public function mount(?Plan $plan = null): void
    {
        if ($plan && $plan->exists) {
            $this->authorize('update', $plan);
            $this->plan          = $plan;
            $this->name          = $plan->name;
            $this->slug          = $plan->slug;
            $this->monthly_price = (string) $plan->monthly_price;
            $this->max_clients   = $plan->max_clients;
            $this->max_meters    = $plan->max_meters;
            $this->features      = implode("\n", $plan->features ?? []);
        } else {
            $this->authorize('create', Plan::class);
        }
    }

    public function updatedName(): void
    {
        if (! $this->plan) {
            $this->slug = Str::slug($this->name);
        }
    }

    protected function rules(): array
    {
        $uniqueSlug = $this->plan?->id
            ? 'unique:plans,slug,' . $this->plan->id
            : 'unique:plans,slug';

        return [
            'name'          => ['required', 'string', 'max:100'],
            'slug'          => ['required', 'string', 'max:100', $uniqueSlug],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'max_clients'   => ['required', 'integer', 'min:1'],
            'max_meters'    => ['required', 'integer', 'min:1'],
            'features'      => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $features = collect(explode("\n", $this->features))
            ->map(fn ($f) => trim($f))
            ->filter()
            ->values()
            ->all();

        $payload = [
            'name'          => $this->name,
            'slug'          => $this->slug,
            'monthly_price' => $this->monthly_price,
            'max_clients'   => $this->max_clients,
            'max_meters'    => $this->max_meters,
            'features'      => $features,
        ];

        if ($this->plan) {
            $this->plan->update($payload);
            session()->flash('status', 'Plan updated.');
        } else {
            Plan::create($payload);
            session()->flash('status', 'Plan created.');
        }

        $this->redirectRoute('admin.plans.index', navigate: true);
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl space-y-6">

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-5">
            <flux:heading size="sm">{{ $plan ? 'Edit plan' : 'New plan' }}</flux:heading>

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model.live="name" label="Name" />
                <flux:input wire:model="slug" label="Slug" />
            </div>

            <flux:input wire:model="monthly_price" type="number" step="0.01" min="0"
                label="Monthly price" />

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="max_clients" type="number" min="1" label="Max clients" />
                <flux:input wire:model="max_meters" type="number" min="1" label="Max meters" />
            </div>

            <flux:textarea wire:model="features" label="Features"
                description="One feature per line." rows="4" />
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('admin.plans.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $plan ? 'Save changes' : 'Create plan' }}</span>
                <span wire:loading>Saving...</span>
            </flux:button>
        </div>
    </form>
</div>