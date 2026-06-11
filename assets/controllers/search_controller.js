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
        'destinationInput',
        'autocompleteList',
        'checkinInput',
        'checkoutInput',
    ];

    static values = {
        guests:          { type: Number,  default: 1 },
        suggestionsUrl:  { type: String,  default: '/api/search/suggestions' },
    };

    connect() {
        this.updateGuestsUi();
        this.closeGuests    = this.closeGuests.bind(this);
        this.closeAutocomplete = this.closeAutocomplete.bind(this);
        document.addEventListener('click', this.closeGuests);
        document.addEventListener('click', this.closeAutocomplete);
        this._debounceTimer = null;
        this._activeIndex   = -1;
        this._suggestions   = [];
    }

    disconnect() {
        document.removeEventListener('click', this.closeGuests);
        document.removeEventListener('click', this.closeAutocomplete);
        clearTimeout(this._debounceTimer);
    }

    // ── Destination autocomplete ──────────────────────────────────────────

    onDestinationInput(event) {
        const q = event.target.value.trim();
        this._activeIndex = -1;

        clearTimeout(this._debounceTimer);

        if (q.length < 2) {
            this._hideSuggestions();
            return;
        }

        this._debounceTimer = setTimeout(() => this._fetchSuggestions(q), 250);
    }

    onDestinationKeydown(event) {
        if (!this.hasAutocompleteListTarget) return;
        const items = this.autocompleteListTarget.querySelectorAll('[data-suggestion]');
        if (!items.length) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this._activeIndex = Math.min(this._activeIndex + 1, items.length - 1);
            this._highlightItem(items);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            this._activeIndex = Math.max(this._activeIndex - 1, 0);
            this._highlightItem(items);
        } else if (event.key === 'Enter' && this._activeIndex >= 0) {
            event.preventDefault();
            items[this._activeIndex].click();
        } else if (event.key === 'Escape') {
            this._hideSuggestions();
        }
    }

    selectSuggestion(event) {
        const value = event.currentTarget.dataset.value;
        const label = event.currentTarget.dataset.label;
        this.destinationInputTargets.forEach(input => {
            input.value = value;
        });
        this._hideSuggestions();
    }

    closeAutocomplete(event) {
        if (!this.hasAutocompleteListTarget) return;
        if (!this.element.contains(event.target)) {
            this._hideSuggestions();
        }
    }

    async _fetchSuggestions(q) {
        try {
            const url = `${this.suggestionsUrlValue}?q=${encodeURIComponent(q)}`;
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            this._suggestions = await res.json();
            this._renderSuggestions(this._suggestions);
        } catch (_) {
            // network error — fail silently
        }
    }

    _renderSuggestions(suggestions) {
        if (!this.hasAutocompleteListTarget) return;
        const list = this.autocompleteListTarget;

        if (!suggestions.length) {
            this._hideSuggestions();
            return;
        }

        list.innerHTML = suggestions.map((s, i) => `
            <button
                type="button"
                class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 text-sm transition"
                data-suggestion="${i}"
                data-value="${this._esc(s.value)}"
                data-label="${this._esc(s.label)}"
                data-action="click->search#selectSuggestion mouseenter->search#hoverSuggestion"
            >
                <i class="fa-solid fa-location-dot text-gray-400 w-4 shrink-0"></i>
                <span>${this._esc(s.label)}</span>
            </button>
        `).join('');

        list.classList.remove('hidden');
    }

    hoverSuggestion(event) {
        const items = this.autocompleteListTarget.querySelectorAll('[data-suggestion]');
        this._activeIndex = parseInt(event.currentTarget.dataset.suggestion, 10);
        this._highlightItem(items);
    }

    _highlightItem(items) {
        items.forEach((el, i) => {
            el.classList.toggle('bg-gray-100', i === this._activeIndex);
        });
    }

    _hideSuggestions() {
        if (this.hasAutocompleteListTarget) {
            this.autocompleteListTarget.classList.add('hidden');
            this.autocompleteListTarget.innerHTML = '';
        }
        this._activeIndex = -1;
    }

    _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ── Date constraints ─────────────────────────────────────────────────

    onCheckinChange(event) {
        const checkinVal = event.target.value;
        if (!checkinVal || !this.hasCheckoutInputTarget) return;

        // Push min on all checkout inputs
        this.checkoutInputTargets.forEach(input => {
            input.min = checkinVal;
            // If current checkout is before the new checkin, clear it
            if (input.value && input.value <= checkinVal) {
                input.value = '';
            }
        });
    }

    // ── Guests counter ────────────────────────────────────────────────────

    selectField(event) {
        this.fieldTargets.forEach(field => {
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
        if (!this.hasGuestsPanelTarget) return;
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
        if (this.hasGuestsInputTarget) this.guestsInputTarget.value = this.guestsValue;
        if (this.hasGuestsLabelTarget) {
            const label = this.guestsValue > 1 ? 'voyageurs' : 'voyageur';
            this.guestsLabelTarget.textContent = `${this.guestsValue} ${label}`;
        }
        if (this.hasGuestsCountTarget)   this.guestsCountTarget.textContent   = this.guestsValue;
        if (this.hasDecrementBtnTarget)  this.decrementBtnTarget.disabled     = this.guestsValue <= 1;
    }

    // ── Mobile panel ──────────────────────────────────────────────────────

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
