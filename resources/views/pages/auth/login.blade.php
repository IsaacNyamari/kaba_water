<x-layouts::auth :title="__('Log in')">
    @php
        // Re-open the phone tab after a failed phone sign-in.
        $startOnPhone = old('meter') !== null || $errors->has('phone') || $errors->has('meter');
    @endphp

    <div class="flex flex-col gap-6" x-data="{ method: '{{ $startOnPhone ? 'phone' : 'email' }}' }">
        <x-auth-header :title="__('Sign in')" :description="__('Use your email, or your phone and meter number if you are a customer without email.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        {{-- Sign-in method --}}
        <div role="tablist" aria-label="{{ __('Sign-in method') }}"
            class="grid grid-cols-2 gap-1 rounded-xl bg-brand-900/5 p-1 ring-1 ring-brand-900/10 dark:bg-white/10 dark:ring-white/15">
            @foreach (['email' => __('Email'), 'phone' => __('Phone & meter')] as $value => $label)
                <button type="button" role="tab" x-on:click="method = '{{ $value }}'"
                    x-bind:aria-selected="(method === '{{ $value }}').toString()"
                    x-bind:class="method === '{{ $value }}'
                        ? 'bg-brand-500 text-white shadow-sm dark:bg-brand-400 dark:text-brand-950'
                        : 'text-brand-900/70 hover:bg-brand-900/5 hover:text-brand-950 dark:text-white/80 dark:hover:bg-white/10 dark:hover:text-white'"
                    class="rounded-lg px-3 py-2 text-sm font-semibold transition">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Email + password --}}
        <div x-show="method === 'email'" @if ($startOnPhone) x-cloak @endif class="flex flex-col gap-6">
            <x-passkey-verify />

            <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
                @csrf

                <!-- Email Address -->
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                <!-- Password -->
                <div class="relative">
                    <flux:input
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        :placeholder="__('Password')"
                        viewable
                    />

                    @if (Route::has('password.request'))
                        <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                            {{ __('Forgot your password?') }}
                        </flux:link>
                    @endif
                </div>

                <!-- Remember Me -->
                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                        {{ __('Log in') }}
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Phone + meter + PIN (customers without email) --}}
        <div x-show="method === 'phone'" @unless ($startOnPhone) x-cloak @endunless>
            <form method="POST" action="{{ route('login.phone') }}" class="flex flex-col gap-6">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input
                        name="phone"
                        :label="__('Phone number')"
                        :value="old('phone')"
                        type="tel"
                        required
                        autocomplete="tel"
                        placeholder="0712 345 678"
                    />

                    <flux:input
                        name="meter"
                        :label="__('Meter number')"
                        :value="old('meter')"
                        required
                        autocomplete="off"
                        :placeholder="__('As printed on your meter')"
                    />
                </div>

                <flux:input
                    name="password"
                    :label="__('PIN or password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :description="__('Given to you by your water provider. Forgotten it? Ask them to set a new one.')"
                    viewable
                />

                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

                <flux:button variant="primary" type="submit" class="w-full" data-test="phone-login-button">
                    {{ __('Log in') }}
                </flux:button>
            </form>
        </div>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('access.request')" wire:navigate>{{ __('Request access') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
