import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'button'];
    static classes = ['copied'];

    copy() {
        const text = this.sourceTarget.value || this.sourceTarget.textContent;

        navigator.clipboard.writeText(text).then(() => {
            this.buttonTarget.classList.add(...this.copiedClasses);
            this.buttonTarget.textContent = 'Copié !';

            setTimeout(() => {
                this.buttonTarget.classList.remove(...this.copiedClasses);
                this.buttonTarget.textContent = 'Copier';
            }, 2000);
        });
    }
}
