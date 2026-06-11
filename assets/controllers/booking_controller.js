import { Controller } from '@hotwired/stimulus';

const MS_PER_DAY = 86400000;

export default class extends Controller {
    static targets = [
        'checkin',
        'checkout',
        'guests',
        'nights',
        'subtotal',
        'cleaning',
        'cleaningRow',
        'service',
        'total',
        'recap',
        'placeholder',
    ];

    static values = {
        rate: Number,
        cleaningFee: { type: Number, default: 0 },
        serviceRate: { type: Number, default: 0.12 },
    };

    connect() {
        this.seedFromUrl();
        this.syncMinCheckout();
        this.recompute();
    }

    seedFromUrl() {
        const params = new URLSearchParams(window.location.search);
        this.maybeSet(this.hasCheckinTarget ? this.checkinTarget : null, params.get('checkin'));
        this.maybeSet(this.hasCheckoutTarget ? this.checkoutTarget : null, params.get('checkout'));
        this.maybeSet(this.hasGuestsTarget ? this.guestsTarget : null, params.get('guests'));
    }

    maybeSet(el, value) {
        if (el && value && !el.value) {
            el.value = value;
        }
    }

    update() {
        this.syncMinCheckout();
        this.recompute();
    }

    syncMinCheckout() {
        if (!this.hasCheckinTarget || !this.hasCheckoutTarget || !this.checkinTarget.value) {
            return;
        }

        const min = this.addDays(this.checkinTarget.value, 1);
        this.checkoutTarget.min = min;
        if (this.checkoutTarget.value && this.checkoutTarget.value < min) {
            this.checkoutTarget.value = min;
        }
    }

    recompute() {
        const nights = this.nights();
        const hasStay = nights > 0;

        if (this.hasRecapTarget) {
            this.recapTarget.classList.toggle('hidden', !hasStay);
        }
        if (this.hasPlaceholderTarget) {
            this.placeholderTarget.classList.toggle('hidden', hasStay);
        }
        if (!hasStay) {
            return;
        }

        const subtotal = nights * (this.rateValue || 0);
        const cleaning = this.cleaningFeeValue || 0;
        const service = Math.round(subtotal * this.serviceRateValue * 100) / 100;
        const total = subtotal + cleaning + service;

        if (this.hasNightsTarget) {
            this.nightsTarget.textContent = nights;
        }
        this.fill(this.hasSubtotalTarget ? this.subtotalTarget : null, subtotal);
        this.fill(this.hasCleaningTarget ? this.cleaningTarget : null, cleaning);
        this.fill(this.hasServiceTarget ? this.serviceTarget : null, service);
        this.fill(this.hasTotalTarget ? this.totalTarget : null, total);

        if (this.hasCleaningRowTarget) {
            this.cleaningRowTarget.classList.toggle('hidden', cleaning <= 0);
        }
    }

    nights() {
        if (!this.hasCheckinTarget || !this.hasCheckoutTarget) {
            return 0;
        }
        const start = this.checkinTarget.value;
        const end = this.checkoutTarget.value;
        if (!start || !end) {
            return 0;
        }
        const diff = Math.round((new Date(`${end}T00:00:00`) - new Date(`${start}T00:00:00`)) / MS_PER_DAY);

        return diff > 0 ? diff : 0;
    }

    addDays(iso, days) {
        const date = new Date(`${iso}T00:00:00`);
        date.setDate(date.getDate() + days);

        return date.toISOString().slice(0, 10);
    }

    fill(el, value) {
        if (el) {
            el.textContent = this.fmt(value);
        }
    }

    fmt(value) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(value);
    }
}
