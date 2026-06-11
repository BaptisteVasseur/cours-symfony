import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        booked: Array,
        blocked: Array,
        price: Number,
        cleaning: Number,
        propertyId: String,
    }

    static targets = [
        'grid', 'monthLabel',
        'checkinInput', 'checkoutInput',
        'pricePreview', 'nights', 'subtotal', 'service', 'total',
        'rangeError', 'bookBtn',
    ]

    connect() {
        this.today = new Date();
        this.today.setHours(0, 0, 0, 0);
        this.currentYear = this.today.getFullYear();
        this.currentMonth = this.today.getMonth();
        this.selectedCheckin = null;
        this.selectedCheckout = null;
        this.buildDisabledSet();
        this.setInitialDates();
        this.render();
        this.updatePrice();
    }

    buildDisabledSet() {
        this.disabledSet = {};
        const next = (d) => new Date(d.getTime() + 86400000);
        const fmt = (d) => d.toISOString().slice(0, 10);

        (this.bookedValue || []).forEach((r) => {
            let cur = new Date(r.from + 'T00:00:00');
            const end = new Date(r.to + 'T00:00:00');
            while (cur < end) {
                this.disabledSet[fmt(cur)] = true;
                cur = next(cur);
            }
        });
        (this.blockedValue || []).forEach((d) => {
            this.disabledSet[d] = true;
        });
    }

    setInitialDates() {
        const ci = this.hasCheckinInputTarget ? this.checkinInputTarget.value : '';
        const co = this.hasCheckoutInputTarget ? this.checkoutInputTarget.value : '';
        if (ci) this.selectedCheckin = ci;
        if (co) this.selectedCheckout = co;
    }

    prevMonth() {
        this.currentMonth--;
        if (this.currentMonth < 0) {
            this.currentMonth = 11;
            this.currentYear--;
        }
        this.render();
    }

    nextMonth() {
        this.currentMonth++;
        if (this.currentMonth > 11) {
            this.currentMonth = 0;
            this.currentYear++;
        }
        this.render();
    }

    render() {
        const year = this.currentYear;
        const month = this.currentMonth;
        const monthNames = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
        ];

        this.monthLabelTarget.textContent = `${monthNames[month]} ${year}`;

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        let startCol = firstDay.getDay() - 1;
        if (startCol < 0) startCol = 6;

        const fmt = (d) => d.toISOString().slice(0, 10);

        let html = '<tr>';
        for (let i = 0; i < startCol; i++) {
            html += '<td></td>';
        }

        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            date.setHours(0, 0, 0, 0);
            const dateStr = fmt(date);
            const isPast = date < this.today;
            const isToday = date.getTime() === this.today.getTime();
            const isBlocked = !!this.disabledSet[dateStr];
            const isCheckin = this.selectedCheckin === dateStr;
            const isCheckout = this.selectedCheckout === dateStr;
            const isInRange = this.selectedCheckin
                && this.selectedCheckout
                && dateStr > this.selectedCheckin
                && dateStr < this.selectedCheckout;

            let classes = 'calendar-day';
            if (isToday) classes += ' today';
            if (isPast) classes += ' past';
            if (isBlocked && !isPast) classes += ' blocked';
            if (isCheckin) classes += ' checkin';
            if (isCheckout) classes += ' checkout';
            if (isInRange) classes += ' in-range';
            if (!isPast && !isBlocked && !isCheckin && !isCheckout) classes += ' available';

            html += `<td class="${classes}" data-date="${dateStr}" data-action="click->calendar#selectDay">${day}</td>`;

            const col = (startCol + day) % 7;
            if (col === 0 && day < lastDay.getDate()) {
                html += '</tr><tr>';
            }
        }

        const totalCells = startCol + lastDay.getDate();
        const remainder = totalCells % 7;
        if (remainder > 0) {
            for (let i = remainder; i < 7; i++) {
                html += '<td></td>';
            }
        }
        html += '</tr>';

        this.gridTarget.innerHTML = html;
    }

    selectDay(event) {
        const dateStr = event.currentTarget.dataset.date;
        if (this.disabledSet[dateStr]) return;

        const clickedDate = new Date(dateStr + 'T00:00:00');
        if (clickedDate < this.today) return;

        if (!this.selectedCheckin || (this.selectedCheckin && this.selectedCheckout)) {
            this.selectedCheckin = dateStr;
            this.selectedCheckout = null;
        } else if (dateStr > this.selectedCheckin) {
            if (this.rangeHasDisabled(this.selectedCheckin, dateStr)) {
                this.selectedCheckin = dateStr;
                this.selectedCheckout = null;
            } else {
                this.selectedCheckout = dateStr;
            }
        } else {
            this.selectedCheckin = dateStr;
            this.selectedCheckout = null;
        }

        this.syncInputs();
        this.updatePrice();
        this.render();
    }

    rangeHasDisabled(from, to) {
        const fromDate = new Date(from + 'T00:00:00');
        const toDate = new Date(to + 'T00:00:00');
        let cur = new Date(fromDate.getTime() + 86400000);
        while (cur < toDate) {
            if (this.disabledSet[cur.toISOString().slice(0, 10)]) return true;
            cur = new Date(cur.getTime() + 86400000);
        }
        return false;
    }

    syncInputs() {
        if (this.hasCheckinInputTarget) {
            this.checkinInputTarget.value = this.selectedCheckin || '';
        }
        if (this.hasCheckoutInputTarget) {
            this.checkoutInputTarget.value = this.selectedCheckout || '';
        }
    }

    updatePrice() {
        if (!this.hasPricePreviewTarget) return;

        if (!this.selectedCheckin || !this.selectedCheckout) {
            this.pricePreviewTarget.style.display = 'none';
            return;
        }

        const from = new Date(this.selectedCheckin + 'T00:00:00');
        const to = new Date(this.selectedCheckout + 'T00:00:00');

        if (to <= from) {
            this.pricePreviewTarget.style.display = 'none';
            return;
        }

        if (this.rangeHasDisabled(this.selectedCheckin, this.selectedCheckout)) {
            if (this.hasRangeErrorTarget) this.rangeErrorTarget.style.display = '';
            this.pricePreviewTarget.style.display = 'none';
            return;
        }

        if (this.hasRangeErrorTarget) this.rangeErrorTarget.style.display = 'none';

        const nights = Math.round((to - from) / 86400000);
        const subtotal = this.priceValue * nights;
        const serviceFee = Math.round(subtotal * 0.12 * 100) / 100;
        const total = subtotal + this.cleaningValue + serviceFee;

        if (this.hasNightsTarget) {
            this.nightsTarget.textContent = `${nights} nuit${nights > 1 ? 's' : ''} \u00d7 ${this.priceValue} \u20ac`;
        }
        if (this.hasSubtotalTarget) {
            this.subtotalTarget.textContent = `${subtotal.toLocaleString('fr-FR')} \u20ac`;
        }
        if (this.hasServiceTarget) {
            this.serviceTarget.textContent = `${serviceFee.toLocaleString('fr-FR')} \u20ac`;
        }
        if (this.hasTotalTarget) {
            this.totalTarget.textContent = `${total.toLocaleString('fr-FR')} \u20ac`;
        }
        if (this.hasBookBtnTarget) {
            this.bookBtnTarget.href = `/logement/${this.propertyIdValue}/reserver?checkin=${this.selectedCheckin}&checkout=${this.selectedCheckout}`;
        }
        this.pricePreviewTarget.style.display = '';
    }
}
