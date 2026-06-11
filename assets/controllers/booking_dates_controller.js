import { Controller } from '@hotwired/stimulus'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.min.css'

export default class extends Controller {
    static targets = ['checkin', 'checkout', 'summary']
    static values  = {
        bookedRanges:  Array,
        pricePerNight: Number,
        cleaningFee:   Number,
    }

    connect() {
        const disabledRanges = this.bookedRangesValue

        // Checkin picker
        const checkinPicker = flatpickr(this.checkinTarget, {
            dateFormat:    'Y-m-d',
            minDate:       'today',
            disable:       disabledRanges,
            disableMobile: true,
            onChange: ([selectedDate]) => {
                if (!selectedDate) return

                // Checkout must be at least the next day
                const minCheckout = new Date(selectedDate)
                minCheckout.setDate(minCheckout.getDate() + 1)
                checkoutPicker.set('minDate', minCheckout)

                // Checkout can go at most up to the day before the next booked range
                const maxCheckout = this._nextBlockStart(selectedDate, disabledRanges)
                checkoutPicker.set('maxDate', maxCheckout ?? null)

                // Reset checkout if it's now invalid
                const current = checkoutPicker.selectedDates[0]
                if (current && (current <= selectedDate || (maxCheckout && current > maxCheckout))) {
                    checkoutPicker.clear()
                }

                this._updateSummary(selectedDate, checkoutPicker.selectedDates[0])
            },
        })

        // Checkout picker
        const checkoutPicker = flatpickr(this.checkoutTarget, {
            dateFormat:    'Y-m-d',
            minDate:       new Date(Date.now() + 86_400_000),
            disable:       disabledRanges,
            disableMobile: true,
            onChange: ([selectedDate]) => {
                this._updateSummary(checkinPicker.selectedDates[0], selectedDate)
            },
        })
    }

    _updateSummary(checkin, checkout) {
        if (!this.hasSummaryTarget) return

        if (!checkin || !checkout) {
            this.summaryTarget.innerHTML = ''
            return
        }

        const pricePerNight = this.pricePerNightValue
        const cleaningFee   = this.cleaningFeeValue

        const nights   = Math.round((checkout - checkin) / 86400000)
        const subtotal = nights * pricePerNight
        const cleaning = cleaningFee
        const service  = Math.round(subtotal * 0.12 * 100) / 100
        const total    = subtotal + cleaning + service

        const fmt = (n) => n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

        this.summaryTarget.innerHTML = `
<div class="space-y-2 text-sm border-t border-gray-200 pt-3 mt-3">
  <div class="flex justify-between"><span>${nights} nuit(s) × ${fmt(pricePerNight)} €</span><span>${fmt(subtotal)} €</span></div>
  <div class="flex justify-between text-gray-500"><span>Frais de ménage</span><span>${fmt(cleaning)} €</span></div>
  <div class="flex justify-between text-gray-500"><span>Frais de service (12%)</span><span>${fmt(service)} €</span></div>
  <div class="flex justify-between font-bold border-t border-gray-200 pt-2 mt-2"><span>Total</span><span>${fmt(total)} €</span></div>
</div>`
    }

    // Returns the first day of the nearest booked range that starts after `date`
    _nextBlockStart(date, ranges) {
        let nearest = null
        for (const range of ranges) {
            const from = new Date(range.from)
            if (from > date && (nearest === null || from < nearest)) {
                nearest = from
            }
        }
        return nearest
    }
}
