import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'field',
        'guestsInput',
        'guestsLabel',
        'guestsPanel',
        'guestsCount',
        'decrementBtn',
        'mobilePanel',
    ];

    static values = {
        guests: { type: Number, default: 1 },
    };

    connect() {
        this.updateGuestsUi();
        this.closeGuests = this.closeGuests.bind(this);
        document.addEventListener('click', this.closeGuests);
    }

    disconnect() {
        document.removeEventListener('click', this.closeGuests);
    }

    selectField(event) {
        this.fieldTargets.forEach((field) => {
            field.classList.remove('bg-gray-100', 'shadow-inner');
        });
        const container = event.currentTarget.closest('[data-search-target="field"]') ?? event.currentTarget;
        container.classList.add('bg-gray-100', 'shadow-inner');
    }

    toggleGuests(event) {
        event.stopPropagation();
        this.guestsPanelTarget.classList.toggle('hidden');
        this.selectField(event);
    }

    stopPropagation(event) {
        event.stopPropagation();
    }

    closeGuests(event) {
        if (!this.hasGuestsPanelTarget) {
            return;
        }

        if (!this.element.contains(event.target)) {
            this.guestsPanelTarget.classList.add('hidden');
        }
    }

    incrementGuests() {
        if (this.guestsValue < 16) {
            this.guestsValue++;
            this.updateGuestsUi();
        }
    }

    decrementGuests() {
        if (this.guestsValue > 1) {
            this.guestsValue--;
            this.updateGuestsUi();
        }
    }

    updateGuestsUi() {
        if (this.hasGuestsInputTarget) {
            this.guestsInputTarget.value = this.guestsValue;
        }
        if (this.hasGuestsLabelTarget) {
            const label = this.guestsValue > 1 ? 'voyageurs' : 'voyageur';
            this.guestsLabelTarget.textContent = `${this.guestsValue} ${label}`;
        }
        if (this.hasGuestsCountTarget) {
            this.guestsCountTarget.textContent = this.guestsValue;
        }
        if (this.hasDecrementBtnTarget) {
            this.decrementBtnTarget.disabled = this.guestsValue <= 1;
        }
    }

    openMobile() {
        if (this.hasMobilePanelTarget) {
            this.mobilePanelTarget.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    }

    closeMobile() {
        if (this.hasMobilePanelTarget) {
            this.mobilePanelTarget.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }
}
