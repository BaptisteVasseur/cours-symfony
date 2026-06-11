import { Controller } from '@hotwired/stimulus';

/*
 * Tarification dynamique sur la fiche logement (G.6) : recalcule le total
 * (nuits × prix + ménage + service) à chaque changement de dates/voyageurs,
 * et propage les dates choisies au lien de réservation.
 */
export default class extends Controller {
    static targets = ['checkin', 'checkout', 'guests', 'breakdown', 'nightsLabel', 'subtotal', 'service', 'total', 'hint', 'cta'];
    static values = { price: Number, cleaning: Number, serviceRate: Number, maxGuests: Number };

    connect() {
        this.eur = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });
        this.update();
    }

    update() {
        const checkin = this.checkinTarget.value;
        const checkout = this.checkoutTarget.value;
        let guests = parseInt(this.guestsTarget.value, 10) || 1;
        guests = Math.min(Math.max(guests, 1), this.maxGuestsValue);
        this.guestsTarget.value = guests;

        const nights = this.nightsBetween(checkin, checkout);

        if (nights > 0) {
            const subtotal = Math.round(this.priceValue * nights * 100) / 100;
            const service = Math.round(subtotal * this.serviceRateValue * 100) / 100;
            const total = Math.round((subtotal + this.cleaningValue + service) * 100) / 100;

            this.nightsLabelTarget.textContent = `${this.eur.format(this.priceValue)} × ${nights} nuit${nights > 1 ? 's' : ''}`;
            this.subtotalTarget.textContent = this.eur.format(subtotal);
            this.serviceTarget.textContent = this.eur.format(service);
            this.totalTarget.textContent = this.eur.format(total);
            this.breakdownTarget.classList.remove('hidden');
            this.hintTarget.classList.add('hidden');
        } else {
            this.breakdownTarget.classList.add('hidden');
            this.hintTarget.classList.remove('hidden');
        }

        this.syncCta(checkin, checkout, guests, nights);
    }

    syncCta(checkin, checkout, guests, nights) {
        const params = new URLSearchParams();
        if (nights > 0) {
            params.set('checkin', checkin);
            params.set('checkout', checkout);
        }
        params.set('guests', guests);
        const query = params.toString();
        this.ctaTarget.href = query ? `${this.ctaTarget.dataset.bookingUrl}?${query}` : this.ctaTarget.dataset.bookingUrl;
    }

    nightsBetween(a, b) {
        if (!a || !b) {
            return 0;
        }
        const diff = (new Date(`${b}T00:00:00Z`) - new Date(`${a}T00:00:00Z`)) / 86400000;
        return diff > 0 ? Math.round(diff) : 0;
    }
}
