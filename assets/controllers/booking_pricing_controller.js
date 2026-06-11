import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'checkin',
        'checkout',
        'guests',
        'nights',
        'subtotal',
        'cleaningFee',
        'serviceFee',
        'total',
        'breakdown',
    ];

    static values = {
        pricePerNight: Number,
        cleaningFee: Number,
        serviceFeeRate: { type: Number, default: 0.12 },
    };

    connect() {
        this.update();
    }

    update() {
        const checkin = this.checkinTarget.value;
        const checkout = this.checkoutTarget.value;

        if (!checkin || !checkout) {
            this.hideBreakdown();
            return;
        }

        const start = new Date(checkin + 'T00:00:00');
        const end = new Date(checkout + 'T00:00:00');
        const diffMs = end - start;
        const nights = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (nights <= 0) {
            this.hideBreakdown();
            return;
        }

        const subtotal = this.pricePerNightValue * nights;
        const cleaningFee = this.cleaningFeeValue;
        const serviceFee = Math.round(subtotal * this.serviceFeeRateValue * 100) / 100;
        const total = Math.round((subtotal + cleaningFee + serviceFee) * 100) / 100;

        this.nightsTarget.textContent = nights;
        this.subtotalTarget.textContent = this.formatCurrency(subtotal);
        this.cleaningFeeTarget.textContent = this.formatCurrency(cleaningFee);
        this.serviceFeeTarget.textContent = this.formatCurrency(serviceFee);
        this.totalTarget.textContent = this.formatCurrency(total);
        this.breakdownTarget.classList.remove('hidden');
    }

    hideBreakdown() {
        if (this.hasBreakdownTarget) {
            this.breakdownTarget.classList.add('hidden');
        }
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(amount);
    }
}
