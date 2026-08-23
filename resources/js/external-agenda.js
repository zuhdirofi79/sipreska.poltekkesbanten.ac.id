export function registerExternalAgendaAlpine(Alpine) {
    Alpine.data('externalActivityDates', (initialDates = [''], lockedDates = []) => ({
        dates: initialDates.length ? [...initialDates] : [''],
        lockedDates: lockedDates,

        add() {
            this.dates.push('');
        },

        remove(index) {
            if (! this.canRemove(index)) {
                return;
            }

            this.dates.splice(index, 1);
        },

        isLocked(date) {
            return date !== '' && this.lockedDates.includes(date);
        },

        canRemove(index) {
            if (this.dates.length <= 1) {
                return false;
            }

            return ! this.isLocked(this.dates[index]);
        },
    }));

    Alpine.data('externalShareLink', (url = '') => ({
        url,
        copied: false,

        async copy() {
            if (! this.url) {
                return;
            }

            try {
                await navigator.clipboard.writeText(this.url);
            } catch {
                this.$refs.urlInput?.select();
                document.execCommand('copy');
            }

            this.copied = true;
            setTimeout(() => {
                this.copied = false;
            }, 2000);
        },
    }));

    Alpine.data('externalAttendanceForm', () => ({
        drawing: false,
        hasSignature: false,
        isSubmitting: false,
        modalOpen: false,
        signatureError: '',
        dateError: '',

        init() {
            this.$watch('modalOpen', (open) => {
                document.body.classList.toggle('overflow-hidden', open);
            });

            this.$nextTick(() => {
                const canvas = this.$refs.canvas;
                if (! canvas) return;

                const ctx = canvas.getContext('2d');
                ctx.strokeStyle = '#111827';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                this.ctx = ctx;
                this.canvas = canvas;
            });
        },

        getForm() {
            return this.$refs.form ?? this.$el;
        },

        selectedDateCount() {
            return this.getForm()?.querySelectorAll('input[name="attendance_dates[]"]:checked').length ?? 0;
        },

        validateDates() {
            if (this.selectedDateCount() < 1) {
                this.dateError = 'Minimal satu tanggal kehadiran wajib dipilih.';

                return false;
            }

            this.dateError = '';

            return true;
        },

        validateSignature() {
            if (! this.hasSignature) {
                this.signatureError = 'Tanda tangan wajib diisi.';
                this.$refs.canvas?.focus();

                return false;
            }

            this.signatureError = '';

            return true;
        },

        requestOpenConfirm() {
            if (this.isSubmitting) {
                return;
            }

            const form = this.getForm();

            if (! form.checkValidity()) {
                form.reportValidity();

                return;
            }

            if (! this.validateDates() || ! this.validateSignature()) {
                return;
            }

            this.modalOpen = true;
            this.$nextTick(() => {
                this.$refs.confirmYes?.focus();
            });
        },

        closeModal() {
            if (this.isSubmitting) {
                return;
            }

            this.modalOpen = false;
            this.$nextTick(() => {
                this.$refs.submitTrigger?.focus();
            });
        },

        handleEscape() {
            if (this.isSubmitting || ! this.modalOpen) {
                return;
            }

            this.closeModal();
        },

        confirmSubmit() {
            if (this.isSubmitting) {
                return;
            }

            const form = this.getForm();

            if (! form.checkValidity() || ! this.validateDates() || ! this.validateSignature()) {
                this.closeModal();

                return;
            }

            this.isSubmitting = true;

            if (this.$refs.canvas && this.$refs.signatureInput) {
                this.$refs.signatureInput.value = this.canvas.toDataURL('image/png');
            }

            form.requestSubmit(this.$refs.hiddenSubmit);
        },

        handleFormSubmit(event) {
            if (! this.isSubmitting) {
                event.preventDefault();
            }
        },

        getPoint(event) {
            const rect = this.canvas.getBoundingClientRect();
            const scaleX = this.canvas.width / rect.width;
            const scaleY = this.canvas.height / rect.height;
            const clientX = event.touches ? event.touches[0].clientX : event.clientX;
            const clientY = event.touches ? event.touches[0].clientY : event.clientY;

            return {
                x: (clientX - rect.left) * scaleX,
                y: (clientY - rect.top) * scaleY,
            };
        },

        startDraw(event) {
            if (! this.ctx) return;
            this.drawing = true;
            const point = this.getPoint(event);
            this.ctx.beginPath();
            this.ctx.moveTo(point.x, point.y);
        },

        draw(event) {
            if (! this.drawing || ! this.ctx) return;
            const point = this.getPoint(event);
            this.ctx.lineTo(point.x, point.y);
            this.ctx.stroke();
            this.hasSignature = true;
            this.signatureError = '';
        },

        endDraw() {
            this.drawing = false;
        },

        clear() {
            if (! this.ctx || ! this.canvas) return;
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            this.hasSignature = false;
            if (this.$refs.signatureInput) {
                this.$refs.signatureInput.value = '';
            }
        },
    }));
}
