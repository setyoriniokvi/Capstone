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

/*
 * Komponen bersarang: Alpine memanggil method dengan "this" = gabungan scope elemen yang diklik,
 * sehingga tombol di dalam komponen lain (mis. tombol "Registrasi Topik" milik registrationForm yang
 * berada di dalam section x-data="disclosure") mengubah "expanded" milik komponen TERDALAM, bukan
 * pemilik method — tombol beranimasi tetapi modal tidak muncul. Karena itu setiap method dikunci ke
 * scope komponennya sendiri saat init ("this" di init = scope elemen akar komponen, termasuk $root/$refs).
 */
const defineData = Alpine.data.bind(Alpine);
Alpine.data = (name, factory) =>
    defineData(name, (...args) => {
        const data = factory(...args);
        const init = data.init;
        data.init = function () {
            const self = this;
            Object.entries(Object.getOwnPropertyDescriptors(data)).forEach(([key, descriptor]) => {
                if (key !== 'init' && typeof descriptor.value === 'function') {
                    self[key] = descriptor.value.bind(self);
                }
            });
            return init?.call(self);
        };
        return data;
    });

// Sidebar navigasi: laci di layar sempit, bisa disembunyikan/ditampilkan di desktop
const desktopQuery = window.matchMedia('(min-width: 768px)');
Alpine.data('shell', () => ({
    navOpen: false,
    collapsed: false,
    init() {
        this.collapsed = this.$el.dataset.sidebar === 'collapsed';
    },
    toggleNav() {
        this.navOpen = !this.navOpen;
    },
    closeNav() {
        this.navOpen = false;
    },
    toggleSidebar() {
        if (!desktopQuery.matches) {
            this.toggleNav();
            return;
        }
        this.collapsed = !this.collapsed;
        // Cookie dibaca layout Blade agar halaman berikutnya langsung dirender dengan status yang sama.
        document.cookie = `sidebar=${this.collapsed ? 'collapsed' : 'open'}; path=/; max-age=31536000; SameSite=Lax`;
    },
    get navClass() {
        return this.navOpen ? '!translate-x-0' : '';
    },
    get sidebarState() {
        return this.collapsed ? 'collapsed' : 'open';
    },
    get sidebarExpanded() {
        return String(desktopQuery.matches ? !this.collapsed : this.navOpen);
    },
    get sidebarLabel() {
        if (!desktopQuery.matches) {
            return this.navOpen ? 'Tutup menu' : 'Buka menu';
        }
        return this.collapsed ? 'Tampilkan menu' : 'Sembunyikan menu';
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

// Lonceng notifikasi: popover + "Tandai semua dibaca" optimistis — tampilan langsung diperbarui, lalu disimpan
// di latar belakang. Bila penyimpanan gagal, formulir dikirim biasa sebagai cadangan.
Alpine.data('notificationMenu', () => ({
    open: false,
    unread: 0,
    init() {
        this.unread = Number(this.$el.dataset.unread ?? 0);
    },
    toggle() {
        this.open = !this.open;
    },
    close() {
        this.open = false;
    },
    get expanded() {
        return this.open ? 'true' : 'false';
    },
    get hasUnread() {
        return this.unread > 0;
    },
    get noUnread() {
        return this.unread === 0;
    },
    get bellLabel() {
        return this.unread ? `Notifikasi, ${this.unread} belum dibaca` : 'Notifikasi';
    },
    markAll(event) {
        event.preventDefault();
        const form = event.target;
        this.unread = 0;
        this.$root.querySelectorAll('[data-unread-dot]').forEach((dot) => {
            dot.classList.remove('bg-bad', 'bg-warn', 'bg-brand');
            dot.classList.add('border-2', 'border-rule-strong');
            dot.removeAttribute('data-unread-dot');
        });
        this.$root.querySelectorAll('[data-unread-label]').forEach((label) => label.remove());

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': form.elements._token.value, Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((response) => {
            if (!response.ok) form.submit();
        }).catch(() => form.submit());
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

// Tabel lebar yang digeser ke samping: tombol < / > langsung ke ujung kiri / kanan (x-ref="scroller").
// Tombol hanya tampil bila masih ada sisi yang bisa dituju.
Alpine.data('scrollPager', () => ({
    canLeft: false,
    canRight: false,
    init() {
        this.update();
        new ResizeObserver(() => this.update()).observe(this.$refs.scroller);
    },
    update() {
        const el = this.$refs.scroller;
        this.canLeft = el.scrollLeft > 1;
        this.canRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
    },
    toStart() {
        this.$refs.scroller.scrollTo({ left: 0, behavior: 'smooth' });
    },
    toEnd() {
        this.$refs.scroller.scrollTo({ left: this.$refs.scroller.scrollWidth, behavior: 'smooth' });
    },
}));

// Kartu To Do: sembunyikan tindakan dengan deadline lebih dari N hari ke depan (data-due-days); tanpa deadline/terlambat selalu tampil.
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

// Setujui sekaligus (Approval Bimbingan & Approval Registrasi Tim): filter per tim opsional (seperti rowFilter) + kotak
// centang per baris untuk "Setujui pilihan". "Pilih semua" hanya mencentang baris yang tampil; baris yang tersembunyi
// oleh filter otomatis tidak dipilih. Baris: [data-team]; kotak centang: [data-select-row] & [data-select-all].
Alpine.data('bulkApproval', () => ({
    value: '',
    count: 0,
    empty: false,
    selected: 0,
    allSelected: false,
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
            if (row.hidden) row.querySelector('[data-select-row]').checked = false;
            else visible += 1;
        });
        this.count = visible;
        this.empty = visible === 0 && rows.length > 0;
        this.sync();
    },
    pick(event) {
        if (event.target.matches('[data-select-all]')) {
            this.visibleBoxes().forEach((box) => {
                box.checked = event.target.checked;
            });
        }
        this.sync();
    },
    visibleBoxes() {
        return [...this.$root.querySelectorAll('[data-select-row]')].filter((box) => !box.closest('tr').hidden);
    },
    sync() {
        const boxes = this.visibleBoxes();
        this.selected = boxes.filter((box) => box.checked).length;
        this.allSelected = boxes.length > 0 && this.selected === boxes.length;
        const all = this.$root.querySelector('[data-select-all]');
        if (all) all.indeterminate = this.selected > 0 && !this.allSelected;
    },
    get noneSelected() {
        return this.selected === 0;
    },
}));

// Cari + filter kategori langsung di browser untuk baris ber-data-search (teks kecil) & data-category (Penilaian dosen).
Alpine.data('tableSearch', () => ({
    query: '',
    category: '',
    empty: false,
    init() {
        this.query = (this.$refs.query?.value ?? '').trim().toLowerCase();
        this.category = this.$refs.category?.value ?? '';
        this.apply();
    },
    search(event) {
        this.query = event.target.value.trim().toLowerCase();
        this.apply();
    },
    pick(event) {
        this.category = event.target.value;
        this.apply();
    },
    apply() {
        const rows = this.$root.querySelectorAll('[data-search]');
        let visible = 0;
        rows.forEach((row) => {
            row.hidden = (this.query !== '' && !row.dataset.search.includes(this.query))
                || (this.category !== '' && row.dataset.category !== this.category);
            if (!row.hidden) visible += 1;
        });
        this.empty = rows.length > 0 && visible === 0;
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
    addLabel: 'Kirim ke admin',
    init() {
        this.store = this.$el.dataset.store;
        this.action = this.$el.dataset.action || this.store;
        this.addLabel = this.$el.dataset.addLabel || 'Kirim ke admin';
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
        return this.editing ? 'Simpan perubahan' : this.addLabel;
    },
    add() {
        this.fill({ action: this.store, title: '', description: '', competencies: '', prodi: [], specializations: [], object: '', quota: 1, lecturer: '', proposers: [], primary: null }, false);
    },
    edit(event) {
        this.fill(JSON.parse(event.currentTarget.dataset.topic), true);
    },
    fill(topic, editing) {
        this.editing = editing;
        this.action = topic.action;
        const form = this.$refs.panel;
        ['title', 'description', 'competencies', 'object', 'quota'].forEach((key) => {
            form.elements[key].value = topic[key] ?? '';
        });
        form.querySelectorAll('input[name="prodi_codes[]"]').forEach((box) => {
            box.checked = (topic.prodi ?? []).includes(box.value);
        });
        form.querySelectorAll('input[name="specializations[]"]').forEach((box) => {
            box.checked = (topic.specializations ?? []).includes(box.value);
        });
        window.dispatchEvent(new CustomEvent('proposers-set', { detail: { ids: topic.proposers ?? [], primary: topic.primary ?? null } }));
        // Formulir admin: dosen pengusul utama dipilih lewat pencarian (lecturerPicker); setelah proposers-set agar
        // pengusul utama dikecualikan dari pilihan pengusul bersama.
        if (form.elements.lecturer_id) window.dispatchEvent(new CustomEvent('primary-set', { detail: topic.lecturer ?? '' }));
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

// Isian wajib di dalam <details> yang tertutup (baris ketua/anggota formulir registrasi) tidak dapat ditunjukkan
// browser saat validasi gagal — buka dulu baris tersebut. Event "invalid" tidak bubble, jadi didengar di fase capture.
document.addEventListener('invalid', (event) => {
    const section = event.target.closest?.('details:not([open])');
    if (section) section.open = true;
}, true);

// Judul baris ketua/anggota (formulir registrasi) mengikuti nama & NIM yang sedang diketik.
document.addEventListener('input', (event) => {
    if (!event.target.matches?.('[data-summary-source]')) return;
    const section = event.target.closest('details');
    const summary = section?.querySelector('[data-summary]');
    if (!summary) return;
    const value = (key) => section.querySelector(`[data-summary-source="${key}"]`)?.value.trim();
    summary.textContent = [value('name'), value('nim')].filter(Boolean).join(' · ') || 'Belum diisi';
    summary.classList.replace('text-bad-ink', 'text-muted');
});

/*
 * Invite anggota pada Registrasi Tim di Tim Saya: dropdown mahasiswa yang belum bertim, bisa dicari dengan nama/NIM/email.
 * Daftar dibaca dari <script type="application/json" id="data-mahasiswa">; mahasiswa yang sudah dipilih di baris lain
 * disembunyikan. Memilih mahasiswa mengisi peminatan & daerah asal dari datanya dan memperbarui judul baris.
 */
Alpine.store('picked', {});

Alpine.data('studentPicker', () => ({
    options: [],
    query: '',
    open: false,
    selectedId: '',
    selectedLabel: '',
    row: '',
    init() {
        this.options = JSON.parse(document.getElementById('data-mahasiswa')?.textContent ?? '[]');
        this.row = this.$el.dataset.row;
        const found = this.options.find((o) => String(o.id) === this.$el.dataset.selected);
        if (found) this.select(found, false);
    },
    get results() {
        const q = this.query.trim().toLowerCase();
        const picked = Alpine.store('picked');
        const taken = Object.keys(picked).filter((row) => row !== this.row).map((row) => picked[row]);
        return this.options
            .filter((o) => !taken.includes(String(o.id)))
            .filter((o) => !q || o.name.toLowerCase().includes(q) || o.nim.includes(q) || (o.email ?? '').toLowerCase().includes(q))
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
    choose(event) {
        const option = this.options.find((o) => String(o.id) === event.currentTarget.dataset.id);
        if (option) this.select(option, true);
    },
    select(option, fill) {
        this.selectedId = String(option.id);
        this.selectedLabel = `${option.name} · ${option.nim} · ${option.email}`;
        this.query = '';
        this.open = false;
        Alpine.store('picked')[this.row] = this.selectedId;
        const section = this.$el.closest('details');
        const summary = section?.querySelector('[data-summary]');
        if (summary) {
            summary.textContent = `${option.name} · ${option.nim}`;
            summary.classList.replace('text-bad-ink', 'text-muted');
        }
        if (fill && section) {
            const field = (name) => section.querySelector(`[name$="[${name}]"]`);
            if (field('specialization')) field('specialization').value = option.specialization ?? '';
            if (field('origin_region') && option.region) field('origin_region').value = option.region;
        }
    },
    clear() {
        this.selectedId = '';
        this.selectedLabel = '';
        delete Alpine.store('picked')[this.row];
        const summary = this.$el.closest('details')?.querySelector('[data-summary]');
        if (summary) summary.textContent = 'Belum diisi';
        this.$nextTick(() => this.$refs.search?.focus());
    },
}));

// Dosen pengusul topik (multi-pilih): cari dosen lain menurut nama / NIP / peminatan dari #data-dosen.
// Daftar terpilih diisi ulang oleh topicForm lewat event "proposers-set" (detail = { ids, primary }); pengusul utama
// (bila yang mengedit pengusul bersama) tampil tetap dan tidak dapat dihapus.
Alpine.data('proposerPicker', () => ({
    options: [],
    selected: [],
    primary: null,
    query: '',
    open: false,
    init() {
        this.options = JSON.parse(document.getElementById('data-dosen')?.textContent ?? '[]');
        this.selected = (this.$el.dataset.selected ?? '').split(',').filter(Boolean);
        const { primaryId, primaryName } = this.$el.dataset;
        this.primary = primaryId ? { id: primaryId, name: primaryName } : null;
    },
    load(event) {
        this.selected = (event.detail?.ids ?? []).map(String);
        this.primary = event.detail?.primary ? { id: String(event.detail.primary.id), name: event.detail.primary.name } : null;
        this.query = '';
        this.open = false;
    },
    // Formulir admin: pengusul utama yang dipilih tidak boleh sekaligus menjadi pengusul bersama.
    exclude(event) {
        const id = event.detail ? String(event.detail) : '';
        this.primary = id ? { id, name: '' } : null;
        this.selected = this.selected.filter((s) => s !== id);
    },
    get hasPrimary() {
        return this.primary !== null;
    },
    get primaryName() {
        return this.primary?.name ?? '';
    },
    get selfLabel() {
        return this.primary ? '· Anda' : '· Anda, pengusul utama';
    },
    get chosen() {
        return this.selected
            .map((id) => this.options.find((o) => String(o.id) === id))
            .filter(Boolean)
            .map((o) => ({ ...o, removeLabel: `Hapus ${o.name} dari pengusul` }));
    },
    get results() {
        const q = this.query.trim().toLowerCase();
        return this.options
            .filter((o) => !this.selected.includes(String(o.id)) && String(o.id) !== this.primary?.id)
            .filter((o) => !q || o.name.toLowerCase().includes(q) || o.nip.includes(q) || o.code.toLowerCase().includes(q) || o.specialization.toLowerCase().includes(q))
            .slice(0, 8);
    },
    get noResults() {
        return this.results.length === 0;
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
    choose(event) {
        const id = event.currentTarget.dataset.id;
        if (!this.selected.includes(id)) this.selected.push(id);
        this.query = '';
        this.open = false;
        this.$nextTick(() => this.$refs.search?.focus());
    },
    remove(event) {
        const id = event.currentTarget.dataset.id;
        this.selected = this.selected.filter((s) => s !== id);
    },
}));

// Daftar dosen di Kuota Dosen: cari kode/nama (data-search) + filter peminatan (data-category) langsung di browser,
// area gulir setinggi 10,5 baris (baris ke-11 terlihat setengah) dan tombol panah ke bawah bila baris tampil > 10.
Alpine.data('peekTable', () => ({
    query: '',
    category: '',
    empty: false,
    overflow: false,
    atEnd: false,
    init() {
        this.apply();
        window.addEventListener('resize', () => this.measure());
    },
    search(event) {
        this.query = event.target.value.trim().toLowerCase();
        this.apply();
    },
    pick(event) {
        this.category = event.target.value;
        this.apply();
    },
    apply() {
        const rows = this.$root.querySelectorAll('[data-search]');
        let visible = 0;
        rows.forEach((row) => {
            row.hidden = (this.query !== '' && !row.dataset.search.includes(this.query))
                || (this.category !== '' && row.dataset.category !== this.category);
            if (!row.hidden) visible += 1;
        });
        this.empty = rows.length > 0 && visible === 0;
        this.$nextTick(() => this.measure());
    },
    measure() {
        const box = this.$refs.scroller;
        if (!box) return;
        const rows = [...box.querySelectorAll('[data-search]')].filter((row) => !row.hidden);
        this.overflow = rows.length > 10;
        box.style.maxHeight = this.overflow ? `${rows[10].offsetTop + rows[10].offsetHeight / 2}px` : '';
        this.track();
    },
    track() {
        const box = this.$refs.scroller;
        this.atEnd = box.scrollTop + box.clientHeight >= box.scrollHeight - 2;
    },
    get showArrow() {
        return this.overflow && !this.atEnd;
    },
    down() {
        const box = this.$refs.scroller;
        box.scrollBy({ top: box.clientHeight * 0.8, behavior: 'smooth' });
    },
}));

// Tambah kuota dosen: pencarian dosen (proposerPicker di dalamnya) hanya aktif untuk cakupan "Dosen tertentu".
Alpine.data('quotaForm', () => ({
    scope: 'global',
    init() {
        this.scope = this.$el.dataset.scope || 'global';
    },
    change(event) {
        this.scope = event.target.value;
    },
    get isGlobal() {
        return this.scope !== 'dosen';
    },
    get isDosen() {
        return this.scope === 'dosen';
    },
    get disabledClass() {
        return this.isGlobal ? 'text-faint' : '';
    },
}));

// Dosen pengusul utama (formulir topik dosen admin): satu dosen, dicari menurut nama / NIP / kode / peminatan dari
// #data-dosen. Diisi ulang oleh topicForm lewat event "primary-set"; pilihannya diumumkan lewat "primary-chosen".
Alpine.data('lecturerPicker', () => ({
    options: [],
    query: '',
    open: false,
    selectedId: '',
    selectedLabel: '',
    init() {
        this.options = JSON.parse(document.getElementById('data-dosen')?.textContent ?? '[]');
        this.set(this.$el.dataset.selected ?? '');
    },
    load(event) {
        this.set(String(event.detail ?? ''));
    },
    set(id) {
        const option = this.options.find((o) => String(o.id) === String(id));
        this.selectedId = option ? String(option.id) : '';
        this.selectedLabel = option ? [option.name, option.nip, option.specialization].filter(Boolean).join(' · ') : '';
        this.query = '';
        this.open = false;
        window.dispatchEvent(new CustomEvent('primary-chosen', { detail: this.selectedId }));
    },
    get results() {
        const q = this.query.trim().toLowerCase();
        return this.options
            .filter((o) => !q || o.name.toLowerCase().includes(q) || o.nip.includes(q) || o.code.toLowerCase().includes(q) || o.specialization.toLowerCase().includes(q))
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
    choose(event) {
        this.set(event.currentTarget.dataset.id);
    },
    clear() {
        this.set('');
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
// notes: data-confirm-notes="nama_field" → kolom catatan opsional, nilainya dikirim sebagai field tersebut (label: data-confirm-notes-label)
Alpine.store('confirm', { open: false, message: '', label: 'Hapus', tone: 'danger', notes: null, notesLabel: '', form: null, submitter: null });

Alpine.data('confirmDialog', () => ({
    init() {
        this.$watch('open', (open) => {
            if (!open) return;
            this.$refs.notes.value = '';
            this.$nextTick(() => (this.withNotes ? this.$refs.notes : this.$refs.cancel).focus());
        });
    },
    get withNotes() {
        return Boolean(Alpine.store('confirm').notes);
    },
    get notesLabel() {
        return Alpine.store('confirm').notesLabel;
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
        const { form, submitter, notes } = Alpine.store('confirm');
        Alpine.store('confirm').open = false;
        if (notes) {
            const field = form.elements[notes] ?? form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name: notes }));
            field.value = this.$refs.notes.value.trim();
        }
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
        notes: form.dataset.confirmNotes ?? null,
        notesLabel: form.dataset.confirmNotesLabel ?? 'Catatan (opsional)',
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

/*
 * Loading antar halaman (<x-page-loader />): tampil saat tautan internal diklik atau form dikirim,
 * setelah jeda singkat agar halaman yang cepat tidak berkedip. Dikecualikan: tautan unduhan (download),
 * tab baru, tautan #anchor, klik dengan tombol pengubah, dan elemen ber-data-no-loader.
 * Handler submit ini didaftarkan setelah handler konfirmasi, jadi form yang ditahan dialog tidak memicunya.
 */
const pageLoader = document.getElementById('page-loader');
let pageLoaderTimer;

function showPageLoader() {
    if (!pageLoader) {
        return;
    }
    clearTimeout(pageLoaderTimer);
    pageLoaderTimer = setTimeout(() => {
        pageLoader.classList.add('is-active');
        pageLoader.setAttribute('aria-hidden', 'false');
    }, 250);
}

function hidePageLoader() {
    if (!pageLoader) {
        return;
    }
    clearTimeout(pageLoaderTimer);
    pageLoader.classList.remove('is-active');
    pageLoader.setAttribute('aria-hidden', 'true');
}

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    const link = event.target.closest('a[href]');
    if (!link || link.hasAttribute('download') || 'noLoader' in link.dataset || (link.target && link.target !== '_self')) {
        return;
    }
    if (link.getAttribute('href').startsWith('#')) {
        return;
    }
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) {
        return;
    }
    // Hanya pindah anchor di halaman yang sama
    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
        return;
    }
    showPageLoader();
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || 'noLoader' in form.dataset || (form.target && form.target !== '_self')) {
        return;
    }
    showPageLoader();
});

// Kembali lewat tombol Back (bfcache): halaman dipulihkan apa adanya, jadi loader harus disembunyikan.
window.addEventListener('pageshow', hidePageLoader);

window.Alpine = Alpine;
Alpine.start();
