import Alpine from 'alpinejs';
import { registerExternalAgendaAlpine } from './external-agenda';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    const attendanceConfirmationFactory = (options = {}) => ({
        drawing: false,
        hasSignature: false,
        isSubmitting: false,
        modalOpen: false,
        signatureError: '',
        required: options.required ?? true,
        showInstitution: false,

        init() {
            this.$watch('modalOpen', (open) => {
                document.body.classList.toggle('overflow-hidden', open);
            });

            this.$nextTick(() => {
                const canvas = this.$refs.canvas;
                if (!canvas) return;

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

        validateSignature() {
            if (!this.required || !this.$refs.canvas) {
                this.signatureError = '';

                return true;
            }

            if (!this.hasSignature) {
                this.signatureError = 'Tanda tangan wajib diisi.';
                this.$refs.canvas.focus();

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

            if (!form.checkValidity()) {
                form.reportValidity();

                return;
            }

            if (!this.validateSignature()) {
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
            if (this.isSubmitting || !this.modalOpen) {
                return;
            }

            this.closeModal();
        },

        confirmSubmit() {
            if (this.isSubmitting) {
                return;
            }

            const form = this.getForm();

            if (!form.checkValidity()) {
                form.reportValidity();
                this.closeModal();

                return;
            }

            if (!this.validateSignature()) {
                this.closeModal();

                return;
            }

            this.isSubmitting = true;

            if (this.required && this.$refs.canvas && this.$refs.signatureInput) {
                this.$refs.signatureInput.value = this.canvas.toDataURL('image/png');
            }

            form.requestSubmit(this.$refs.hiddenSubmit);
        },

        handleFormSubmit(event) {
            if (!this.isSubmitting) {
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
            if (!this.ctx) return;
            this.drawing = true;
            const point = this.getPoint(event);
            this.ctx.beginPath();
            this.ctx.moveTo(point.x, point.y);
        },

        draw(event) {
            if (!this.drawing || !this.ctx) return;
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
            if (!this.ctx || !this.canvas) return;
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            this.hasSignature = false;
            if (this.$refs.signatureInput) {
                this.$refs.signatureInput.value = '';
            }
        },

    });

    Alpine.data('attendanceConfirmation', attendanceConfirmationFactory);
    Alpine.data('signaturePad', attendanceConfirmationFactory);

    Alpine.data('calendarView', (events = [], initialView = 'month') => ({
        events: events,
        view: initialView,
        cursor: new Date(),

        init() {
            this.normalizeEvents();
        },

        normalizeEvents() {
            this.events = (this.events || []).map((event) => ({
                ...event,
                date: event.date ? new Date(event.date + 'T00:00:00') : new Date(),
            }));
        },

        get titleLabel() {
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            if (this.view === 'day') {
                return this.cursor.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            }
            if (this.view === 'week') {
                const start = this.startOfWeek(this.cursor);
                const end = new Date(start);
                end.setDate(end.getDate() + 6);
                return `${start.getDate()} – ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear()}`;
            }
            return `${months[this.cursor.getMonth()]} ${this.cursor.getFullYear()}`;
        },

        get sortedEvents() {
            return [...this.events].sort((a, b) => a.date - b.date);
        },

        get dayEvents() {
            return this.eventsForDate(this.cursor);
        },

        get monthCells() {
            const year = this.cursor.getFullYear();
            const month = this.cursor.getMonth();
            const firstDay = new Date(year, month, 1);
            const start = this.startOfWeek(firstDay);
            const cells = [];
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            for (let i = 0; i < 42; i++) {
                const date = new Date(start);
                date.setDate(start.getDate() + i);
                const key = date.toISOString().slice(0, 10);
                cells.push({
                    key,
                    day: date.getDate(),
                    inMonth: date.getMonth() === month,
                    isToday: date.getTime() === today.getTime(),
                    events: this.eventsForDate(date),
                });
            }

            return cells;
        },

        get weekCells() {
            const start = this.startOfWeek(this.cursor);
            const labels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
            const cells = [];

            for (let i = 0; i < 7; i++) {
                const date = new Date(start);
                date.setDate(start.getDate() + i);
                cells.push({
                    key: date.toISOString().slice(0, 10),
                    label: labels[i],
                    day: date.getDate(),
                    events: this.eventsForDate(date),
                });
            }

            return cells;
        },

        startOfWeek(date) {
            const result = new Date(date);
            result.setHours(0, 0, 0, 0);
            result.setDate(result.getDate() - result.getDay());
            return result;
        },

        eventsForDate(date) {
            const key = date.toISOString().slice(0, 10);
            return this.events.filter((event) => {
                const eventKey = event.date.toISOString().slice(0, 10);
                return eventKey === key;
            });
        },

        setView(view) {
            this.view = view;
        },

        prev() {
            const next = new Date(this.cursor);
            if (this.view === 'month') next.setMonth(next.getMonth() - 1);
            else if (this.view === 'week') next.setDate(next.getDate() - 7);
            else next.setDate(next.getDate() - 1);
            this.cursor = next;
        },

        next() {
            const next = new Date(this.cursor);
            if (this.view === 'month') next.setMonth(next.getMonth() + 1);
            else if (this.view === 'week') next.setDate(next.getDate() + 7);
            else next.setDate(next.getDate() + 1);
            this.cursor = next;
        },

        goToday() {
            this.cursor = new Date();
        },
    }));

    Alpine.data('attendanceSharePanel', (config = {}) => ({
        publicUrl: config.publicUrl ?? '',
        activityTitle: config.activityTitle ?? '',
        isOpen: config.isOpen ?? false,
        isAccepting: config.isAccepting ?? false,
        isClosedByTime: config.isClosedByTime ?? false,
        isNotYetOpen: config.isNotYetOpen ?? false,
        qrImageUrl: config.qrImageUrl ?? '',
        whatsappUrl: config.whatsappUrl ?? '',

        copied: false,
        toast: null,
        toastTimer: null,
        qrOpen: false,
        copyResetTimer: null,

        get copyDisabled() {
            return !this.isOpen;
        },

        get subtitle() {
            if (!this.isOpen) {
                return 'Form absensi belum aktif. Aktifkan form terlebih dahulu sebelum membagikannya kepada peserta.';
            }

            if (this.isAccepting) {
                return 'Form absensi sudah aktif. Silakan bagikan tautan berikut kepada peserta kegiatan.';
            }

            if (this.isClosedByTime) {
                return 'Form absensi sudah ditutup. Tautan masih dapat disalin untuk arsip atau pratinjau.';
            }

            if (this.isNotYetOpen) {
                return 'Form absensi sudah diaktifkan tetapi belum dibuka sesuai jadwal. Anda dapat membagikan tautan sebelum waktu pembukaan.';
            }

            return 'Form absensi sudah aktif. Silakan bagikan tautan berikut kepada peserta kegiatan.';
        },

        init() {
            this.$watch('qrOpen', (open) => {
                document.body.classList.toggle('overflow-hidden', open);
            });
        },

        async copyLink() {
            if (this.copyDisabled) {
                return;
            }

            const url = this.publicUrl;
            const copied = await this.copyText(url);

            if (copied) {
                this.copied = true;
                this.showToast('success', 'Link absensi berhasil disalin. Silakan bagikan kepada peserta.');

                clearTimeout(this.copyResetTimer);
                this.copyResetTimer = setTimeout(() => {
                    this.copied = false;
                }, 2500);
            } else {
                this.focusUrlInput();
                this.showToast('error', 'Link gagal disalin otomatis. Silakan salin tautan secara manual.');
            }
        },

        async copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(text);
                    return true;
                } catch {
                    // fall through to legacy copy
                }
            }

            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                textarea.style.top = '0';
                document.body.appendChild(textarea);
                textarea.select();
                textarea.setSelectionRange(0, text.length);
                const ok = document.execCommand('copy');
                document.body.removeChild(textarea);

                if (ok) {
                    return true;
                }
            } catch {
                // fall through to manual selection
            }

            return false;
        },

        focusUrlInput() {
            const input = this.$refs.publicUrlInput;
            if (!input) {
                return;
            }

            input.focus();
            input.select();

            this.showToast('info', 'Tekan Ctrl+C untuk menyalin tautan.');
        },

        showToast(type, message) {
            clearTimeout(this.toastTimer);
            this.toast = { type, message };

            this.toastTimer = setTimeout(() => {
                this.toast = null;
            }, 4000);
        },

        openQrModal() {
            if (this.copyDisabled) {
                return;
            }

            this.qrOpen = true;
        },

        closeQrModal() {
            this.qrOpen = false;
        },

        downloadQr() {
            const link = document.createElement('a');
            link.href = this.qrImageUrl;
            link.download = `qr-absensi-${this.slugify(this.activityTitle)}.png`;
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        printQr() {
            const printWindow = window.open('', '_blank', 'noopener,noreferrer');
            if (!printWindow) {
                this.showToast('error', 'Popup diblokir. Izinkan popup.');
                return;
            }

            const title = this.activityTitle.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const url = this.publicUrl.replace(/</g, '&lt;').replace(/>/g, '&gt;');

            printWindow.document.write(`
                <!DOCTYPE html>
                <html lang="id">
                <head>
                    <meta charset="UTF-8">
                    <title>QR Absensi - ${title}</title>
                    <style>
                        body { font-family: sans-serif; text-align: center; padding: 2rem; }
                        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
                        p { color: #4b5563; font-size: 0.875rem; word-break: break-all; }
                        img { margin: 1.5rem auto; max-width: 280px; }
                    </style>
                </head>
                <body>
                    <h1>${title}</h1>
                    <p>Scan QR Code untuk mengisi daftar hadir</p>
                    <img src="${this.qrImageUrl}" alt="QR Code absensi">
                    <p>${url}</p>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        },

        slugify(value) {
            return String(value)
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .slice(0, 40) || 'kegiatan';
        },
    }));

    Alpine.data('attendanceQrPanel', (config = {}) => ({
        publicUrl: config.publicUrl ?? '',
        activityTitle: config.activityTitle ?? '',
        activitySlug: config.activitySlug ?? 'kegiatan',
        qrPreviewUrl: config.qrPreviewUrl ?? '',
        qrDownloadUrl: config.qrDownloadUrl ?? '',
        whatsappUrl: config.whatsappUrl ?? '',

        qrGenerated: false,
        qrPreviewSrc: '',
        shareModalOpen: false,
        copied: false,
        copyResetTimer: null,

        get downloadFilename() {
            return `qr-daftar-hadir-${this.activitySlug}.png`;
        },

        init() {
            this.$watch('shareModalOpen', (open) => {
                document.body.classList.toggle('overflow-hidden', open);
            });
        },

        generateQr() {
            const cacheBuster = Date.now();
            const separator = this.qrPreviewUrl.includes('?') ? '&' : '?';
            this.qrPreviewSrc = `${this.qrPreviewUrl}${separator}t=${cacheBuster}`;
            this.qrGenerated = true;
        },

        async downloadQr() {
            if (!this.qrGenerated) {
                return;
            }

            try {
                const response = await fetch(this.qrDownloadUrl, {
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('download failed');
                }

                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = this.downloadFilename;
                link.rel = 'noopener';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(objectUrl);
            } catch {
                const link = document.createElement('a');
                link.href = this.qrDownloadUrl;
                link.download = this.downloadFilename;
                link.rel = 'noopener';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        },

        async shareQr() {
            if (!this.qrGenerated) {
                return;
            }

            try {
                const response = await fetch(this.qrDownloadUrl, {
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('share fetch failed');
                }

                const blob = await response.blob();
                const file = new File([blob], this.downloadFilename, { type: 'image/png' });

                if (navigator.share && navigator.canShare?.({ files: [file] })) {
                    await navigator.share({
                        files: [file],
                        title: `QR Daftar Hadir - ${this.activityTitle}`,
                        text: `Scan QR Code untuk mengisi daftar hadir: ${this.publicUrl}`,
                    });

                    return;
                }
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }
            }

            this.shareModalOpen = true;
        },

        closeShareModal() {
            this.shareModalOpen = false;
        },

        async copyLink() {
            const copied = await this.copyText(this.publicUrl);

            if (copied) {
                this.copied = true;
                clearTimeout(this.copyResetTimer);
                this.copyResetTimer = setTimeout(() => {
                    this.copied = false;
                }, 2500);
            }
        },

        async copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(text);
                    return true;
                } catch {
                    // fall through
                }
            }

            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.setAttribute('readonly', '');
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(textarea);

                return ok;
            } catch {
                return false;
            }
        },
    }));

    Alpine.data('attendanceResponsesPoll', (config = {}) => ({
        stats: {
            total: config.initialTotal ?? 0,
            today: 0,
            last_submitted_at: '-',
            form_status: '-',
        },
        version: config.initialVersion ?? null,
        hasUpdates: false,
        pollTimer: null,

        init() {
            this.poll();
            this.pollTimer = setInterval(() => this.poll(), 12000);
        },

        destroy() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
        },

        async poll() {
            if (!config.url) {
                return;
            }

            try {
                const response = await fetch(config.url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                this.stats = {
                    total: data.total ?? 0,
                    today: data.today ?? 0,
                    last_submitted_at: data.last_submitted_at ?? '-',
                    form_status: data.form_status ?? '-',
                };

                if (data.version !== this.version && this.version !== null) {
                    this.hasUpdates = true;
                }

                if (data.version !== undefined) {
                    this.version = data.version;
                }
            } catch {
                // ignore transient network errors during polling
            }
        },
    }));

    registerExternalAgendaAlpine(Alpine);
});

Alpine.start();
