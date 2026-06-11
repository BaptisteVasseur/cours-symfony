import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

export default class extends Controller {
    static values = { unavailableCheckin: Array, unavailableCheckout: Array };

    connect() {
        const unavailable = this.unavailableCheckinValue;
        const unavailableCheckout = this.unavailableCheckoutValue;
        const unavailableSet = new Set(unavailable);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const checkinInput = this.element.querySelector('[data-datepicker-checkin]');
        const checkoutInput = this.element.querySelector('[data-datepicker-checkout]');

        const onDayCreate = (_dObj, _dStr, _fp, dayElem) => {
            const iso = dayElem.dateObj.toISOString().slice(0, 10);
            if (unavailableSet.has(iso)) {
                dayElem.title = 'Date indisponible';
            }
        };

        const addIcon = (fp) => {
            const altInput = fp.altInput;
            if (!altInput) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'relative';
            altInput.parentNode.insertBefore(wrapper, altInput);
            wrapper.appendChild(altInput);
            const icon = document.createElement('span');
            icon.innerHTML = '<i class="fa-regular fa-calendar"></i>';
            icon.className = 'pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm';
            wrapper.appendChild(icon);
        };

        const checkinPicker = flatpickr(checkinInput, {
            minDate: today,
            disable: unavailable,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            locale: { firstDayOfWeek: 1 },
            onDayCreate,
            onReady: (_d, _s, fp) => addIcon(fp),
            onClose: (selectedDates) => {
                if (selectedDates[0]) {
                    const next = new Date(selectedDates[0]);
                    next.setDate(next.getDate() + 1);
                    checkoutPicker.set('minDate', next);
                    checkoutPicker.open();
                }
            },
        });

        const checkoutPicker = flatpickr(checkoutInput, {
            minDate: today,
            disable: unavailableCheckout,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            locale: { firstDayOfWeek: 1 },
            onDayCreate,
            onReady: (_d, _s, fp) => addIcon(fp),
        });

        void checkinPicker;
    }
}
