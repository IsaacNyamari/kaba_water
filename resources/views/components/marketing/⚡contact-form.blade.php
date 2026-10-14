<?php

use App\Actions\Marketing\SubmitContactMessage;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/*
 * Public contact form. Submits over Livewire so sending a message never
 * triggers a full page load.
 */
new class extends Component {
    public string $name = '';
    public string $email = '';
    public string $company = '';
    public string $message = '';

    /** Honeypot: hidden from people, so only bots fill it in. */
    public string $website = '';

    public bool $sent = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'company' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['prohibited'],
        ];
    }

    protected function messages(): array
    {
        return [
            'message.min' => 'Tell us a little more so we can help — at least 10 characters.',
            'website.prohibited' => 'Your message could not be sent.',
        ];
    }

    public function send(SubmitContactMessage $submit): void
    {
        $key = 'contact-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('message', 'Too many messages in a short time. Please try again in a minute.');

            return;
        }

        $data = $this->validate();
        RateLimiter::hit($key, 60);

        $submit->handle($data);

        $this->reset(['name', 'email', 'company', 'message', 'website']);
        $this->sent = true;

        Flux::toast(variant: 'success', heading: 'Message sent', text: 'Thanks — we will be in touch within one working day.');
    }
};
?>

<div id="contact-form" class="mk-card scroll-mt-24 p-6 sm:p-10">
    @if ($sent)
        <div role="status" class="mb-8 flex gap-3 rounded-2xl border border-aqua/40 bg-aqua-mist p-4 text-sm text-teal dark:bg-aqua/10 dark:text-light-aqua">
            <flux:icon.check-circle variant="mini" class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">Message sent</p>
                <p class="mt-0.5">Thanks — we will be in touch within one working day. A confirmation is on its way to your inbox.</p>
            </div>
        </div>
    @endif

    <h2 class="text-2xl font-semibold tracking-tight text-navy dark:text-white">Send us a message</h2>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Fields marked with * are required.</p>

    <form wire:submit="send" class="mt-8 grid gap-6 sm:grid-cols-2">
        <flux:input wire:model="name" label="Full name *" autocomplete="name" required />
        <flux:input wire:model="email" type="email" label="Email *" autocomplete="email" required />

        <div class="sm:col-span-2">
            <flux:input wire:model="company" label="Company or estate" autocomplete="organization" />
        </div>

        <div class="sm:col-span-2">
            <flux:textarea wire:model="message" label="How can we help? *" rows="6" required
                placeholder="e.g. We bill 320 meters across two estates and want to move off spreadsheets." />
        </div>

        {{-- Honeypot: people never see or reach this field. --}}
        <div class="hidden" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" wire:model="website" tabindex="-1" autocomplete="off" />
        </div>

        @error('website')
            <p class="text-sm font-medium text-rose-600 sm:col-span-2">{{ $message }}</p>
        @enderror

        <div class="flex flex-col gap-4 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                We use your details only to reply. See our
                <a href="{{ route('marketing.privacy') }}" wire:navigate class="font-medium text-teal underline underline-offset-4 dark:text-light-aqua">privacy policy</a>.
            </p>
            <button type="submit" wire:loading.attr="disabled" class="btn-mk btn-mk-primary shrink-0 disabled:opacity-60">
                <span wire:loading.remove wire:target="send">Send message</span>
                <span wire:loading wire:target="send">Sending…</span>
                <flux:icon.paper-airplane variant="micro" class="size-4" />
            </button>
        </div>
    </form>
</div>
