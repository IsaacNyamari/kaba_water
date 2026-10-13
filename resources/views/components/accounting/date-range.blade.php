{{-- From/To filters bound with wire:model.live (CLAUDE.md UI rules). --}}
@props(['from' => 'from', 'to' => 'to'])

<div class="flex items-end gap-2">
    <flux:input type="date" wire:model.live="{{ $from }}" label="From" class="w-40" />
    <flux:input type="date" wire:model.live="{{ $to }}" label="To" class="w-40" />
</div>
