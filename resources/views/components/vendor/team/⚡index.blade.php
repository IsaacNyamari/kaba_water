<?php

use App\Models\User;
use App\Notifications\TeamInvitation;
use App\Support\Notifier;
use App\Support\TeamRoles;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * The vendor's team: office staff, field technicians, meter readers and
 * accountants. New members are emailed a link to set their own password
 * (or the owner sets one). Only the workspace owner role manages the team.
 */
new #[Layout('layouts.vendor'), Title('Team')] class extends Component {
    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $role = 'technician';
    public string $password = '';

    public ?int $removingId = null;

    public function mount(): void
    {
        $this->authorize('vendor.team.manage');
    }

    #[Computed]
    public function members()
    {
        return User::query()
            ->where('vendor_id', auth()->user()->vendor_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['vendor', ...TeamRoles::names()]))
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function openJobs(): array
    {
        return \App\Models\WorkOrder::query()->open()->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as c')->groupBy('assigned_to')->pluck('c', 'assigned_to')->all();
    }

    public function startAdd(): void
    {
        $this->authorize('vendor.team.manage');
        $this->reset('editingId', 'name', 'email', 'password');
        $this->role = 'technician';
        $this->resetValidation();
        Flux::modal('member')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize('vendor.team.manage');
        $member = $this->findMember($id);

        $this->editingId = $member->id;
        $this->name = $member->name;
        $this->email = $member->email ?? '';
        $this->role = $member->roles->pluck('name')->first(fn ($r) => in_array($r, TeamRoles::names(), true)) ?? 'vendor_staff';
        $this->password = '';
        $this->resetValidation();
        Flux::modal('member')->show();
    }

    public function save(): void
    {
        $this->authorize('vendor.team.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::in(array_keys(TeamRoles::assignable()))],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $vendor = auth()->user()->vendor;
        $roleLabel = TeamRoles::label($this->role);

        if ($this->editingId) {
            $member = $this->findMember($this->editingId);
            abort_if($member->hasRole('vendor'), 403, 'The owner role cannot be changed here.');

            DB::transaction(function () use ($member) {
                $member->update(['name' => $this->name, 'email' => $this->email] + ($this->password !== '' ? ['password' => $this->password] : []));
                $member->syncRoles([$this->role]);
            });

            Notifier::send($member, 'Your role was updated', "You are now {$roleLabel} in {$vendor->name}.", $member->homeRoute(), 'user-circle', 'team');
            Flux::toast(variant: 'success', heading: 'Member updated', text: "{$member->name} · {$roleLabel}");
        } else {
            $member = DB::transaction(function () use ($vendor) {
                $member = User::create([
                    'vendor_id' => $vendor->id,
                    'name' => $this->name,
                    'email' => $this->email,
                    // Without a password from the owner, a random one; the invite email lets them set their own.
                    'password' => $this->password !== '' ? $this->password : Str::password(32),
                ]);
                $member->forceFill(['email_verified_at' => now()])->save();
                $member->assignRole($this->role);

                return $member;
            });

            if ($this->password === '') {
                $url = route('password.reset', ['token' => Password::createToken($member), 'email' => $member->email]);
                rescue(fn () => $member->notify(new TeamInvitation($vendor->name, $roleLabel, auth()->user()->name, $url)));
            }

            Flux::toast(variant: 'success', heading: 'Member added', text: $this->password === ''
                ? "{$member->name} was emailed a link to set their password."
                : "{$member->name} can sign in with the password you set.");
        }

        Flux::modal('member')->close();
        $this->reset('editingId', 'name', 'email', 'password');
        unset($this->members);
    }

    public function confirmRemove(int $id): void
    {
        $this->authorize('vendor.team.manage');
        $this->removingId = $this->findMember($id)->id;
        Flux::modal('remove-member')->show();
    }

    public function remove(): void
    {
        $this->authorize('vendor.team.manage');
        $member = $this->findMember($this->removingId);
        abort_if($member->hasRole('vendor') || $member->id === auth()->id(), 403, 'The owner cannot be removed.');

        // Their open jobs go back to the unassigned pile.
        \App\Models\WorkOrder::query()->open()->where('assigned_to', $member->id)->update(['assigned_to' => null]);
        $member->syncRoles([]);
        $member->delete();

        Flux::modal('remove-member')->close();
        Flux::toast(variant: 'success', heading: 'Member removed', text: "{$member->name} can no longer sign in. Their open jobs are unassigned.");
        $this->removingId = null;
        unset($this->members, $this->openJobs);
    }

    protected function findMember(?int $id): User
    {
        return User::query()->where('vendor_id', auth()->user()->vendor_id)->with('roles')->findOrFail($id);
    }
};
?>

<div class="p-4 lg:p-6 space-y-6">
    <div class="stat-card tone-brand flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-sm">
                <flux:icon.user-group variant="mini" class="size-5" />
            </span>
            <div>
                <flux:heading size="lg">Team</flux:heading>
                <flux:text class="text-sm">The people who run your water business with you. Each sees only what their role needs.</flux:text>
            </div>
        </div>
        <flux:button variant="primary" icon="user-plus" wire:click="startAdd">Add member</flux:button>
    </div>

    {{-- Roles at a glance --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (TeamRoles::assignable() as $key => $info)
            @php $count = $this->members->filter(fn ($m) => $m->hasRole($key))->count(); @endphp
            <div wire:key="role-{{ $key }}" class="stat-card tone-{{ ['vendor_staff' => 'brand', 'technician' => 'amber', 'meter_reader' => 'sky', 'accountant' => 'violet', 'project_manager' => 'emerald', 'site_engineer' => 'amber', 'storekeeper' => 'sky', 'procurement_officer' => 'violet'][$key] ?? 'brand' }} p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $info['label'] }}</span>
                    <flux:icon :name="$info['icon']" variant="mini" class="size-5 text-zinc-400" />
                </div>
                <div class="mt-2 font-mono text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $count }}</div>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $info['description'] }}</p>
            </div>
        @endforeach
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Member</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column align="end">Open jobs</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->members as $member)
                @php
                    $roleName = $member->roles->pluck('name')->first(fn ($r) => in_array($r, ['vendor', ...TeamRoles::names()], true));
                    $isOwner = $roleName === 'vendor';
                @endphp
                <flux:table.row :key="$member->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :initials="$member->initials()" />
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $member->name }} @if ($member->id === auth()->id())<span class="text-xs text-zinc-500">(you)</span>@endif</div>
                                <div class="text-xs text-zinc-500">{{ $member->email }}</div>
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$isOwner ? 'zinc' : ['vendor_staff' => 'teal', 'technician' => 'amber', 'meter_reader' => 'sky', 'accountant' => 'violet', 'project_manager' => 'emerald', 'site_engineer' => 'orange', 'storekeeper' => 'cyan', 'procurement_officer' => 'indigo'][$roleName] ?? 'zinc'">
                            {{ TeamRoles::label($roleName) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">{{ $this->openJobs[$member->id] ?? 0 }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @unless ($isOwner)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $member->id }})" />
                            <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600" wire:click="confirmRemove({{ $member->id }})" />
                        @endunless
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- Add / edit member --}}
    <flux:modal name="member" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit member' : 'Add a team member' }}</flux:heading>
                <flux:subheading>{{ $editingId ? 'Change their details or role.' : 'They sign in with this email.' }}</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Full name" />
                <flux:input wire:model="email" type="email" label="Email" />
            </div>

            <flux:radio.group wire:model="role" label="Role">
                @foreach (TeamRoles::assignable() as $key => $info)
                    <flux:radio value="{{ $key }}" :label="$info['label']" :description="$info['description']" />
                @endforeach
            </flux:radio.group>

            <flux:input wire:model="password" type="password" viewable autocomplete="new-password"
                :label="$editingId ? 'New password (optional)' : 'Password (optional)'"
                :description="$editingId ? 'Leave blank to keep their current password.' : 'Leave blank to email them a link to set their own.'" />

            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Save' : 'Add member' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Remove member --}}
    <flux:modal name="remove-member" class="w-full max-w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Remove this member?</flux:heading>
                <flux:subheading>They will no longer be able to sign in. Any open jobs assigned to them go back to unassigned.</flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="remove">Remove</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
