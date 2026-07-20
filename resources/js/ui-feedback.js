import Alpine from 'alpinejs';

// Global toast + confirm dialog stores. Rendered once in layouts/app.blade.php
// via <x-toast-container /> and <x-confirm-dialog />.

document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
        items: [],
        counter: 0,

        push(message, type = 'success', duration = 3500) {
            const id = ++this.counter;
            this.items.push({ id, message, type });
            setTimeout(() => this.dismiss(id), duration);
        },

        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    });

    Alpine.store('confirmDialog', {
        open: false,
        title: 'Are you sure?',
        message: '',
        confirmLabel: 'Confirm',
        danger: false,
        _resolve: null,

        ask(message, { title = 'Are you sure?', confirmLabel = 'Confirm', danger = false } = {}) {
            this.message = message;
            this.title = title;
            this.confirmLabel = confirmLabel;
            this.danger = danger;
            this.open = true;

            return new Promise((resolve) => {
                this._resolve = resolve;
            });
        },

        settle(result) {
            this.open = false;
            if (this._resolve) {
                this._resolve(result);
                this._resolve = null;
            }
        },
    });
});

window.toast = (message, type = 'success') => Alpine.store('toasts').push(message, type);
window.confirmDialog = (message, options = {}) => Alpine.store('confirmDialog').ask(message, options);

// Forms with data-confirm="message" get a styled confirmation instead of the
// native confirm(). The guard attribute prevents re-triggering on the
// programmatic re-submit.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === 'true') {
        return;
    }

    event.preventDefault();

    window.confirmDialog(form.dataset.confirm, { danger: true, confirmLabel: 'Delete' }).then((confirmed) => {
        if (confirmed) {
            form.dataset.confirmed = 'true';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    });
});
