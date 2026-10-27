// Levy Branzuela Salon — Booking System
document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('.booking-container')) {
        window.salonBooking = new SalonBookingSystem();
    }
});

class SalonBookingSystem {
    constructor() {
        this.currentStep   = 1;
        this.totalSteps    = 4;
        this.bookedSlots   = [];   // time strings blocked for the selected date e.g. ["10:00:00"]
        this.bookingData   = {
            services     : [],
            totalPrice   : 0,
            totalDuration: 0,
            dateTime     : {},
            personalInfo : {},
            paymentMethod: 'salon'
        };
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupDatePicker();
        this.generateTimeSlots();
        this.updateProgressTabs();
    }

    // ── EVENT LISTENERS ──────────────────────────────────────────────────────
    setupEventListeners() {
        // Next buttons
        document.querySelectorAll('.next-step').forEach(btn => {
            btn.addEventListener('click', () => {
                const next = parseInt(btn.dataset.next);
                if (this.validateCurrentStep()) this.goToStep(next);
            });
        });

        // Prev buttons
        document.querySelectorAll('.prev-step').forEach(btn => {
            btn.addEventListener('click', () => this.goToStep(parseInt(btn.dataset.prev)));
        });

        // Confirm button
        const confirmBtn = document.getElementById('confirm-booking-btn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.validateCurrentStep()) this.confirmBooking();
            });
        }

        // Tab navigation
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const step = parseInt(tab.dataset.step);
                if (step < this.currentStep) this.goToStep(step);
                else if (step === this.currentStep + 1 && this.validateCurrentStep()) this.goToStep(step);
            });
        });

        // Service checkboxes (multi-select)
        document.querySelectorAll('input[name="service"]').forEach(cb => {
            cb.addEventListener('change', () => this.handleServiceChange());
        });

        // Date input — fetch booked slots whenever date changes
        const dateInput = document.getElementById('booking-date');
        if (dateInput) {
            dateInput.addEventListener('change', async (e) => {
                const dateVal = e.target.value;

                // Guard: reject any date before today (browser min attr doesn't grey out past dates)
                if (dateVal) {
                    const [y, mo, d] = dateVal.split('-').map(Number);
                    const picked = new Date(y, mo - 1, d); // local midnight
                    const today  = new Date();
                    today.setHours(0, 0, 0, 0);            // local midnight
                    if (picked < today) {
                        e.target.value = '';
                        this.showAlert('Please select today or a future date.', 'error');
                        this.bookingData.dateTime = {};
                        document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                        this.updateStep3Summary();
                        return;
                    }
                }

                this.bookingData.dateTime.date          = dateVal;
                this.bookingData.dateTime.formattedDate = this.formatDate(dateVal);

                // Clear previously selected time slot when date changes
                this.bookingData.dateTime.time   = null;
                this.bookingData.dateTime.time24 = null;
                document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));

                this.updateStep3Summary();

                // Fetch booked slots and refresh the time grid
                await this.fetchBookedSlots(dateVal);
                this.applyBookedSlots();
            });
        }

        // Form field validation
        document.querySelectorAll('input[required]').forEach(input => {
            input.addEventListener('blur',  () => this.validateInput(input));
            input.addEventListener('input', () => this.clearError(input));
        });
    }

    // ── SERVICE SELECTION ────────────────────────────────────────────────────
    handleServiceChange() {
        const checked = document.querySelectorAll('input[name="service"]:checked');

        this.bookingData.services = Array.from(checked).map(cb => ({
            id      : cb.value,
            name    : cb.dataset.name,
            price   : parseFloat(cb.dataset.price),
            duration: parseInt(cb.dataset.duration)
        }));

        this.bookingData.totalPrice    = this.bookingData.services.reduce((s, x) => s + x.price,    0);
        this.bookingData.totalDuration = this.bookingData.services.reduce((s, x) => s + x.duration, 0);

        this.updateStep3Summary();
    }

    // ── AVAILABILITY CHECK ───────────────────────────────────────────────────
    async fetchBookedSlots(date) {
        const loader = document.getElementById('time-slots-loading');
        if (loader) loader.style.display = 'block';

        try {
            const res  = await fetch('check_availability.php?date=' + encodeURIComponent(date));
            const data = await res.json();
            this.bookedSlots = data.booked || [];
        } catch (err) {
            console.warn('Could not fetch availability:', err);
            this.bookedSlots = [];
        } finally {
            if (loader) loader.style.display = 'none';
        }
    }

    applyBookedSlots() {
        document.querySelectorAll('.time-slot').forEach(slot => {
            const time24 = slot.dataset.time24;   // e.g. "10:00:00"
            if (this.bookedSlots.includes(time24)) {
                slot.classList.add('booked');
                slot.classList.remove('selected');
            } else {
                slot.classList.remove('booked');
            }
        });
    }

    // ── STEP NAVIGATION ──────────────────────────────────────────────────────
    goToStep(step) {
        document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
        const target = document.getElementById('step-' + step);
        if (target) target.classList.add('active');
        this.currentStep = step;
        this.updateProgressTabs();

        if (step === 3) this.updateStep3Summary();
        if (step === 4) this.updateStep4Summary();

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    updateProgressTabs() {
        document.querySelectorAll('.tab').forEach((tab, i) => {
            const n = i + 1;
            tab.classList.remove('active', 'completed');
            if      (n < this.currentStep)  tab.classList.add('completed');
            else if (n === this.currentStep) tab.classList.add('active');
        });
    }

    // ── VALIDATION ───────────────────────────────────────────────────────────
    validateCurrentStep() {
        switch (this.currentStep) {
            case 1: {
                if (this.bookingData.services.length === 0) {
                    this.showAlert('Please select at least one service.', 'error');
                    return false;
                }
                return true;
            }
            case 2: {
                const date = document.getElementById('booking-date').value;
                const time = document.querySelector('.time-slot.selected');
                if (!date) { this.showAlert('Please select a date.',      'error'); return false; }
                if (!time) { this.showAlert('Please select a time slot.', 'error'); return false; }

                // Double-check the selected slot hasn't become booked since the page loaded
                if (time.classList.contains('booked')) {
                    this.showAlert('That time slot is no longer available. Please pick a different time.', 'error');
                    time.classList.remove('selected');
                    return false;
                }

                this.bookingData.dateTime = {
                    date         : date,
                    time         : time.textContent.replace('Unavailable','').trim(),
                    time24       : time.dataset.time24,
                    formattedDate: this.formatDate(date)
                };
                return true;
            }
            case 3: {
                let valid = true;
                ['full-name', 'email', 'phone'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el && el.type !== 'hidden' && !el.value.trim()) {
                        this.showError(el, 'This field is required');
                        valid = false;
                    }
                });
                if (!valid) return false;
                this.bookingData.personalInfo = {
                    fullName: document.getElementById('full-name').value.trim(),
                    email   : document.getElementById('email').value.trim(),
                    phone   : document.getElementById('phone').value.trim()
                };
                return true;
            }
        }
        return true;
    }

    validateInput(input) {
        const val = input.value.trim();
        if (!val && input.required) { this.showError(input, 'This field is required'); return false; }
        if (input.type === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            this.showError(input, 'Please enter a valid email address'); return false;
        }
        return true;
    }

    showError(input, msg) {
        this.clearError(input);
        input.style.borderColor     = '#dc3545';
        input.style.backgroundColor = '#fff5f5';
        const err = document.createElement('div');
        err.className = 'field-error';
        err.style.cssText = 'color:#dc3545;font-size:.85rem;margin-top:.25rem;';
        err.textContent = msg;
        input.parentNode.appendChild(err);
    }

    clearError(input) {
        input.style.borderColor     = '';
        input.style.backgroundColor = '';
        const err = input.parentNode.querySelector('.field-error');
        if (err) err.remove();
    }

    // ── SUMMARY UPDATES ──────────────────────────────────────────────────────
    updateStep3Summary() {
        const names = this.bookingData.services.map(s => s.name).join(', ') || '-';
        const el    = id => document.getElementById(id);

        if (el('summary-service'))        el('summary-service').textContent        = names;
        if (el('summary-total-duration')) el('summary-total-duration').textContent = this.bookingData.totalDuration + ' mins';
        if (el('summary-price'))          el('summary-price').textContent          =
            '₱' + this.bookingData.totalPrice.toLocaleString('en-PH', { minimumFractionDigits: 2 });

        if (this.bookingData.dateTime.formattedDate && this.bookingData.dateTime.time) {
            if (el('summary-datetime'))
                el('summary-datetime').textContent =
                    this.bookingData.dateTime.formattedDate + ' at ' + this.bookingData.dateTime.time;
        }
    }

    updateStep4Summary() {
        const names = this.bookingData.services.map(s => s.name).join(', ') || '-';
        const el    = id => document.getElementById(id);

        if (el('final-service'))  el('final-service').textContent  = names;
        if (el('final-duration')) el('final-duration').textContent = this.bookingData.totalDuration + ' mins';
        if (el('final-datetime')) el('final-datetime').textContent =
            (this.bookingData.dateTime.formattedDate || '') + ' at ' + (this.bookingData.dateTime.time || '');
        if (el('final-price'))    el('final-price').textContent    =
            '₱' + this.bookingData.totalPrice.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    }

    setupDatePicker() {
        const input = document.getElementById('booking-date');
        if (!input) return;

        // Use local date parts to avoid UTC timezone shifts (e.g. UTC+8)
        // causing yesterday's date to remain selectable
        const pad = n => String(n).padStart(2, '0');
        const toLocalDateStr = d =>
            d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());

        const today = new Date();
        today.setHours(0, 0, 0, 0); // midnight local time
        const max = new Date(today);
        max.setDate(max.getDate() + 60);

        input.min = toLocalDateStr(today);
        input.max = toLocalDateStr(max);
    }

    // ── TIME SLOTS ───────────────────────────────────────────────────────────
    generateTimeSlots() {
        const container = document.getElementById('time-slots');
        if (!container) return;
        container.innerHTML = '';

        for (let h = 9; h <= 19; h++) {
            const label = this.formatTime(h, 0);
            const time24 = String(h).padStart(2, '0') + ':00:00';

            const slot = document.createElement('div');
            slot.className      = 'time-slot';
            slot.textContent    = label;
            slot.dataset.time24 = time24;

            slot.addEventListener('click', () => {
                // Prevent selecting a booked slot
                if (slot.classList.contains('booked')) {
                    this.showAlert(
                        'This time slot is already booked. Please choose a different time.',
                        'error'
                    );
                    return;
                }
                document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                slot.classList.add('selected');
                this.bookingData.dateTime.time   = label;
                this.bookingData.dateTime.time24 = time24;
                this.updateStep3Summary();
            });

            container.appendChild(slot);
        }
    }

    formatTime(h, m) {
        const period = h >= 12 ? 'PM' : 'AM';
        const dh     = h > 12 ? h - 12 : (h === 0 ? 12 : h);
        return dh + ':' + String(m).padStart(2, '0') + ' ' + period;
    }

    formatDate(str) {
        // Parse as local date to avoid timezone shifts
        const [y, mo, d] = str.split('-').map(Number);
        const date = new Date(y, mo - 1, d);
        return date.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    }

    // ── CONFIRM BOOKING ──────────────────────────────────────────────────────
    async confirmBooking() {
        this.showLoading();
        try {
            const payload = {
                fullName     : this.bookingData.personalInfo.fullName,
                email        : this.bookingData.personalInfo.email,
                phone        : this.bookingData.personalInfo.phone,
                services     : this.bookingData.services,
                totalPrice   : this.bookingData.totalPrice,
                totalDuration: this.bookingData.totalDuration,
                bookingDate  : this.bookingData.dateTime.date,
                bookingTime  : this.bookingData.dateTime.time,
                bookingTime24: this.bookingData.dateTime.time24,
                paymentMethod: this.bookingData.paymentMethod
            };

            const response = await fetch('process_booking.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body   : JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('booking-reference').textContent  = result.bookingReference;
                document.getElementById('confirmation-email').textContent = this.bookingData.personalInfo.email;
                this.goToStep(4);
            } else {
                // Slot conflict reported by server — re-fetch and grey out
                if (result.conflict) {
                    await this.fetchBookedSlots(this.bookingData.dateTime.date);
                    this.applyBookedSlots();
                    // Deselect the conflicting slot
                    document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
                    this.bookingData.dateTime.time   = null;
                    this.bookingData.dateTime.time24 = null;
                }
                throw new Error(result.message || 'Failed to process booking');
            }
        } catch (err) {
            this.showAlert(err.message || 'Error processing booking. Please try again.', 'error');
        } finally {
            this.hideLoading();
        }
    }

    // ── LOADING ──────────────────────────────────────────────────────────────
    showLoading() { const o = document.getElementById('loading-overlay'); if (o) o.style.display = 'flex'; }
    hideLoading() { const o = document.getElementById('loading-overlay'); if (o) o.style.display = 'none'; }

    // ── TOAST ALERT ──────────────────────────────────────────────────────────
    showAlert(msg, type = 'info') {
        const colors = { error: '#dc3545', success: '#28a745', info: '#17a2b8' };
        const div    = document.createElement('div');
        div.textContent = msg;
        div.style.cssText = `
            position:fixed;top:20px;right:20px;padding:1rem 1.5rem;border-radius:8px;
            color:white;font-weight:500;z-index:10000;min-width:300px;max-width:400px;
            box-shadow:0 4px 20px rgba(0,0,0,.25);line-height:1.5;
            background:${colors[type] || colors.info};
            transform:translateX(120%);transition:transform .3s ease;
        `;
        document.body.appendChild(div);
        setTimeout(() => div.style.transform = 'translateX(0)',    50);
        setTimeout(() => {
            div.style.transform = 'translateX(120%)';
            setTimeout(() => div.remove(), 350);
        }, 5000);
    }
}