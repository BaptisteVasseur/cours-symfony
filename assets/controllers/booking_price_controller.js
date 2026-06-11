import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'checkin',
        'checkout',
        'guests',
        'breakdown',
        'nightsLabel',
        'subtotal',
        'cleaning',
        'service',
        'total',
        'error',
        'submitBtn'
    ];

    static values = {
        propertyId: String,
        pricePerNight: Number
    };

    connect() {
        this.updatePrice();
    }

    async updatePrice() {
        const checkinVal = this.checkinTarget.value;
        const checkoutVal = this.checkoutTarget.value;
        const guestsVal = this.hasGuestsTarget ? this.guestsTarget.value : 1;

        if (!checkinVal || !checkoutVal) {
            this.hideBreakdown();
            this.hideError();
            if (this.hasSubmitBtnTarget) {
                this.submitBtnTarget.disabled = true;
            }
            return;
        }

        try {
            const url = `/api/properties/${this.propertyIdValue}/availability?` + 
                new URLSearchParams({
                    checkin: checkinVal,
                    checkout: checkoutVal,
                    guests: guestsVal
                });

            const response = await fetch(url);
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }

            const data = await response.json();

            if (data.available) {
                this.showBreakdown(data);
                this.hideError();
                if (this.hasSubmitBtnTarget) {
                    this.submitBtnTarget.disabled = false;
                }
            } else {
                this.hideBreakdown();
                this.showError(data.error || 'Ce logement n’est pas disponible pour ces dates.');
                if (this.hasSubmitBtnTarget) {
                    this.submitBtnTarget.disabled = true;
                }
            }
        } catch (err) {
            console.error(err);
            this.hideBreakdown();
            this.showError('Impossible de calculer le prix. Veuillez vérifier votre connexion.');
            if (this.hasSubmitBtnTarget) {
                this.submitBtnTarget.disabled = true;
            }
        }
    }

    showBreakdown(data) {
        if (!this.hasBreakdownTarget) return;

        const formattedPrice = this.formatPrice(this.pricePerNightValue);
        const nightsText = data.nights > 1 ? 'nuits' : 'nuit';
        
        if (this.hasNightsLabelTarget) {
            this.nightsLabelTarget.textContent = `${formattedPrice} × ${data.nights} ${nightsText}`;
        }
        if (this.hasSubtotalTarget) {
            this.subtotalTarget.textContent = this.formatPrice(data.subtotal);
        }
        if (this.hasCleaningTarget && data.cleaningFee) {
            this.cleaningTarget.textContent = this.formatPrice(data.cleaningFee);
        }
        if (this.hasServiceTarget) {
            this.serviceTarget.textContent = this.formatPrice(data.serviceFee);
        }
        if (this.hasTotalTarget) {
            this.totalTarget.textContent = this.formatPrice(data.totalPrice);
        }

        this.breakdownTarget.classList.remove('hidden');
    }

    hideBreakdown() {
        if (this.hasBreakdownTarget) {
            this.breakdownTarget.classList.add('hidden');
        }
    }

    showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message;
            this.errorTarget.classList.remove('hidden');
        }
    }

    hideError() {
        if (this.hasErrorTarget) {
            this.errorTarget.classList.add('hidden');
            this.errorTarget.textContent = '';
        }
    }

    formatPrice(value) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(parseFloat(value));
    }
}
