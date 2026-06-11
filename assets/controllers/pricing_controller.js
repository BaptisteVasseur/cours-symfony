import { Controller } from '@hotwired/stimulus';

/*
 * Tarification dynamique du tunnel de réservation (G.6).
 * Recalcule une estimation du coût total (nuits × prix + frais) à chaque
 * changement de dates. Le serveur reste la source de vérité au moment de la
 * validation : c'est une estimation indicative côté client.
 */
export default class extends Controller {
    static targets = ['checkin', 'checkout', 'summary', 'nights', 'subtotal', 'service', 'cleaningRow', 'total'];
    static values = { price: Number, cleaning: Number, serviceRate: Number };

    connect() {
        this.update();
    }

    update() {
        const checkin = this.hasCheckinTarget ? this.checkinTarget.value : null;
        const checkout = this.hasCheckoutTarget ? this.checkoutTarget.value : null;

        if (!checkin || !checkout) {
            this.hide();
            return;
        }

        const nights = Math.round((new Date(checkout) - new Date(checkin)) / 86400000);
        if (!Number.isFinite(nights) || nights < 1) {
            this.hide();
            return;
        }

        const subtotal = nights * this.priceValue;
        const service = Math.round(subtotal * this.serviceRateValue * 100) / 100;
        const total = subtotal + this.cleaningValue + service;

        this.nightsTarget.textContent = nights;
        this.subtotalTarget.textContent = this.format(subtotal);
        this.serviceTarget.textContent = this.format(service);
        this.totalTarget.textContent = this.format(total);
        this.summaryTarget.classList.remove('hidden');
    }

    hide() {
        if (this.hasSummaryTarget) {
            this.summaryTarget.classList.add('hidden');
        }
    }

    format(value) {
        return value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }
}
