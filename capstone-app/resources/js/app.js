import Alpine from '@alpinejs/csp';

// Daftarkan semua gambar di resources/images ke manifest Vite agar bisa dipakai di Blade:
//   {{ Vite::asset('resources/images/nama-file.png') }}
import.meta.glob(['../images/**']);

/** Formulir modal (<x-modal>): setelah terbuka, fokus ke isian pertama di dalam dialog yang tampil. */
function focusDialog(root) {
    requestAnimationFrame(() => {
        const dialog = [...root.querySelectorAll('[role=dialog]')].find((el) => el.offsetParent !== null);
        dialog?.querySelector('input:not([type=hidden]):not([disabled]), textarea, select')?.focus({ preventScroll: true });
    });
}

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

// Popover (menu notifikasi): tutup saat klik di luar atau tombol Escape
Alpine.data('popover', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
    get expanded() {
        return this.open ? 'true' : 'false';
    },
    get chevronClass() {
        return this.open ? 'rotate-180' : '';
    },
}));

// Form filter yang langsung dikirim saat pilihan dropdown berubah (@change="submit")
Alpine.data('autoSubmit', () => ({
    submit() {
        if (this.$root.requestSubmit) this.$root.requestSubmit();
        else this.$root.submit();
    },
}));

/*
 * Peer review: slider 1–5 per aspek dengan wajah yang berubah — 1–2 cemberut, 3 datar, 4–5 senyum.
 * Deskripsi tiap skor dibaca dari data-level-1 … data-level-5 (rubrik perilaku).
 */
const PEER_MOUTHS = {
    1: 'M13 29 Q20 20 27 29',
    2: 'M13 28 Q20 24 27 28',
    3: 'M13 26 Q20 26 27 26',
    4: 'M13 24 Q20 29 27 24',
    5: 'M12 23 Q20 33 28 23',
};
const PEER_FACES = {
    1: 'fill-bad-tint',
    2: 'fill-bad-tint',
    3: 'fill-warn-tint',
    4: 'fill-ok-tint',
    5: 'fill-ok-tint',
};
const PEER_NAMES = { 1: 'Sangat buruk', 2: 'Buruk', 3: 'Cukup', 4: 'Baik', 5: 'Sangat baik' };

Alpine.data('peerSlider', () => ({
    value: 3,
    init() {
        this.value = Number(this.$el.dataset.value) || 3;
    },
    change(event) {
        this.value = Number(event.target.value);
    },
    get mouthStyle() {
        return `d: path("${PEER_MOUTHS[this.value]}"); transition: d 0.3s ease-out;`;
    },
    get faceClass() {
        return PEER_FACES[this.value];
    },
    get levelName() {
        return PEER_NAMES[this.value];
    },
    get valueText() {
        return `${this.value} — ${PEER_NAMES[this.value]}`;
    },
    get description() {
        return this.$root.dataset[`level-${this.value}`] ?? '';
    },
}));

// Kartu To Do: sembunyikan tindakan bertenggat lebih dari N hari ke depan (data-due-days); tanpa tenggat/terlambat selalu tampil.
Alpine.data('todoFilter', () => ({
    range: 7,
    empty: false,
    init() {
        this.range = Number(this.$el.dataset.range) || 7;
        this.apply();
    },
    change(event) {
        this.range = Number(event.target.value);
        this.apply();
    },
    apply() {
        let visible = 0;
        this.$root.querySelectorAll('[data-due-days]').forEach((item) => {
            const days = item.dataset.dueDays;
            item.hidden = days !== '' && Number(days) > this.range;
            if (!item.hidden) visible += 1;
        });
        this.empty = visible === 0;
    },
}));

// Filter baris tabel per tim (select → data-team) langsung di browser, beserta jumlah baris yang tampil.
Alpine.data('rowFilter', () => ({
    value: '',
    count: 0,
    empty: false,
    init() {
        this.apply();
    },
    change(event) {
        this.value = event.target.value;
        this.apply();
    },
    apply() {
        const rows = this.$root.querySelectorAll('[data-team]');
        let visible = 0;
        rows.forEach((row) => {
            row.hidden = this.value !== '' && row.dataset.team !== this.value;
            if (!row.hidden) visible += 1;
        });
        this.count = visible;
        this.empty = visible === 0 && rows.length > 0;
    },
}));

// Tab "Log Bimbingan" dosen: tampilkan log satu tim (data-team) yang cocok dengan kata kunci (data-search).
Alpine.data('logTable', () => ({
    team: '',
    query: '',
    empty: false,
    init() {
        this.team = this.$el.dataset.team ?? '';
        this.apply();
    },
    pick(event) {
        this.team = event.target.value;
        this.apply();
    },
    search(event) {
        this.query = event.target.value.trim().toLowerCase();
        this.apply();
    },
    apply() {
        let visible = 0;
        this.$root.querySelectorAll('tr[data-team]').forEach((row) => {
            row.hidden = row.dataset.team !== this.team || (this.query !== '' && !row.dataset.search.includes(this.query));
            if (!row.hidden) visible += 1;
        });
        // Kartu info hanya untuk tim terpilih
        this.$root.querySelectorAll('[data-team-card]').forEach((card) => {
            card.hidden = card.dataset.teamCard !== this.team;
        });
        this.empty = visible === 0;
    },
}));

// Form milestone admin: jenis Dokumen (jenis dokumen + tipe file) atau Lainnya (tahapan); isian jenis lain dinonaktifkan.
Alpine.data('milestoneCategory', () => ({
    category: 'dokumen',
    init() {
        this.category = this.$el.dataset.category || 'dokumen';
    },
    change(event) {
        this.category = event.target.value;
    },
    get isDocument() {
        return this.category === 'dokumen';
    },
    get isOther() {
        return this.category !== 'dokumen';
    },
}));

// Panel lipat sederhana; juga dipakai untuk membuka formulir modal (<x-modal>) per baris
Alpine.data('disclosure', () => ({
    expanded: false,
    toggle() {
        this.expanded = !this.expanded;
        if (this.expanded) this.$nextTick(() => focusDialog(this.$root));
    },
    close() {
        this.expanded = false;
    },
    get collapsed() {
        return !this.expanded;
    },
}));

// Formulir di atas halaman (registrasi tim, registrasi topik): tersembunyi sampai tombolnya ditekan,
// terbuka bila ada error validasi (data-open="true"). Panel formulir diberi x-ref="panel".
Alpine.data('registrationForm', () => ({
    expanded: false,
    init() {
        this.expanded = this.$el.dataset.open === 'true';
        if (this.expanded && this.$el.dataset.reveal === 'true') this.reveal();
    },
    toggle() {
        this.expanded = !this.expanded;
        if (this.expanded) this.reveal();
    },
    open() {
        this.expanded = true;
        this.reveal();
    },
    close() {
        this.expanded = false;
    },
    get chevronClass() {
        return this.expanded ? 'rotate-180' : '';
    },
    reveal() {
        this.$nextTick(() => focusDialog(this.$root));
    },
}));

/*
 * Formulir tambah/edit topik dosen di atas halaman, tanpa memuat ulang:
 * add() mengosongkan formulir (aksi = simpan baru), edit(event) mengisinya dari data-topic tombol Edit (aksi = PUT).
 */
Alpine.data('topicForm', () => ({
    expanded: false,
    editing: false,
    action: '',
    store: '',
    init() {
        this.store = this.$el.dataset.store;
        this.action = this.$el.dataset.action || this.store;
        this.editing = this.$el.dataset.editing === '1';
        this.expanded = this.$el.dataset.open === 'true';
        if (this.expanded) this.reveal();
    },
    get method() {
        return this.editing ? 'PUT' : 'POST';
    },
    get editingFlag() {
        return this.editing ? '1' : '';
    },
    get heading() {
        return this.editing ? 'Edit topik' : 'Tambah topik';
    },
    get submitLabel() {
        return this.editing ? 'Simpan perubahan' : 'Kirim ke admin';
    },
    add() {
        this.fill({ action: this.store, title: '', description: '', prodi: '', competencies: '', object: '', quota: 1 }, false);
    },
    edit(event) {
        this.fill(JSON.parse(event.currentTarget.dataset.topic), true);
    },
    fill(topic, editing) {
        this.editing = editing;
        this.action = topic.action;
        const form = this.$refs.panel;
        ['title', 'description', 'object', 'quota'].forEach((key) => {
            form.elements[key].value = topic[key] ?? '';
        });
        form.elements.prodi_code.value = topic.prodi ?? '';
        form.elements.competencies.value = topic.competencies ?? '';
        form.querySelectorAll('.field-error').forEach((el) => el.remove());
        this.expanded = true;
        this.reveal();
    },
    close() {
        this.expanded = false;
    },
    reveal() {
        this.$nextTick(() => focusDialog(this.$root));
    },
}));

// Formulir pengajuan topik dosen di atas halaman; tombol "Ajukan" tiap kartu membawa data-action & data-title.
Alpine.data('applyForm', () => ({
    expanded: false,
    action: '',
    title: '',
    init() {
        this.expanded = this.$el.dataset.open === 'true';
        this.action = this.$el.dataset.action ?? '';
        this.title = this.$el.dataset.title ?? '';
    },
    pick(event) {
        this.action = event.currentTarget.dataset.action;
        this.title = event.currentTarget.dataset.title;
        this.expanded = true;
        this.$nextTick(() => focusDialog(this.$root));
    },
    close() {
        this.expanded = false;
    },
}));

/*
 * Satu baris anggota pada formulir registrasi: dropdown mahasiswa yang bisa dicari (nama/NIM)
 * dan daerah asal yang terisi otomatis dari data mahasiswa. Daftar mahasiswa dibaca dari
 * <script type="application/json" id="data-mahasiswa">; mahasiswa yang sudah dipilih di baris lain disembunyikan.
 */
Alpine.store('picked', {});

Alpine.data('memberRow', () => ({
    options: [],
    query: '',
    open: false,
    selectedId: '',
    selectedLabel: '',
    region: '',
    row: '',
    init() {
        this.options = JSON.parse(document.getElementById('data-mahasiswa')?.textContent ?? '[]');
        this.row = this.$el.dataset.row;
        this.region = this.$el.dataset.region ?? '';
        const found = this.options.find((o) => String(o.id) === this.$el.dataset.selected);
        if (found) this.select(found, false);
    },
    get results() {
        const q = this.query.trim().toLowerCase();
        const picked = Alpine.store('picked');
        const taken = Object.keys(picked).filter((row) => row !== this.row).map((row) => picked[row]);
        return this.options
            .filter((o) => !taken.includes(String(o.id)))
            .filter((o) => !q || o.name.toLowerCase().includes(q) || o.nim.includes(q))
            .slice(0, 8);
    },
    get noResults() {
        return this.results.length === 0;
    },
    get hasSelection() {
        return this.selectedId !== '';
    },
    get noSelection() {
        return this.selectedId === '';
    },
    get expanded() {
        return this.open ? 'true' : 'false';
    },
    show() {
        this.open = true;
    },
    hide() {
        this.open = false;
    },
    search(event) {
        this.query = event.target.value;
        this.open = true;
    },
    setRegion(event) {
        this.region = event.target.value;
    },
    choose(event) {
        const option = this.options.find((o) => String(o.id) === event.currentTarget.dataset.id);
        if (option) this.select(option, true);
    },
    select(option, fillRegion) {
        this.selectedId = String(option.id);
        this.selectedLabel = `${option.name} · ${option.nim}`;
        this.query = '';
        this.open = false;
        if (fillRegion && option.region) this.region = option.region;
        Alpine.store('picked')[this.row] = this.selectedId;
    },
    clear() {
        this.selectedId = '';
        this.selectedLabel = '';
        delete Alpine.store('picked')[this.row];
        this.$nextTick(() => this.$refs.search?.focus());
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
// tone: "danger" (bawaan, tombol merah — hapus/tolak) atau "positive" (tombol hijau — terima/setujui), via data-confirm-tone
Alpine.store('confirm', { open: false, message: '', label: 'Hapus', tone: 'danger', form: null, submitter: null });

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
    get acceptClass() {
        return Alpine.store('confirm').tone === 'positive' ? 'btn-primary' : 'btn-danger';
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
        tone: form.dataset.confirmTone ?? 'danger',
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
