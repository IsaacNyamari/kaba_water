<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.vendor'), Title('Expense Categories')] class extends Component {
    // Inline create/edit form state
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $color = 'blue';

    public const COLORS = [
        'blue' => 'Blue',
        'emerald' => 'Emerald',
        'violet' => 'Violet',
        'amber' => 'Amber',
        'orange' => 'Orange',
        'rose' => 'Rose',
        'red' => 'Red',
        'yellow' => 'Yellow',
        'zinc' => 'Zinc',
        'slate' => 'Slate',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        if (! ExpenseCategory::exists()) {
            \App\Support\DefaultExpenseCategories::seedFor(auth()->user()->vendor_id);
        }
    }

    public function openCreate(): void
    {
        $this->authorize('create', ExpenseCategory::class);

        $this->reset(['editingId', 'name']);
        $this->color = 'blue';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $this->authorize('update', $category);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->color = $category->color;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'color']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $vendorId = auth()->user()->vendor_id;

        $this->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                function ($attribute, $value, $fail) use ($vendorId) {
                    $slug = Str::slug($value);
                    $exists = ExpenseCategory::withoutGlobalScope('vendor')->where('vendor_id', $vendorId)->where('slug', $slug)->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))->exists();
                    if ($exists) {
                        $fail('You already have a category with that name.');
                    }
                },
            ],
            'color' => ['required', 'in:' . implode(',', array_keys(self::COLORS))],
        ]);

        $payload = [
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'color' => $this->color,
        ];

        if ($this->editingId) {
            $category = ExpenseCategory::findOrFail($this->editingId);
            $this->authorize('update', $category);
            $category->update($payload);
            \Flux\Flux::toast(variant: 'success', text: 'Category updated.');
        } else {
            $this->authorize('create', ExpenseCategory::class);
            ExpenseCategory::create($payload);
            \Flux\Flux::toast(variant: 'success', text: 'Category created.');
        }

        $this->closeForm();
    }

    public function delete(int $id): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $this->authorize('delete', $category);

        if ($category->expenses()->exists()) {
            \Flux\Flux::toast(variant: 'danger', text: "Cannot delete '{$category->name}' — it has expenses linked to it.");
            return;
        }

        $category->delete();
        \Flux\Flux::toast(variant: 'success', text: 'Category deleted.');
    }

    public function with(): array
    {
        return [
            'categories' => ExpenseCategory::query()->withCount('expenses')->orderBy('name')->get(),
        ];
    }
};
?>

<div class="p-4 lg:p-6 max-w-3xl space-y-4" x-data="{ confirmDelete: null }">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Expense Categories</h1>
            <p class="text-sm text-zinc-500 mt-1">
                Group your expenses for reporting. Every expense must belong to one category.
            </p>
        </div>
        <flux:button wire:click="openCreate" variant="primary" icon="plus">
            New category
        </flux:button>
    </div>

    {{-- Categories list --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Category</flux:table.column>
                <flux:table.column>Slug</flux:table.column>
                <flux:table.column align="end">Expenses</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($categories as $cat)
                    <flux:table.row :key="$cat->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-3 h-3 rounded-full shrink-0
                                    @switch($cat->color)
                                        @case('blue')     bg-blue-500     @break
                                        @case('emerald')  bg-emerald-500  @break
                                        @case('violet')   bg-violet-500   @break
                                        @case('amber')    bg-amber-500    @break
                                        @case('orange')   bg-orange-500   @break
                                        @case('rose')     bg-rose-500     @break
                                        @case('red')      bg-red-500      @break
                                        @case('yellow')   bg-yellow-500   @break
                                        @case('zinc')     bg-zinc-500     @break
                                        @case('slate')    bg-slate-500    @break
                                        @default          bg-zinc-500
                                    @endswitch
                                "></span>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $cat->name }}
                                </span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="font-mono text-xs text-zinc-500">
                            {{ $cat->slug }}
                        </flux:table.cell>

                        <flux:table.cell align="end" class="font-mono">
                            {{ number_format($cat->expenses_count) }}
                        </flux:table.cell>

                        <flux:table.cell class="py-0">
                            @if ($cat->is_system)
                                <flux:badge color="zinc" size="sm">Default</flux:badge>
                            @else
                                <flux:badge color="blue" size="sm">Custom</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <div class="inline-flex gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil-square"
                                    wire:click="openEdit({{ $cat->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" class="!text-rose-600"
                                    x-on:click="confirmDelete = {{ $cat->id }}" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-12 text-zinc-500">
                            No categories yet — create one to start recording expenses.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Create / Edit modal --}}
    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Edit category' : 'New category' }}
                </flux:heading>
                <flux:subheading>
                    Give it a clear name — you'll see it on every expense dropdown and report.
                </flux:subheading>
            </div>

            <flux:input wire:model="name" label="Name" placeholder="e.g. Water Quality Tests" autofocus />

            <flux:select wire:model="color" label="Colour">
                @foreach (self::COLORS as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- Colour preview --}}
            <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                <span>Preview:</span>
                <flux:badge :color="$color" size="sm">{{ $name ?: 'Category' }}</flux:badge>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="closeForm">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ $editingId ? 'Save changes' : 'Create category' }}</span>
                    <span wire:loading>Saving…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation --}}
    <flux:modal name="delete-category" x-model="confirmDelete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete category?</flux:heading>
                <flux:subheading>
                    Categories with expenses cannot be deleted. Reassign the expenses first.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" x-on:click="confirmDelete = null">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete(confirmDelete)" x-on:click="confirmDelete = null">
                    Delete
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
