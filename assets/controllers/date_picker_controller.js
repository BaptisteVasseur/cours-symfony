import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = [
        "modal",
        "calendar",
        "sidebarCheckin",
        "sidebarCheckout",
        "modalCheckin",
        "modalCheckout",
        "nights",
        "nightsAmount",
        "cleaningFeeRow",
        "cleaningFeeAmount",
        "total",
        "priceBreakdown",
        "hint",
        "selectBtn",
        "guestCount",
        "guestDecBtn",
        "guestIncBtn",
        "reserveLink",
    ];

    static values = {
        pricePerNight: Number,
        cleaningFee: Number,
        maxGuests: Number,
        blockedDates: Array,
        calendarUrl: String,
    };

    connect() {
        this.checkIn = null;
        this.checkOut = null;
        this.selecting = "checkin";
        this.viewDate = new Date();
        this.viewDate.setDate(1);
        this.guests = 1;
        this.blockedSet = new Set(this.blockedDatesValue);
        this.cutoffDate = null;
        this.updateGuestButtons();
    }

    async openModal() {
        this.modalTarget.classList.remove("hidden");
        document.body.classList.add("overflow-hidden");
        await this.fetchCalendar();
        this.updateModalDates();
    }

    closeModal() {
        this.modalTarget.classList.add("hidden");
        document.body.classList.remove("overflow-hidden");
    }

    select() {
        if (!this.checkIn || !this.checkOut) return;
        this.sidebarCheckinTarget.textContent = this.formatDate(this.checkIn);
        this.sidebarCheckoutTarget.textContent = this.formatDate(this.checkOut);
        this.updateReserveLink();
        this.closeModal();
    }

    updateReserveLink() {
        if (!this.hasReserveLinkTarget || !this.checkIn || !this.checkOut)
            return;
        const url = new URL(
            this.reserveLinkTarget.href,
            window.location.origin,
        );
        url.searchParams.set("checkin", this.toDateStr(this.checkIn));
        url.searchParams.set("checkout", this.toDateStr(this.checkOut));
        url.searchParams.set("guests", this.guests);
        this.reserveLinkTarget.href = url.toString();
    }

    incrementGuests() {
        if (this.guests >= this.maxGuestsValue) return;
        this.guests++;
        this.guestCountTarget.textContent = this.guests;
        this.updateGuestButtons();
    }

    decrementGuests() {
        if (this.guests <= 1) return;
        this.guests--;
        this.guestCountTarget.textContent = this.guests;
        this.updateGuestButtons();
    }

    updateGuestButtons() {
        this.guestDecBtnTarget.disabled = this.guests <= 1;
        this.guestIncBtnTarget.disabled = this.guests >= this.maxGuestsValue;
        this.updateReserveLink();
    }

    async prevMonth() {
        this.viewDate.setMonth(this.viewDate.getMonth() - 1);
        await this.fetchCalendar();
    }

    async nextMonth() {
        this.viewDate.setMonth(this.viewDate.getMonth() + 1);
        await this.fetchCalendar();
    }

    async handleCalendarClick(event) {
        const dayEl = event.target.closest("[data-date]");
        if (!dayEl) return;

        const date = new Date(dayEl.dataset.date + "T12:00:00");
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (date < today) return;

        if (!this.checkIn || this.selecting === "checkin") {
            this.checkIn = date;
            this.checkOut = null;
            this.selecting = "checkout";
            this.computeCutoff();
        } else if (
            date <= this.checkIn ||
            (this.cutoffDate && date >= this.cutoffDate)
        ) {
            this.checkIn = date;
            this.checkOut = null;
            this.selecting = "checkout";
            this.computeCutoff();
        } else {
            this.checkOut = date;
            this.selecting = "checkin";
            this.cutoffDate = null;
        }

        await this.fetchCalendar();
        this.updateModalDates();
        this.updatePricing();
    }

    handleCalendarHover(event) {
        const dayEl = event.target.closest("[data-date]");
        this.applyHoverRange(
            dayEl ? new Date(dayEl.dataset.date + "T12:00:00") : null,
        );
    }

    handleCalendarLeave() {
        this.applyHoverRange(null);
    }

    applyHoverRange(hovered) {
        this.calendarTarget
            .querySelectorAll("[data-date].hover-range")
            .forEach((el) => {
                el.classList.remove("hover-range", "bg-gray-100");
            });

        if (
            !this.checkIn ||
            this.checkOut ||
            !hovered ||
            hovered <= this.checkIn
        )
            return;
        if (this.cutoffDate && hovered >= this.cutoffDate) return;

        this.calendarTarget.querySelectorAll("[data-date]").forEach((el) => {
            const d = new Date(el.dataset.date + "T12:00:00");
            if (d > this.checkIn && d < hovered) {
                el.classList.add("hover-range", "bg-gray-100");
            }
        });
    }

    async fetchCalendar() {
        const params = new URLSearchParams({
            year: this.viewDate.getFullYear(),
            month: this.viewDate.getMonth() + 1,
        });
        if (this.checkIn) params.set("checkin", this.toDateStr(this.checkIn));
        if (this.checkOut)
            params.set("checkout", this.toDateStr(this.checkOut));

        try {
            const res = await fetch(`${this.calendarUrlValue}?${params}`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            });
            if (res.ok) this.calendarTarget.innerHTML = await res.text();
        } catch (_) {}
    }

    computeCutoff() {
        this.cutoffDate = null;
        if (!this.checkIn || this.checkOut) return;
        const cursor = new Date(this.checkIn);
        cursor.setDate(cursor.getDate() + 1);
        for (let i = 0; i < 730; i++) {
            if (this.blockedSet.has(this.toDateStr(cursor))) {
                this.cutoffDate = new Date(cursor);
                return;
            }
            cursor.setDate(cursor.getDate() + 1);
        }
    }

    updateModalDates() {
        this.modalCheckinTarget.textContent = this.checkIn
            ? this.formatDate(this.checkIn)
            : "Sélectionner";
        this.modalCheckoutTarget.textContent = this.checkOut
            ? this.formatDate(this.checkOut)
            : "Sélectionner";
    }

    updatePricing() {
        if (!this.checkIn || !this.checkOut) {
            this.hintTarget.classList.remove("hidden");
            this.priceBreakdownTarget.classList.add("hidden");
            this.selectBtnTarget.disabled = true;
            return;
        }

        const nights = Math.round((this.checkOut - this.checkIn) / 86400000);
        const priceNights = nights * this.pricePerNightValue;
        const cleaning = this.cleaningFeeValue || 0;

        this.nightsTarget.textContent = `${this.pricePerNightValue.toLocaleString("fr-FR")} € × ${nights} nuit${nights > 1 ? "s" : ""}`;
        this.nightsAmountTarget.textContent = `${priceNights.toLocaleString("fr-FR")} €`;
        this.totalTarget.textContent = `${(priceNights + cleaning).toLocaleString("fr-FR")} €`;

        if (cleaning > 0) {
            this.cleaningFeeRowTarget.classList.remove("hidden");
            this.cleaningFeeAmountTarget.textContent = `${cleaning.toLocaleString("fr-FR")} €`;
        } else {
            this.cleaningFeeRowTarget.classList.add("hidden");
        }

        this.hintTarget.classList.add("hidden");
        this.priceBreakdownTarget.classList.remove("hidden");
        this.selectBtnTarget.disabled = false;
    }

    formatDate(date) {
        return date.toLocaleDateString("fr-FR", {
            day: "numeric",
            month: "short",
        });
    }

    toDateStr(date) {
        const m = String(date.getMonth() + 1).padStart(2, "0");
        const d = String(date.getDate()).padStart(2, "0");
        return `${date.getFullYear()}-${m}-${d}`;
    }
}
