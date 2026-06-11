import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkin', 'checkout', 'warning', 'submit', 'blockedList'];
    static values = { propertyId: String };

    connect() {
        this.blockedRanges = [];
        this.setMinDates();
        this.fetchBlockedDates();
    }

    setMinDates() {
        const today = new Date().toISOString().split('T')[0];
        this.checkinTarget.min = today;
        this.checkoutTarget.min = today;
    }

    async fetchBlockedDates() {
        try {
            const response = await fetch(`/api/properties/${this.propertyIdValue}/blocked-dates`);
            if (response.ok) {
                this.blockedRanges = await response.json();
                this.renderBlockedList();
            }
        } catch (e) {
            // Silently fail — validation will happen server-side
        }
    }

    renderBlockedList() {
        if (this.blockedRanges.length === 0) {
            this.blockedListTarget.classList.add('hidden');
            return;
        }

        const items = this.blockedRanges.map(range => {
            const icon = range.type === 'reservation'
                ? '<i class="fa-solid fa-user-clock text-red-400"></i>'
                : '<i class="fa-solid fa-ban text-gray-400"></i>';
            const label = range.type === 'reservation' ? 'Déjà réservé' : (range.label || 'Indisponible');
            return `<li class="flex items-center gap-2 text-sm text-gray-700">
                ${icon}
                <span class="font-medium">${this.formatDate(range.start)}</span>
                <span class="text-gray-400">→</span>
                <span class="font-medium">${this.formatDate(range.end)}</span>
                <span class="text-gray-500 text-xs">(${label})</span>
            </li>`;
        }).join('');

        this.blockedListTarget.innerHTML = `
            <p class="text-sm font-semibold text-gray-800 mb-2"><i class="fa-solid fa-calendar-xmark text-red-400 mr-1"></i> Périodes indisponibles</p>
            <ul class="space-y-1.5">${items}</ul>
        `;
        this.blockedListTarget.classList.remove('hidden');
    }

    checkDates() {
        const checkin = this.checkinTarget.value;
        const checkout = this.checkoutTarget.value;

        // Update checkout min based on checkin
        if (checkin) {
            const nextDay = new Date(checkin);
            nextDay.setDate(nextDay.getDate() + 1);
            this.checkoutTarget.min = nextDay.toISOString().split('T')[0];
        }

        if (!checkin || !checkout) {
            this.hideWarning();
            return;
        }

        const conflict = this.blockedRanges.find(range => {
            return checkin < range.end && checkout > range.start;
        });

        if (conflict) {
            const label = conflict.type === 'reservation' ? 'Déjà réservé' : (conflict.label || 'Indisponible');
            this.showWarning(`Conflit : ${label} du ${this.formatDate(conflict.start)} au ${this.formatDate(conflict.end)}. Veuillez choisir d'autres dates.`);
        } else {
            this.hideWarning();
        }
    }

    showWarning(message) {
        this.warningTarget.innerHTML = `<i class="fa-solid fa-triangle-exclamation mr-1"></i> ${message}`;
        this.warningTarget.classList.remove('hidden');
        this.submitTarget.disabled = true;
        this.submitTarget.classList.add('opacity-50', 'cursor-not-allowed');
    }

    hideWarning() {
        this.warningTarget.textContent = '';
        this.warningTarget.classList.add('hidden');
        this.submitTarget.disabled = false;
        this.submitTarget.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    formatDate(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    }
}
