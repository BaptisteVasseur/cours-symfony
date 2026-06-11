import { Controller } from '@hotwired/stimulus'
import flatpickr from 'flatpickr'
import 'flatpickr/dist/flatpickr.min.css'

export default class extends Controller {
    static targets = ['checkin', 'checkout']
    static values  = { bookedRanges: Array }

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
            },
        })

        // Checkout picker
        const checkoutPicker = flatpickr(this.checkoutTarget, {
            dateFormat:    'Y-m-d',
            minDate:       new Date(Date.now() + 86_400_000),
            disable:       disabledRanges,
            disableMobile: true,
        })
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
