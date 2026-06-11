import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog'];

    open() {
        this.dialogTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        this.dialogTarget.querySelector('textarea, input:not([type=hidden]), button')?.focus();
    }

    close() {
        this.dialogTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    closeOnEsc(event) {
        if (event.key === 'Escape' && !this.dialogTarget.classList.contains('hidden')) {
            this.close();
        }
    }
}
