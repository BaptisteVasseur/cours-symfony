import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['slide', 'counter']

    connect() {
        this.current = 0
        this._update()
    }

    next() {
        this.current = (this.current + 1) % this.slideTargets.length
        this._update()
    }

    previous() {
        this.current = (this.current - 1 + this.slideTargets.length) % this.slideTargets.length
        this._update()
    }

    _update() {
        this.slideTargets.forEach((slide, i) => {
            slide.hidden = i !== this.current
        })
        if (this.hasCounterTarget) {
            this.counterTarget.textContent = `${this.current + 1} / ${this.slideTargets.length}`
        }
    }
}
