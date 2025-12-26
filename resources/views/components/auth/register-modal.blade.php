<x-modal name="register-modal" maxWidth="md" focusable>
    <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            {{ __('Register') }}
        </h2>

        <div id="register-status-message" class="mb-4 hidden"></div>

        <form id="register-form" method="POST" action="{{ route('register') }}">
            @csrf

            <!-- Name -->
            <div>
                <x-input-label for="register-name" :value="__('Name')" />
                <x-text-input id="register-name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <div id="register-name-errors" class="mt-2"></div>
            </div>

            <!-- Email Address -->
            <div class="mt-4">
                <x-input-label for="register-email" :value="__('Email')" />
                <x-text-input id="register-email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                <div id="register-email-errors" class="mt-2"></div>
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-input-label for="register-password" :value="__('Password')" />
                <x-text-input id="register-password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />
                <div id="register-password-errors" class="mt-2"></div>
            </div>

            <!-- Confirm Password -->
            <div class="mt-4">
                <x-input-label for="register-password-confirmation" :value="__('Confirm Password')" />
                <x-text-input id="register-password-confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
                <div id="register-password-confirmation-errors" class="mt-2"></div>
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-primary-button type="submit">
                    {{ __('Register') }}
                </x-primary-button>
            </div>
        </form>

        <div class="mt-4 text-center">
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Already registered?') }}</span>
            <button type="button" @click.prevent="$dispatch('close-modal', 'register-modal'); $dispatch('open-modal', 'login-modal');" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 ml-1">
                {{ __('Log in') }}
            </button>
        </div>
    </div>
</x-modal>





