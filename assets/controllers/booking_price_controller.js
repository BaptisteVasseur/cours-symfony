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
        pricePerNight: Number,
        cleaningFee: Number
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

        const pricePerNight = parseFloat(data.pricePerNight || this.pricePerNightValue);
        const cleaningFee = this.hasCleaningFeeValue ? parseFloat(this.cleaningFeeValue) : 0;
        
        const pricePerNightCents = Math.round(pricePerNight * 100);
        const subtotalCents = pricePerNightCents * data.nights;
        const subtotal = subtotalCents / 100;
        
        if (this.hasSubtotalTarget) {
            this.subtotalTarget.textContent = this.formatPrice(subtotal);
        }
        if (this.hasCleaningTarget) {
            this.cleaningTarget.textContent = this.formatPrice(cleaningFee);
        }
        
        const serviceFeeCents = Math.floor((subtotalCents * 12 + 50) / 100);
        const serviceFee = serviceFeeCents / 100;
        
        if (this.hasServiceTarget) {
            this.serviceTarget.textContent = this.formatPrice(serviceFee);
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

    disableSubmit(event) {
        if (this.isSubmitting) {
            event.preventDefault();
            return;
        }
        this.isSubmitting = true;
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = true;
            this.submitBtnTarget.textContent = 'Réservation en cours...';
        }
    }
}
