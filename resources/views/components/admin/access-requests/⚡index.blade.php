<?php

use App\Livewire\Concerns\RefreshesOnActivity;

use App\Actions\AccessRequests\ApproveAccessRequest;
use App\Actions\AccessRequests\RejectAccessRequest;
use App\Enums\AccessRequestStatus;
use App\Enums\BusinessType;
use App\Support\TeamRoles;
use App\Models\AccessRequest;
use App\Models\Client;
use App\Models\Vendor;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin'), Title('Access Requests')] class extends Component {
    use WithPagination, RefreshesOnActivity;

    #[Url(history: true)]
    public string $status = 'pending';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    // Approve modal
    public ?int $approvingId = null;
    public string $role = 'vendor';
    public string $workspaceMode = 'new'; // new | existing (vendor role)
    public string $businessType = 'water'; // new workspace: water | property
    public ?int $vendorId = null;
    public ?int $clientId = null;

    // Reject modal
    public ?int $rejectingId = null;
    public string $rejectionReason = '';
    public bool $notifyRejection = true;

    public function mount(): void
    {
        $this->authorize('viewAny', AccessRequest::class);
    }

    public function updating($field): void
    {
        if (in_array($field, ['status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function updatedRole(): void
    {
        $this->reset(['vendorId', 'clientId']);
        $this->workspaceMode = 'new';
        $this->resetValidation();
    }

    public function updatedVendorId(): void
    {
        $this->clientId = null;
    }

    #[Computed]
    public function approving(): ?AccessRequest
    {
        return $this->approvingId ? AccessRequest::find($this->approvingId) : null;
    }

    #[Computed]
    public function vendors()
    {
        return Vendor::orderBy('name')->get(['id', 'name']);
    }

    /** Customer accounts of the chosen provider that don't have a login yet. */
    #[Computed]
    public function clients()
    {
        if (! $this->vendorId) {
            return collect();
        }

        return Client::withoutGlobalScope('vendor')
            ->where('vendor_id', $this->vendorId)
            ->whereNull('user_id')
            ->orderBy('name')
            ->get(['id', 'name', 'account_no', 'email']);
    }

    public function openApprove(int $id): void
    {
        $request = AccessRequest::findOrFail($id);
        $this->authorize('review', $request);

        $this->resetValidation();
        $this->approvingId = $request->id;
        $this->role = $request->requested_type === AccessRequest::TYPE_CLIENT ? 'client' : 'vendor';
        $this->workspaceMode = 'new';
        $this->businessType = $request->businessType()->value;
        $this->vendorId = null;
        $this->clientId = null;

        // Client request: try to pre-select a matching customer account by email.
        if ($this->role === 'client') {
            $match = Client::withoutGlobalScope('vendor')
                ->whereNull('user_id')
                ->where('email', $request->email)
                ->first();

            if ($match) {
                $this->vendorId = $match->vendor_id;
                $this->clientId = $match->id;
            }
        }

        Flux::modal('approve-request')->show();
    }

    public function approve(ApproveAccessRequest $action): void
    {
        $request = AccessRequest::findOrFail($this->approvingId);
        $this->authorize('review', $request);
        $this->resetValidation();

        $vendorId = match ($this->role) {
            'vendor' => $this->workspaceMode === 'existing' ? $this->vendorId : null,
            'super_admin' => null,
            default => $this->vendorId, // client and team roles
        };

        if ($this->role === 'vendor' && $this->workspaceMode === 'existing' && ! $vendorId) {
            $this->addError('vendorId', 'Choose the workspace to join.');
            return;
        }

        try {
            $action->handle(
                $request, $this->role, $vendorId, $this->role === 'client' ? $this->clientId : null, auth()->user(),
                businessType: BusinessType::tryFrom($this->businessType),
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(in_array($field, ['vendorId', 'clientId', 'role'], true) ? $field : 'role', $messages[0]);
            }
            return;
        } catch (\Throwable $e) {
            // Approval is saved before mailing; a mail failure leaves it "Invited".
            report($e);
            Flux::modal('approve-request')->close();
            Flux::toast(variant: 'warning', heading: 'Approved, email not sent',
                text: 'The signup email could not be sent (' . $e->getMessage() . '). Fix mail settings, then use "Resend link".');
            $this->approvingId = null;
            return;
        }

        Flux::modal('approve-request')->close();
        Flux::toast(variant: 'success', heading: 'Approved', text: "Signup link emailed to {$request->email}.");
        $this->approvingId = null;
    }

    public function resend(int $id, ApproveAccessRequest $action): void
    {
        $request = AccessRequest::findOrFail($id);
        $this->authorize('review', $request);

        if (! $request->isApproved()) {
            return;
        }

        try {
            $action->sendInvitation($request->loadMissing('vendor'));
            Flux::toast(variant: 'success', heading: 'Link resent', text: "A new signup link was emailed to {$request->email}. The old one no longer works.");
        } catch (\Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', heading: 'Email failed', text: $e->getMessage());
        }
    }

    public function openReject(int $id): void
    {
        $request = AccessRequest::findOrFail($id);
        $this->authorize('review', $request);

        $this->rejectingId = $request->id;
        $this->rejectionReason = '';
        $this->notifyRejection = true;
        Flux::modal('reject-request')->show();
    }

    public function reject(RejectAccessRequest $action): void
    {
        $request = AccessRequest::findOrFail($this->rejectingId);
        $this->authorize('review', $request);

        $this->validate(['rejectionReason' => ['nullable', 'string', 'max:500']]);

        try {
            $action->handle($request, auth()->user(), $this->rejectionReason, $this->notifyRejection);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', heading: 'Cannot reject', text: collect($e->errors())->flatten()->first());
            return;
        }

        Flux::modal('reject-request')->close();
        Flux::toast(variant: 'warning', heading: 'Rejected', text: "Request from {$request->email} rejected.");
        $this->rejectingId = null;
    }

    public function with(): array
    {
        return [
            'requests' => AccessRequest::query()
                ->with(['vendor:id,name', 'reviewer:id,name'])
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('organization', 'like', "%{$this->search}%")
                    ->orWhere('provider_name', 'like', "%{$this->search}%")))
                ->latest()
                ->paginate(20),
            'pendingCount' => AccessRequest::pending()->count(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <flux:heading size="lg">Access requests</flux:heading>
            <flux:text class="text-sm">
                Self sign-up is off. Approving a request emails a single-use signup link.
                Auto-approve is <strong>{{ config('access.auto_approve') ? 'ON' : 'OFF' }}</strong>
                (<code>ACCESS_AUTO_APPROVE</code>).
            </flux:text>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search name, email, company…"
            class="sm:w-80" />
        <flux:select wire:model.live="status" class="sm:w-56">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach (AccessRequestStatus::cases() as $s)
                <flux:select.option value="{{ $s->value }}">
                    {{ $s->label() }}{{ $s === AccessRequestStatus::Pending ? " ({$pendingCount})" : '' }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$requests">
        <flux:table.columns>
            <flux:table.column>Requested</flux:table.column>
            <flux:table.column>Person</flux:table.column>
            <flux:table.column>Wants</flux:table.column>
            <flux:table.column>Email check</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column align="end">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($requests as $r)
                <flux:table.row :key="$r->id">
                    <flux:table.cell class="whitespace-nowrap text-sm">
                        {{ $r->created_at->format('d M Y') }}
                        <div class="text-xs text-zinc-500">{{ $r->created_at->diffForHumans() }}</div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $r->name }}</div>
                        <div class="text-xs text-zinc-500">{{ $r->email }}{{ $r->phone ? ' · ' . $r->phone : '' }}</div>
                        @if ($r->message)
                            <div class="text-xs text-zinc-500 mt-1 max-w-sm italic">“{{ \Illuminate\Support\Str::limit($r->message, 120) }}”</div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-sm">
                        @if ($r->wantsWorkspace())
                            <flux:badge size="sm" :color="$r->requested_type === AccessRequest::TYPE_LANDLORD ? 'amber' : 'violet'">
                                {{ $r->requested_type === AccessRequest::TYPE_LANDLORD ? 'Landlord' : 'Water company' }}
                            </flux:badge>
                            <div class="text-xs text-zinc-500 mt-1">{{ $r->organization }}</div>
                        @else
                            <flux:badge size="sm" color="sky">Client</flux:badge>
                            <div class="text-xs text-zinc-500 mt-1">
                                {{ $r->provider_name }}{{ $r->account_reference ? ' · #' . $r->account_reference : '' }}
                            </div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($r->email_check)
                            <flux:badge size="sm" :color="$r->email_check->color()" :title="$r->email_check_detail">
                                {{ $r->email_check->label() }}
                            </flux:badge>
                        @else
                            <span class="text-xs text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" :color="$r->status->color()">{{ $r->status->label() }}</flux:badge>
                        <div class="text-xs text-zinc-500 mt-1">
                            @if ($r->auto_approved)
                                Auto-approved
                            @elseif ($r->reviewer)
                                by {{ $r->reviewer->name }}
                            @endif
                            @if ($r->role)
                                · {{ $r->role === 'client' ? 'Client' : ($r->role === 'super_admin' ? 'Platform admin' : TeamRoles::label($r->role)) }}{{ $r->vendor ? ' @ ' . $r->vendor->name : '' }}
                            @endif
                        </div>
                        @if ($r->status === AccessRequestStatus::Approved && $r->invite_expires_at?->isPast())
                            <div class="text-xs text-rose-600">Link expired</div>
                        @endif
                        @if ($r->status === AccessRequestStatus::Rejected && $r->rejection_reason)
                            <div class="text-xs text-rose-600 max-w-xs">{{ $r->rejection_reason }}</div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="inline-flex gap-1">
                            @if ($r->status === AccessRequestStatus::Pending)
                                <flux:button size="sm" variant="primary" icon="check" wire:click="openApprove({{ $r->id }})">Approve</flux:button>
                                <flux:button size="sm" variant="ghost" icon="x-mark" class="!text-rose-600" wire:click="openReject({{ $r->id }})">Reject</flux:button>
                            @elseif ($r->status === AccessRequestStatus::Approved)
                                <flux:button size="sm" variant="filled" icon="arrow-path" wire:click="resend({{ $r->id }})"
                                    wire:confirm="Send a new signup link? The previous link will stop working.">Resend link</flux:button>
                                <flux:button size="sm" variant="ghost" icon="no-symbol" class="!text-rose-600" wire:click="openReject({{ $r->id }})">Revoke</flux:button>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 py-10">No requests here.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Approve modal --}}
    <flux:modal name="approve-request" class="w-full max-w-lg">
        @if ($this->approving)
            <form wire:submit="approve" class="space-y-5">
                <div>
                    <flux:heading size="lg">Approve {{ $this->approving->name }}</flux:heading>
                    <flux:subheading>{{ $this->approving->email }} will be emailed a signup link.</flux:subheading>
                </div>

                <flux:text class="text-sm">
                    They asked as: <strong>{{ $this->approving->typeLabel() }}</strong>{{ $this->approving->organization ? ' · ' . $this->approving->organization : '' }}
                </flux:text>

                <flux:select wire:model.live="role" label="Role to grant"
                    :description="TeamRoles::assignable()[$role]['description'] ?? null">
                    <flux:select.option value="vendor">Director / owner (runs the whole workspace)</flux:select.option>
                    @foreach (TeamRoles::assignable() as $value => $teamRole)
                        <flux:select.option value="{{ $value }}">{{ $teamRole['label'] }} (team member of a workspace)</flux:select.option>
                    @endforeach
                    <flux:select.option value="client">Client / tenant (customer portal)</flux:select.option>
                    <flux:select.option value="super_admin">Platform admin</flux:select.option>
                </flux:select>
                <flux:error name="role" />

                @if ($role === 'vendor')
                    <flux:radio.group wire:model.live="workspaceMode" label="Workspace">
                        <flux:radio value="new"
                            :label="'Create a new trial workspace: ' . ($this->approving->organization ?: $this->approving->name . '\'s workspace')" />
                        <flux:radio value="existing" label="Add as a user of an existing workspace" />
                    </flux:radio.group>

                    @if ($workspaceMode === 'new')
                        <flux:select wire:model="businessType" label="Business type"
                            description="Landlords bill tenants per house number; water companies bill metered water.">
                            @foreach (BusinessType::cases() as $type)
                                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    @if ($workspaceMode === 'existing')
                        <flux:select wire:model.live="vendorId" label="Workspace" placeholder="Choose…">
                            @foreach ($this->vendors as $v)
                                <flux:select.option value="{{ $v->id }}">{{ $v->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    <flux:error name="vendorId" />
                @elseif (array_key_exists($role, TeamRoles::assignable()))
                    <flux:select wire:model.live="vendorId" label="Workspace" placeholder="Choose…">
                        @foreach ($this->vendors as $v)
                            <flux:select.option value="{{ $v->id }}">{{ $v->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="vendorId" />
                @elseif ($role === 'client')
                    <flux:text class="text-sm">
                        They said their provider is <strong>{{ $this->approving->provider_name ?: '—' }}</strong>
                        @if ($this->approving->account_reference)
                            and account/meter <strong>{{ $this->approving->account_reference }}</strong>
                        @endif.
                    </flux:text>

                    <flux:select wire:model.live="vendorId" label="Water company or landlord" placeholder="Choose…">
                        @foreach ($this->vendors as $v)
                            <flux:select.option value="{{ $v->id }}">{{ $v->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="clientId" label="Customer account to link" placeholder="Choose…"
                        :disabled="! $vendorId">
                        @foreach ($this->clients as $c)
                            <flux:select.option value="{{ $c->id }}">
                                {{ $c->account_no }} · {{ $c->name }}{{ $c->email ? ' · ' . $c->email : '' }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="clientId" />
                    @if ($vendorId && $this->clients->isEmpty())
                        <flux:text class="text-xs text-amber-600">
                            This provider has no customer accounts without a login. The vendor must add the customer first.
                        </flux:text>
                    @endif
                @else
                    <flux:callout variant="warning" icon="shield-exclamation">
                        Platform admins can see and manage every vendor. Only grant this to your own staff.
                    </flux:callout>
                @endif

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Approve & send link</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{-- Reject modal --}}
    <flux:modal name="reject-request" class="w-full max-w-md">
        <form wire:submit="reject" class="space-y-5">
            <div>
                <flux:heading size="lg">Reject request?</flux:heading>
                <flux:subheading>If a link was already sent, it stops working.</flux:subheading>
            </div>

            <flux:textarea wire:model="rejectionReason" label="Reason (optional)" rows="3" />
            <flux:checkbox wire:model="notifyRejection" label="Email the person to let them know" />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Reject</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
