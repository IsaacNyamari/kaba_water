<?php

namespace App\Livewire\Admin\Vendors;

use App\Enums\VendorStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\Vendor;
use App\Support\DefaultExpenseCategories;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] class extends Component {
    public ?Vendor $vendor = null;

    public string $name = '';
    public string $slug = '';
    public string $email = '';
    public string $phone = '';
    public string $currency = 'KES';
    public string $status = 'trial';
    public ?int $plan_id = null;

    // Optional owner account
    public bool $create_owner = false;
    public string $owner_name = '';
    public string $owner_email = '';
    public string $owner_password = '';

    public function mount(?Vendor $vendor = null): void
    {
        if ($vendor && $vendor->exists) {
            $this->authorize('update', $vendor);
            $this->vendor = $vendor;
            $this->name = $vendor->name;
            $this->slug = $vendor->slug;
            $this->email = $vendor->email;
            $this->phone = $vendor->phone ?? '';
            $this->currency = $vendor->currency;
            $this->status = $vendor->status->value;
            $this->plan_id = $vendor->plan_id;
        } else {
            $this->authorize('create', Vendor::class);
            $this->status = VendorStatus::Trial->value;
            $this->plan_id = Plan::orderBy('monthly_price')->value('id');
        }
    }

    public function title(): string
    {
        return $this->vendor ? 'Edit vendor' : 'New vendor';
    }

    public function updatedName(): void
    {
        if (!$this->vendor) {
            $this->slug = Str::slug($this->name);
        }
    }

    protected function rules(): array
    {
        $uniqueSlug = $this->vendor?->id ? 'unique:vendors,slug,' . $this->vendor->id : 'unique:vendors,slug';

        $uniqueEmail = $this->vendor?->id ? 'unique:vendors,email,' . $this->vendor->id : 'unique:vendors,email';

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', $uniqueSlug],
            'email' => ['required', 'email', 'max:150', $uniqueEmail],
            'phone' => ['nullable', 'string', 'max:30'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'in:trial,active,suspended'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ];

        if ($this->create_owner && !$this->vendor) {
            $rules['owner_name'] = ['required', 'string', 'max:150'];
            $rules['owner_email'] = ['required', 'email', 'max:150', 'unique:users,email'];
            $rules['owner_password'] = ['required', 'string', 'min:8'];
        }

        return $rules;
    }

    public function save(): void
    {
        $this->authorize($this->vendor ? 'update' : 'create', $this->vendor ?? Vendor::class);
        $this->validate();

        $payload = [
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'currency' => strtoupper($this->currency),
            'status' => $this->status,
            'plan_id' => $this->plan_id,
        ];

        if ($this->vendor) {
            $this->vendor->update($payload);
            session()->flash('status', 'Vendor updated.');
        } else {
            $payload['trial_ends_at'] = now()->addDays(30);

            \Illuminate\Support\Facades\DB::transaction(function () use ($payload) {
                $vendor = Vendor::create($payload);
                DefaultExpenseCategories::seedFor($vendor->id);

                if ($this->create_owner) {
                    $user = User::create([
                        'vendor_id' => $vendor->id,
                        'name' => $this->owner_name,
                        'email' => $this->owner_email,
                        'password' => $this->owner_password,
                    ]);
                    // Admin-created account: mark verified so the owner can sign in straight away.
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $user->assignRole('vendor');
                }
            });

            session()->flash('status', 'Vendor created.');
        }

        $this->redirectRoute('admin.vendors.index', navigate: true);
    }

    public function with(): array
    {
        return [
            'plans' => Plan::orderBy('monthly_price')->get(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl space-y-6">

    <form wire:submit="save" class="space-y-6">

        {{-- Company --}}
        <flux:card class="space-y-5">
            <flux:heading size="sm">{{ $vendor ? 'Edit vendor' : 'New vendor' }}</flux:heading>

            <flux:input wire:model.live="name" label="Business name" />

            <flux:input wire:model="slug" label="Slug"
                description="Used in URLs and API paths. Auto-generated from name." />

            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="email" type="email" label="Contact email" />
                <flux:input wire:model="phone" label="Phone" />
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input wire:model="currency" label="Currency" description="ISO 4217" maxlength="3"
                    class="uppercase" />

                <flux:select wire:model="status" label="Status">
                    @foreach (VendorStatus::cases() as $s)
                        <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="plan_id" label="Plan" searchable placeholder="No plan">
                    @foreach ($plans as $p)
                        <flux:select.option value="{{ $p->id }}">
                            {{ $p->name }} — {{ number_format((float) $p->monthly_price, 2) }}/mo
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </flux:card>

        {{-- Owner account (only when creating) --}}
        @unless ($vendor)
            <flux:card class="space-y-5">
                <flux:heading size="sm">Owner account</flux:heading>

                <flux:checkbox wire:model.live="create_owner" label="Create a vendor owner login"
                    description="The owner can sign in and manage this vendor's clients, meters, and invoices." />

                @if ($create_owner)
                    <flux:input wire:model="owner_name" label="Owner name" />
                    <flux:input wire:model="owner_email" type="email" label="Owner email" />
                    <flux:input wire:model="owner_password" label="Initial password"
                        description="Share this with the vendor — they can change it after login." />
                @endif
            </flux:card>
        @endunless

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('admin.vendors.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $vendor ? 'Save changes' : 'Create vendor' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>
