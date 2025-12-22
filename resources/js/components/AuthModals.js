/**
 * Auth Modals Handler
 * Handles AJAX form submissions for login, register, and forgot password modals
 */

document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    /**
     * Display validation errors in the form
     */
    function displayErrors(formId, errors) {
        // Clear previous errors
        const form = document.getElementById(formId);
        if (!form) return;

        // Clear all error containers
        form.querySelectorAll('[id$="-errors"]').forEach(el => {
            el.innerHTML = '';
            el.classList.add('hidden');
        });

        // Clear status message
        const statusEl = form.querySelector('[id$="-status-message"]');
        if (statusEl) {
            statusEl.classList.add('hidden');
            statusEl.innerHTML = '';
        }

        // Display new errors
        if (errors) {
            // Extract prefix from formId (e.g., 'login-form' -> 'login')
            const prefix = formId.replace('-form', '');
            
            Object.keys(errors).forEach(field => {
                const errorContainer = form.querySelector(`#${prefix}-${field}-errors`);
                if (errorContainer) {
                    errorContainer.innerHTML = errors[field].map(msg => 
                        `<ul class="text-sm text-red-600 dark:text-red-400 space-y-1">
                            <li>${msg}</li>
                        </ul>`
                    ).join('');
                    errorContainer.classList.remove('hidden');
                }
            });
        }
    }

    /**
     * Display success/error message
     */
    function displayStatusMessage(formId, message, isSuccess = true) {
        // Extract prefix from formId (e.g., 'login-form' -> 'login')
        const prefix = formId.replace('-form', '');
        const statusEl = document.getElementById(`${prefix}-status-message`);
        if (!statusEl) return;

        statusEl.className = `mb-4 font-medium text-sm ${isSuccess ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`;
        statusEl.textContent = message;
        statusEl.classList.remove('hidden');
    }

    /**
     * Handle form submission
     */
    function handleFormSubmit(form, formId, successCallback) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton?.textContent;

            // Disable submit button
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formData,
                });

                const data = await response.json();

                if (data.success) {
                    // Clear errors
                    displayErrors(formId, null);
                    
                    // Show success message
                    displayStatusMessage(formId, data.message || 'Success!', true);

                    // Call success callback
                    if (successCallback) {
                        successCallback(data);
                    }
                } else {
                    // Display errors
                    displayErrors(formId, data.errors || {});
                    
                    if (data.message) {
                        displayStatusMessage(formId, data.message, false);
                    }
                }
            } catch (error) {
                console.error('Form submission error:', error);
                displayStatusMessage(formId, 'An error occurred. Please try again.', false);
            } finally {
                // Re-enable submit button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            }
        });
    }

    // Initialize login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        handleFormSubmit(loginForm, 'login-form', (data) => {
            // Close modal and redirect
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'login-modal' }));
            setTimeout(() => {
                window.location.href = data.redirect || '/';
            }, 500);
        });
    }

    // Initialize register form
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        handleFormSubmit(registerForm, 'register-form', (data) => {
            // Close modal and redirect
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'register-modal' }));
            setTimeout(() => {
                window.location.href = data.redirect || '/';
            }, 500);
        });
    }

    // Initialize forgot password form
    const forgotPasswordForm = document.getElementById('forgot-password-form');
    if (forgotPasswordForm) {
        handleFormSubmit(forgotPasswordForm, 'forgot-password-form', (data) => {
            // Show success message, form will stay open
            // User can close manually or we can auto-close after a delay
            setTimeout(() => {
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'forgot-password-modal' }));
            }, 2000);
        });
    }

    // Reset form errors when modal is opened
    window.addEventListener('open-modal', (e) => {
        const modalName = e.detail;
        let formId = null;
        
        if (modalName === 'login-modal') {
            formId = 'login-form';
        } else if (modalName === 'register-modal') {
            formId = 'register-form';
        } else if (modalName === 'forgot-password-modal') {
            formId = 'forgot-password-form';
        }

        if (formId) {
            // Clear errors when opening modal
            setTimeout(() => {
                const form = document.getElementById(formId);
                if (form) {
                    displayErrors(formId, null);
                    const statusEl = form.querySelector('[id$="-status-message"]');
                    if (statusEl) {
                        statusEl.classList.add('hidden');
                        statusEl.innerHTML = '';
                    }
                }
            }, 100);
        }
    });
});

