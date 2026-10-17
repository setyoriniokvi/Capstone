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

/*
 * Konfirmasi aksi destruktif. Cukup beri atribut pada form:
 *   <form ... data-confirm="Hapus dokumen ini?" data-confirm-label="Hapus">
 * Dialognya satu untuk seluruh halaman: <x-confirm-dialog /> di layout.
 */
Alpine.store('confirm', { open: false, message: '', label: 'Hapus', form: null, submitter: null });

Alpine.data('confirmDialog', () => ({
    init() {
        this.$watch('open', (open) => open && this.$nextTick(() => this.$refs.cancel.focus()));
    },
    get open() {
        return Alpine.store('confirm').open;
    },
    get message() {
        return Alpine.store('confirm').message;
    },
    get label() {
        return Alpine.store('confirm').label;
    },
    cancel() {
        const store = Alpine.store('confirm');
        store.open = false;
        store.submitter?.focus();
        store.form = null;
    },
    accept() {
        const { form, submitter } = Alpine.store('confirm');
        Alpine.store('confirm').open = false;
        form.dataset.confirmed = 'true';
        // requestSubmit belum ada di iOS 14 (NFR) — fallback ke submit() biasa
        if (form.requestSubmit) {
            form.requestSubmit(submitter ?? null);
        } else {
            form.submit();
        }
    },
}));

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.dataset.confirm || form.dataset.confirmed === 'true') {
        return;
    }
    event.preventDefault();
    Object.assign(Alpine.store('confirm'), {
        open: true,
        message: form.dataset.confirm,
        label: form.dataset.confirmLabel ?? 'Hapus',
        form,
        submitter: event.submitter ?? null,
    });
});

/*
 * Tabel responsif: salin judul kolom <th> ke setiap sel sebagai data-label,
 * dipakai CSS untuk tampilan kartu di layar sempit (lihat "Tabel responsif" di app.css).
 */
function labelTableCells(root = document) {
    root.querySelectorAll('.data-table').forEach((table) => {
        const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach((row) => {
            [...row.children].forEach((cell, i) => {
                if (headers[i] && !cell.hasAttribute('colspan') && !cell.dataset.label) {
                    cell.dataset.label = headers[i];
                }
            });
        });
    });
}
labelTableCells();

/*
 * Tabel responsif: bila tabel lebih lebar dari wadahnya, .table-wrap diberi .is-stacked
 * (tampilan kartu, lihat app.css). Dicek ulang hanya saat LEBAR wadah berubah.
 */
function fitTable(wrap) {
    wrap.classList.remove('is-stacked');
    if (wrap.scrollWidth > wrap.clientWidth + 1) {
        wrap.classList.add('is-stacked');
    }
}

const fittedWidths = new WeakMap();
const tableObserver = new ResizeObserver((entries) => {
    entries.forEach(({ target, contentRect }) => {
        if (fittedWidths.get(target) === contentRect.width) {
            return;
        }
        fittedWidths.set(target, contentRect.width);
        fitTable(target);
    });
});
document.querySelectorAll('.table-wrap').forEach((wrap) => tableObserver.observe(wrap));

// Tombol submit yang menandai sedang memproses agar tidak dikirim dua kali
document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) {
        return;
    }
    const button = event.target.querySelector('button[type=submit][data-once]');
    if (button) {
        button.dataset.loading = 'true';
        button.setAttribute('aria-disabled', 'true');
    }
});

window.Alpine = Alpine;
Alpine.start();
