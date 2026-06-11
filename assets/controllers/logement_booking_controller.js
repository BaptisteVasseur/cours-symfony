import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'checkin',
        'checkout',
        'guests',
        'priceBreakdown',
        'lineBase',
        'lineBaseAmt',
        'lineCleaning',
        'lineCleaningAmt',
        'lineService',
        'lineTotal',
        'warning',
        'warningText',
        'loader',
        'cta',
        'lineTotalPerPerson',
    ];

    static values = {
        priceUrl:    { type: String, default: '' },
        checkoutUrl: { type: String, default: '' },
        instant:     { type: String, default: 'false' },
        logged:      { type: String, default: 'false' },
        loginUrl:    { type: String, default: '' },
    };

    connect() {
        // Trigger initial calculation if both dates are already set (from query params)
        if (this.hasCheckinTarget && this.hasCheckoutTarget) {
            const ci = this.checkinTarget.value;
            const co = this.checkoutTarget.value;
            if (ci && co) {
                this._fetchPrice(ci, co);
            }
        }
    }

    onDateChange() {
        const ci = this.hasCheckinTarget  ? this.checkinTarget.value  : '';
        const co = this.hasCheckoutTarget ? this.checkoutTarget.value : '';

        // Sync checkout min with checkin
        if (ci && this.hasCheckoutTarget) {
            this.checkoutTarget.min = ci;
            if (co && co <= ci) {
                this.checkoutTarget.value = '';
                this._reset();
                return;
            }
        }

        if (ci && co) {
            this._fetchPrice(ci, co);
        } else {
            this._reset();
        }
    }

    onGuestsChange() {
        const ci = this.hasCheckinTarget  ? this.checkinTarget.value  : '';
        const co = this.hasCheckoutTarget ? this.checkoutTarget.value : '';
        if (ci && co) {
            this._fetchPrice(ci, co);
        }
    }

    scrollToCalendar() {
        const section = document.getElementById('calendrier-disponibilites');
        if (section) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    async _fetchPrice(checkin, checkout) {
        this._showLoader();

        try {
            const guests = this.hasGuestsTarget ? this.guestsTarget.value : '1';
            const url = `${this.priceUrlValue}?checkin=${checkin}&checkout=${checkout}&guests=${guests}`;
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();

            if (data.error) {
                this._reset();
                return;
            }

            if (data.available === false) {
                this._showWarning(data.message ?? 'Ces dates ne sont pas disponibles.');
                return;
            }

            this._showPriceBreakdown(data, checkin, checkout);
        } catch (_) {
            this._reset();
        }
    }

    _showPriceBreakdown(data, checkin, checkout) {
        this._hideWarning();
        this._hideLoader();

        // Line: prix × nuits (avec mention tarif spécial si applicable)
        if (this.hasLineBaseTarget) {
            const label = data.hasSpecialRate
                ? `${data.nights} nuit${data.nights > 1 ? 's' : ''} <span class="text-brand text-[10px] font-semibold ml-1">tarifs spéciaux</span>`
                : `${this._fmt(data.pricePerNight)} € × ${data.nights} nuit${data.nights > 1 ? 's' : ''}`;
            this.lineBaseTarget.innerHTML = label;
        }
        if (this.hasLineBaseAmtTarget) {
            this.lineBaseAmtTarget.textContent = `${this._fmt(data.baseTotal ?? data.pricePerNight * data.nights)} €`;
        }

        // Line: frais de ménage (masquer si 0)
        if (this.hasLineCleaningTarget) {
            if (data.cleaning > 0) {
                this.lineCleaningTarget.classList.remove('hidden');
                if (this.hasLineCleaningAmtTarget) {
                    this.lineCleaningAmtTarget.textContent = `${this._fmt(data.cleaning)} €`;
                }
            } else {
                this.lineCleaningTarget.classList.add('hidden');
            }
        }

        // Line: frais de service
        if (this.hasLineServiceTarget) {
            this.lineServiceTarget.textContent = `${this._fmt(data.serviceFee)} €`;
        }

        // Line: total
        if (this.hasLineTotalTarget) {
            this.lineTotalTarget.textContent = `${this._fmt(data.total)} €`;
        }

        if (this.hasPriceBreakdownTarget) {
            this.priceBreakdownTarget.classList.remove('hidden');
        }

        // Prix par personne sous le total
        if (this.hasLineTotalPerPersonTarget) {
            const guests = this.hasGuestsTarget ? parseInt(this.guestsTarget.value, 10) : 1;
            if (guests > 1) {
                const perPerson = data.total / guests;
                this.lineTotalPerPersonTarget.textContent = `(soit ${this._fmt(perPerson)} €/pers.)`;
                this.lineTotalPerPersonTarget.classList.remove('hidden');
            } else {
                this.lineTotalPerPersonTarget.classList.add('hidden');
            }
        }

        this._updateCta(checkin, checkout, true);
    }

    _updateCta(checkin, checkout, available) {
        if (!this.hasCtaTarget) return;

        const guestsVal = this.hasGuestsTarget ? this.guestsTarget.value : '1';
        const isLogged  = this.loggedValue === 'true';
        const isInstant = this.instantValue === 'true';

        if (!available) {
            this.ctaTarget.innerHTML = `
                <button disabled class="block w-full text-center bg-gray-300 text-gray-500 font-semibold py-3 rounded-lg cursor-not-allowed">
                    Dates indisponibles
                </button>`;
            return;
        }

        if (!isLogged) {
            this.ctaTarget.innerHTML = `
                <a href="${this.loginUrlValue}"
                   class="block w-full text-center bg-brand hover:bg-brandHover text-white font-semibold py-3 rounded-lg transition">
                    Se connecter pour réserver
                </a>`;
            return;
        }

        let url = `${this.checkoutUrlValue}?checkin=${checkin}&checkout=${checkout}`;
        if (guestsVal && parseInt(guestsVal) > 1) url += `&guests=${guestsVal}`;

        const icon  = isInstant ? '<i class="fa-solid fa-bolt mr-1"></i>' : '';
        const label = isInstant ? 'Réserver instantanément' : 'Demander à réserver';

        this.ctaTarget.innerHTML = `
            <a href="${url}"
               class="block w-full text-center bg-brand hover:bg-brandHover text-white font-semibold py-3 rounded-lg transition">
                ${icon}${label}
            </a>`;
    }

    _showWarning(message) {
        this._hideLoader();

        if (this.hasWarningTarget) {
            this.warningTarget.classList.remove('hidden');
        }
        if (this.hasWarningTextTarget) {
            this.warningTextTarget.textContent = message;
        }

        if (this.hasPriceBreakdownTarget) {
            this.priceBreakdownTarget.classList.add('hidden');
        }
        if (this.hasLineTotalPerPersonTarget) {
            this.lineTotalPerPersonTarget.classList.add('hidden');
        }

        // Mettre à jour le CTA en mode indisponible
        this._updateCta('', '', false);

        // Scroll automatique vers le calendrier après 800ms
        setTimeout(() => this.scrollToCalendar(), 800);
    }

    _hideWarning() {
        if (this.hasWarningTarget) {
            this.warningTarget.classList.add('hidden');
        }
    }

    _showLoader() {
        this._hideWarning();
        if (this.hasPriceBreakdownTarget) {
            this.priceBreakdownTarget.classList.add('hidden');
        }
        if (this.hasLoaderTarget) {
            this.loaderTarget.classList.remove('hidden');
        }
    }

    _hideLoader() {
        if (this.hasLoaderTarget) {
            this.loaderTarget.classList.add('hidden');
        }
    }

    _reset() {
        this._hideLoader();
        this._hideWarning();
        if (this.hasPriceBreakdownTarget) {
            this.priceBreakdownTarget.classList.add('hidden');
        }
        if (this.hasLineTotalPerPersonTarget) {
            this.lineTotalPerPersonTarget.classList.add('hidden');
        }
        // Restaurer le CTA par défaut (sans dates)
        if (this.hasCtaTarget) {
            const isLogged  = this.loggedValue === 'true';
            const isInstant = this.instantValue === 'true';
            if (!isLogged) {
                this.ctaTarget.innerHTML = `
                    <a href="${this.loginUrlValue}"
                       class="block w-full text-center bg-brand hover:bg-brandHover text-white font-semibold py-3 rounded-lg transition">
                        Se connecter pour réserver
                    </a>`;
            } else {
                const icon  = isInstant ? '<i class="fa-solid fa-bolt mr-1"></i>' : '';
                const label = isInstant ? 'Réserver instantanément' : 'Demander à réserver';
                this.ctaTarget.innerHTML = `
                    <a href="${this.checkoutUrlValue}"
                       class="block w-full text-center bg-brand hover:bg-brandHover text-white font-semibold py-3 rounded-lg transition opacity-50 pointer-events-none">
                        ${icon}${label}
                    </a>`;
            }
        }
    }

    // Formatte un nombre en style français (espace comme séparateur de milliers, virgule décimale)
    _fmt(n) {
        return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(n);
    }
}
