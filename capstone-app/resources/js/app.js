import Alpine from '@alpinejs/csp';

/*
 * Build CSP Alpine tidak mengevaluasi ekspresi bebas, jadi seluruh perilaku
 * didefinisikan di sini lewat Alpine.data dan dipanggil dengan nama.
 */

// Sidebar navigasi untuk layar sempit
Alpine.data('shell', () => ({
    navOpen: false,
    toggleNav() {
        this.navOpen = !this.navOpen;
    },
    closeNav() {
        this.navOpen = false;
    },
    get navClass() {
        return this.navOpen ? '!translate-x-0' : '';
    },
}));

// Modal konfirmasi approval/penolakan (PRD §10.3)
Alpine.data('decision', () => ({
    open: false,
    show() {
        this.open = true;
        this.$nextTick(() => this.$refs.dialog?.querySelector('textarea, button')?.focus());
    },
    hide() {
        this.open = false;
    },
    get closed() {
        return !this.open;
    },
}));

// Tombol mata pada field password
Alpine.data('passwordToggle', () => ({
    visible: false,
    toggle() {
        this.visible = !this.visible;
    },
    get hidden() {
        return !this.visible;
    },
    get inputType() {
        return this.visible ? 'text' : 'password';
    },
    get label() {
        return this.visible ? 'Sembunyikan password' : 'Tampilkan password';
    },
    get pressed() {
        return this.visible ? 'true' : 'false';
    },
}));

// Panel lipat sederhana
Alpine.data('disclosure', () => ({
    expanded: false,
    toggle() {
        this.expanded = !this.expanded;
    },
    get collapsed() {
        return !this.expanded;
    },
}));

// Unggah dokumen dengan indikator progres (NFR mobile-first)
Alpine.data('uploader', () => ({
    progress: 0,
    busy: false,
    error: '',
    fileName: '',
    pick(event) {
        this.fileName = event.target.files[0]?.name ?? '';
        this.error = '';
    },
    get idle() {
        return !this.busy;
    },
    get percent() {
        return `${this.progress}%`;
    },
    get barStyle() {
        return `transform: scaleX(${this.progress / 100})`;
    },
    get hasError() {
        return this.error !== '';
    },
    get hasFile() {
        return this.fileName !== '';
    },
    submit(event) {
        const form = event.target;
        if (!form.querySelector('input[type=file]')?.files.length) {
            this.error = 'Pilih file terlebih dahulu.';
            return;
        }
        this.busy = true;
        this.progress = 0;
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100);
        });
        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                window.location.reload();
                return;
            }
            this.busy = false;
            try {
                const body = JSON.parse(xhr.responseText);
                this.error = Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? 'Unggah gagal.';
            } catch {
                this.error = xhr.status === 413 ? 'Ukuran file melebihi batas server.' : 'Unggah gagal. Coba lagi.';
            }
        });
        xhr.addEventListener('error', () => {
            this.busy = false;
            this.error = 'Koneksi terputus. Coba lagi.';
        });
        xhr.send(new FormData(form));
    },
}));

// Tombol submit yang menandai sedang memproses agar tidak dikirim dua kali
document.addEventListener('submit', (event) => {
    const button = event.target.querySelector('button[type=submit][data-once]');
    if (button) {
        button.dataset.loading = 'true';
        button.setAttribute('aria-disabled', 'true');
    }
});

window.Alpine = Alpine;
Alpine.start();
