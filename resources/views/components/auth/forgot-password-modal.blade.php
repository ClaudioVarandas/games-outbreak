<x-modal name="forgot-password-modal" maxWidth="md" focusable>
    <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            {{ __('Forgot Password') }}
        </h2>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        <div id="forgot-password-status-message" class="mb-4 hidden"></div>

        <form id="forgot-password-form" method="POST" action="{{ route('password.email') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="forgot-password-email" :value="__('Email')" />
                <x-text-input id="forgot-password-email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
                <div id="forgot-password-email-errors" class="mt-2"></div>
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-primary-button type="submit">
                    {{ __('Email Password Reset Link') }}
                </x-primary-button>
            </div>
        </form>

        <div class="mt-4 text-center">
            <button type="button" @click.prevent="$dispatch('close-modal', 'forgot-password-modal'); $dispatch('open-modal', 'login-modal');" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                {{ __('Back to login') }}
            </button>
        </div>
    </div>
</x-modal>

