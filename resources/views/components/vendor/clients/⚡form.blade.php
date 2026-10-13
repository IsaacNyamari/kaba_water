<?php

use App\Models\Client;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.vendor')] class extends Component {
    public ?Client $client = null;

    public string $account_no = '';
    public string $name       = '';
    public string $email      = '';
    public string $phone      = '';
    public string $address    = '';
    public string $status     = 'active';

    public bool $create_portal_account = false;
    public string $password = '';

    public function mount(?Client $client = null): void
    {
        if ($client && $client->exists) {
            $this->authorize('update', $client);
            $this->client = $client;
            $this->account_no = $client->account_no;
            $this->name       = $client->name;
            $this->email      = $client->email ?? '';
            $this->phone      = $client->phone ?? '';
            $this->address    = $client->address ?? '';
            $this->status     = $client->status;
        } else {
            $this->authorize('create', Client::class);
            $this->fillDefaults();
        }
    }

    public function fillDefaults(): void
    {
        $vendorId = auth()->user()->vendor_id;

        $this->account_no = Client::nextAccountNo($vendorId);
    }

    protected function rules(): array
    {
        $vendorId = auth()->user()->vendor_id;

        $unique = $this->client?->id
            ? "unique:clients,account_no,{$this->client->id},id,vendor_id,{$vendorId}"
            : "unique:clients,account_no,NULL,id,vendor_id,{$vendorId}";

        return [
            'account_no' => ['required', 'string', 'max:50', $unique],
            'name'       => ['required', 'string', 'max:150'],
            'email'      => $this->create_portal_account && ! $this->client
                ? ['required', 'email', 'max:150', 'unique:users,email']
                : ['nullable', 'email', 'max:150'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'address'    => ['nullable', 'string', 'max:500'],
            'status'     => ['required', 'in:active,inactive'],
            'password'   => [$this->create_portal_account && ! $this->client ? 'required' : 'nullable', 'string', 'min:8'],
        ];
    }

    public function save(): void
    {
        $this->authorize($this->client ? 'update' : 'create', $this->client ?? Client::class);
        $this->validate();

        if ($this->client) {
            $this->client->update([
                'account_no' => $this->account_no,
                'name'       => $this->name,
                'email'      => $this->email ?: null,
                'phone'      => $this->phone ?: null,
                'address'    => $this->address ?: null,
                'status'     => $this->status,
            ]);
            session()->flash('status', 'Client updated.');
        } else {
            DB::transaction(function () {
                $userId = null;

                if ($this->create_portal_account) {
                    $user = User::create([
                        'vendor_id' => auth()->user()->vendor_id,
                        'name'      => $this->name,
                        'email'     => $this->email,
                        'password'  => $this->password,
                    ]);
                    // The vendor vouches for this address; without this the client is
                    // stuck behind the "verified" middleware on the portal.
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $user->assignRole('client');
                    $userId = $user->id;
                }

                Client::create([
                    'user_id'    => $userId,
                    'account_no' => $this->account_no,
                    'name'       => $this->name,
                    'email'      => $this->email ?: null,
                    'phone'      => $this->phone ?: null,
                    'address'    => $this->address ?: null,
                    'status'     => $this->status,
                ]);
            });
            session()->flash('status', 'Client created.');
        }

        $this->redirectRoute('vendor.clients.index', navigate: true);
    }
};
?>

<div class="max-w-3xl">
    <form wire:submit="save" class="space-y-6">

        <div class="card">
            <div class="card-header">
                <flux:heading size="sm">{{ $client ? 'Edit client' : 'New client' }}</flux:heading>
            </div>
            <div class="card-body grid gap-5 sm:grid-cols-2">

                <flux:input wire:model="account_no" label="Account number" />
                <flux:select wire:model="status" label="Status">
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:input wire:model="name" label="Full name" />
                </div>

                <flux:input wire:model="phone" label="Phone" />
                <flux:input wire:model="email" type="email" label="Email" />

                <div class="sm:col-span-2">
                    <flux:textarea wire:model="address" label="Address" rows="2" />
                </div>
            </div>
        </div>

        @unless ($client)
        <div class="card">
            <div class="card-header">
                <flux:heading size="sm">Portal account (optional)</flux:heading>
            </div>
            <div class="card-body space-y-4">
                <flux:checkbox wire:model.live="create_portal_account"
                    label="Create a client portal login"
                    description="They'll be able to view invoices, readings and make payments." />

                @if ($create_portal_account)
                <flux:input wire:model="password" label="Initial password"
                    description="Share this with the client — they can change it after login." />
                @endif
            </div>
        </div>
        @endunless

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.clients.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $client ? 'Save changes' : 'Create client' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>