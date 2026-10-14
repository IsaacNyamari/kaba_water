<?php

use App\Livewire\Concerns\InteractsWithClient;
use App\Models\Meter;
use App\Models\Reading;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.portal'), Title('Submit reading')] class extends Component {
    use InteractsWithClient, WithFileUploads;

    public string $meter_id = '';
    public string $reading = '';
    public string $read_at = '';
    public $photo = null;

    public function mount(): void
    {
        $client = $this->requireClient();

        $this->read_at = now()->format('Y-m-d\TH:i');

        $meters = Meter::where('client_id', $client->id)
            ->where('status', 'active')
            ->get(['id']);

        if ($meters->count() === 1) {
            $this->meter_id = (string) $meters->first()->id;
        }
    }

    #[Computed]
    public function client()
    {
        return $this->requireClient();
    }

    #[Computed]
    public function meters()
    {
        return Meter::where('client_id', $this->client->id)
            ->where('status', 'active')
            ->orderBy('serial')
            ->get(['id', 'serial', 'location']);
    }

    #[Computed]
    public function latestApproved(): ?Reading
    {
        if (!$this->meter_id) {
            return null;
        }

        return Reading::where('meter_id', $this->meter_id)->where('status', 'approved')->latest('read_at')->first();
    }

    protected function rules(): array
    {
        $clientId = $this->client->id;

        return [
            'meter_id' => ['required', "exists:meters,id,client_id,{$clientId},status,active"],
            'reading' => ['required', 'integer', 'min:0'],
            'read_at' => ['required', 'date', 'before_or_equal:now'],
            'photo' => ['nullable', 'image', 'max:4096', 'mimes:jpeg,png,jpg,webp'],
        ];
    }

    public function save(): void
    {
        // Dials show leading zeros ("00156"); store and compare whole numbers.
        $this->reading = \App\Support\MeterValue::normalize($this->reading);

        $this->validate();

        if ($latest = $this->latestApproved) {
            if ((int) $this->reading < (int) $latest->reading) {
                $this->addError('reading', "The new reading ({$this->reading}) cannot be lower than the last " . "approved reading ({$latest->reading}).");
                return;
            }
        }

        $photoPath = null;
        if ($this->photo) {
            $photoPath = $this->photo->store('meter-readings', 'public');
        }

        // Model boot hook auto-sets status = 'pending' for clients,
        // and computes previous_reading + consumption.
    
        Reading::create([
            'vendor_id' => $this->client->vendor_id,
            'meter_id' => $this->meter_id,
            'reading' => $this->reading,
            'source' => 'manual',
            'photo_path' => $photoPath,
            'read_at' => $this->read_at,
            'created_by' => auth()->id(),
        ]);

        session()->flash('status', 'Reading submitted. ' . $this->client->vendor->name . ' will review it shortly.');

        $this->redirectRoute('portal.readings.index', navigate: true);
    }
};
?>

<div class="p-4 lg:p-6 max-w-2xl space-y-6">

    <div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Submit a reading</h1>
        <p class="text-sm text-zinc-500 mt-1">
            Enter the reading from your meter. {{ $this->client->vendor->name }} will verify it
            before it's included in your bill.
        </p>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Meter --}}
        <flux:card class="space-y-5">
            <flux:heading size="sm">Meter</flux:heading>

            @if ($this->meters->count() === 1)
                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
                    <div class="font-mono text-sm text-zinc-700 dark:text-zinc-300">
                        {{ $this->meters->first()->serial }}
                    </div>
                    @if ($this->meters->first()->location)
                        <div class="text-xs text-zinc-500">{{ $this->meters->first()->location }}</div>
                    @endif
                </div>
            @else
                <flux:select wire:model.live="meter_id" label="Which meter?" searchable placeholder="Choose Meter">
                    @forelse ($this->meters as $m)
                        <flux:select.option value="{{ $m->id }}">
                            {{ $m->serial }}@if ($m->location)
                                · {{ $m->location }}
                            @endif
                        </flux:select.option>
                    @empty
                        <flux:select.option value="" disabled>
                            No active meters on your account
                        </flux:select.option>
                    @endforelse
                </flux:select>
            @endif

            @if ($this->latestApproved)
                <div class="rounded-lg bg-brand-50 dark:bg-brand-950/30 p-4 text-sm">
                    <div class="text-brand-700 dark:text-brand-300">
                        Last approved reading:
                        <strong class="font-mono">{{ number_format($this->latestApproved->reading) }} m³</strong>
                    </div>
                    <div class="text-xs text-brand-600 dark:text-brand-400 mt-1">
                        Read {{ $this->latestApproved->read_at->format('d M Y') }}
                    </div>
                </div>
            @endif
        </flux:card>

        {{-- Reading value --}}
        <flux:card class="space-y-5">
            <flux:heading size="sm">Reading value</flux:heading>

            <flux:input wire:model="reading" label="Digits shown on the meter"
                description="Whole cubic meters only — ignore the red digits and small dials." placeholder="e.g. 012806"
                class="font-mono text-lg" inputmode="numeric" pattern="[0-9]*" />

            <flux:input type="datetime-local" wire:model="read_at" label="When did you take this reading?" />
        </flux:card>

        {{-- Photo --}}
        <flux:card class="space-y-5">
            <div>
                <flux:heading size="sm">Meter photo (optional)</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    A photo helps your vendor verify the reading faster.
                </flux:text>
            </div>

            @if (!$photo)
                <label
                    class="block border-2 border-dashed border-zinc-300 dark:border-zinc-600
                              hover:border-brand-400 rounded-xl p-6 text-center cursor-pointer transition">
                    <input type="file" wire:model="photo" accept="image/*" class="hidden">
                    <flux:icon name="camera" class="w-8 h-8 mx-auto text-zinc-400" />
                    <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Click to upload a photo
                    </div>
                    <div wire:loading wire:target="photo" class="mt-1 text-xs text-brand-600">
                        Uploading…
                    </div>
                </label>
            @else
                <div class="grid gap-4 sm:grid-cols-[180px_1fr] items-start">
                    <img src="{{ $photo->temporaryUrl() }}"
                        class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" type="button"
                            wire:click="$set('photo', null)" class="!text-rose-600">
                            Remove photo
                        </flux:button>
                    </div>
                </div>
            @endif
        </flux:card>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <flux:button variant="ghost" href="{{ route('portal.readings.index') }}">Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled"
                wire:target="save">
                <span wire:loading.remove wire:target="save">Submit for review</span>
                <span wire:loading wire:target="save">Submitting…</span>
            </flux:button>
        </div>
    </form>
</div>
