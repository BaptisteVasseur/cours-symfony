import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

export default class extends Controller {
    connect() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const startInput = this.element.querySelector('[data-simpledatepicker-start]');
        const endInput = this.element.querySelector('[data-simpledatepicker-end]');

        if (!startInput || !endInput) return;

        flatpickr(startInput, {
            minDate: today,
            dateFormat: 'Y-m-d',
            locale: { firstDayOfWeek: 1 },
            onClose: (selectedDates) => {
                if (selectedDates[0]) {
                    const next = new Date(selectedDates[0]);
                    next.setDate(next.getDate() + 1);
                    endPicker.set('minDate', next);
                }
            },
        });

        const endPicker = flatpickr(endInput, {
            minDate: today,
            dateFormat: 'Y-m-d',
            locale: { firstDayOfWeek: 1 },
        });
    }
}
