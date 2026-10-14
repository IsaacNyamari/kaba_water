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

    /** Edit mode: set a new PIN/password for the client's existing portal login. */
    public string $new_pin = '';

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

    /** True when this save should create a portal login (new client, or an existing one without a login). */
    protected function creatingLogin(): bool
    {
        return $this->create_portal_account && ! $this->client?->user_id;
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
            // A login needs an email OR a phone number (phone logins use phone + meter number + PIN).
            'email'      => $this->creatingLogin()
                ? ['nullable', 'required_without:phone', 'email', 'max:150', 'unique:users,email']
                : ['nullable', 'email', 'max:150'],
            'phone'      => $this->creatingLogin()
                ? ['nullable', 'required_without:email', 'string', 'max:30']
                : ['nullable', 'string', 'max:30'],
            'address'    => ['nullable', 'string', 'max:500'],
            'status'     => ['required', 'in:active,inactive'],
            'password'   => [$this->creatingLogin() ? 'required' : 'nullable', 'string', 'min:6', 'max:255'],
            'new_pin'    => ['nullable', 'string', 'min:6', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required_without' => 'Give an email or a phone number so the client can sign in.',
            'phone.required_without' => 'Give an email or a phone number so the client can sign in.',
        ];
    }

    /** Create the client's portal login. Email is optional: phone-only clients sign in with phone + meter + PIN. */
    protected function createPortalUser(): User
    {
        $user = User::create([
            'vendor_id' => auth()->user()->vendor_id,
            'name'      => $this->name,
            'email'     => $this->email ?: null,
            'password'  => $this->password,
        ]);
        // The vendor vouches for this person; without this the client is
        // stuck behind the "verified" middleware on the portal.
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('client');

        return $user;
    }

    public function save(): void
    {
        $this->authorize($this->client ? 'update' : 'create', $this->client ?? Client::class);
        $this->validate();

        if ($this->client) {
            DB::transaction(function () {
                $this->client->update([
                    'account_no' => $this->account_no,
                    'name'       => $this->name,
                    'email'      => $this->email ?: null,
                    'phone'      => $this->phone ?: null,
                    'address'    => $this->address ?: null,
                    'status'     => $this->status,
                ]);

                if ($this->creatingLogin()) {
                    $this->client->update(['user_id' => $this->createPortalUser()->id]);
                } elseif ($this->client->user_id && $this->new_pin !== '') {
                    // Phone-only clients can't use "forgot password" — the vendor sets a new PIN.
                    User::whereKey($this->client->user_id)->first()?->update(['password' => $this->new_pin]);
                }
            });

            session()->flash('status', $this->new_pin !== '' ? 'Client updated and new PIN set.' : 'Client updated.');
        } else {
            DB::transaction(function () {
                $userId = $this->create_portal_account ? $this->createPortalUser()->id : null;

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

        <div class="card">
            <div class="card-header">
                <flux:heading size="sm">Portal account (optional)</flux:heading>
            </div>
            <div class="card-body space-y-4">
                @if ($client?->user_id)
                    <flux:text class="text-sm">
                        This client has a portal login.
                        @if ($client->email)
                            They sign in with <strong>{{ $client->email }}</strong>, or with their phone and meter number.
                        @else
                            They sign in with their <strong>phone number, meter number and PIN</strong>.
                        @endif
                    </flux:text>

                    <flux:input wire:model="new_pin" type="password" viewable autocomplete="new-password"
                        label="Set a new PIN or password (optional)"
                        description="Use this if they've forgotten it. At least 6 characters. Leave blank to keep the current one." />
                @else
                    <flux:checkbox wire:model.live="create_portal_account"
                        label="Create a client portal login"
                        description="They'll be able to view invoices, readings and make payments." />

                    @if ($create_portal_account)
                        <flux:input wire:model="password" label="Initial PIN or password"
                            description="At least 6 characters. Share it with the client — they can change it after logging in." />

                        <flux:callout icon="device-phone-mobile" color="sky">
                            <flux:callout.text>
                                No email? Leave it blank and fill in the phone number. The client then signs in on the
                                <strong>Phone &amp; meter</strong> tab with their phone number, their meter number and this PIN.
                                Add their meter after saving.
                            </flux:callout.text>
                        </flux:callout>
                    @endif
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('vendor.clients.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ $client ? 'Save changes' : 'Create client' }}</span>
                <span wire:loading>Saving…</span>
            </flux:button>
        </div>
    </form>
</div>