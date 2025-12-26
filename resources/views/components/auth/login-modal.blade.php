<x-modal name="login-modal" maxWidth="md" focusable>
    <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            {{ __('Log in') }}
        </h2>

        <div id="login-status-message" class="mb-4 hidden"></div>

        <form id="login-form" method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="login-email" :value="__('Email')" />
                <x-text-input id="login-email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <div id="login-email-errors" class="mt-2"></div>
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-input-label for="login-password" :value="__('Password')" />
                <x-text-input id="login-password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />
                <div id="login-password-errors" class="mt-2"></div>
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                <button type="button" @click.prevent="$dispatch('close-modal', 'login-modal'); $dispatch('open-modal', 'forgot-password-modal');" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                    {{ __('Forgot your password?') }}
                </button>

                <x-primary-button type="submit" class="ms-3">
                    {{ __('Log in') }}
                </x-primary-button>
            </div>
        </form>

        <div class="mt-4 text-center">
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Don\'t have an account?') }}</span>
            <button type="button" @click.prevent="$dispatch('close-modal', 'login-modal'); $dispatch('open-modal', 'register-modal');" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 ml-1">
                {{ __('Register') }}
            </button>
        </div>
    </div>
</x-modal>





