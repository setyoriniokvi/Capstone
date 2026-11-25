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
// Sidebar: "open" / "collapsed" pilihan pengguna berlaku di semua ukuran layar (disimpan di cookie); "auto" = belum
// pernah memilih (tampil di layar >= 768px, tersembunyi di layar sempit). Sidebar yang tampil mendorong konten (app.css).
Alpine.data('shell', () => ({
    state: 'auto',
    desktop: true,
    init() {
        this.state = this.$el.dataset.sidebar || 'auto';
        this.desktop = desktopQuery.matches;
        desktopQuery.addEventListener('change', (event) => {
            this.desktop = event.matches;
        });
    },
    get visible() {
        return this.state === 'open' || (this.state === 'auto' && this.desktop);
    },
    setState(state) {
        this.state = state;
        // Cookie dibaca layout Blade agar halaman berikutnya langsung dirender dengan status yang sama.
        document.cookie = `sidebar=${state}; path=/; max-age=31536000; SameSite=Lax`;
    },
    toggleSidebar() {
        this.setState(this.visible ? 'collapsed' : 'open');
    },
    get sidebarState() {
        return this.state;
    },
    get sidebarExpanded() {
        return String(this.visible);
    },
    get sidebarLabel() {
        return this.visible ? 'Sembunyikan menu' : 'Tampilkan menu';
    },
    // Topbar di atas foto banner dashboard: transparan di puncak halaman, berlatar setelah digulir.
    scrolled: false,
    trackScroll() {
        this.scrolled = window.scrollY > 8;
    },
    get topbarClass() {
        return this.scrolled ? 'is-scrolled' : '';
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
 * Peer review: skala 1–5 per aspek dengan wajah yang berubah — 1–2 cemberut, 3 datar, 4–5 senyum.
 */
// Nilai peer review per level (0 / 50 / 65 / 75 / 90): bentuk mulut & warna wajah
const PEER_MOUTHS = {
    0: 'M13 29 Q20 20 27 29',
    50: 'M13 28 Q20 24 27 28',
    65: 'M13 26 Q20 26 27 26',
    75: 'M13 24 Q20 29 27 24',
    90: 'M12 23 Q20 33 28 23',
};
const PEER_FACES = {
    0: 'fill-bad-tint',
    50: 'fill-bad-tint',
    65: 'fill-warn-tint',
    75: 'fill-ok-tint',
    90: 'fill-ok-tint',
};
Alpine.data('peerSlider', () => ({
    value: 65,
    init() {
        const value = Number(this.$el.dataset.value);
        this.value = value in PEER_MOUTHS ? value : 65;
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
    // Geser sekitar satu layar tabel ke kiri / kanan
    left() {
        this.$refs.scroller.scrollBy({ left: -this.step(), behavior: 'smooth' });
    },
    right() {
        this.$refs.scroller.scrollBy({ left: this.step(), behavior: 'smooth' });
    },
    step() {
        return Math.max(160, this.$refs.scroller.clientWidth * 0.8);
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

// Filter dropdown langsung di browser (tanpa memuat ulang): <select data-filter="peran" name="peran" @change="filter">
// menyaring baris yang ber-data-filter-peran (nilai dipisah "|"); pilihan kosong = semua. Pilihan ikut ditulis ke URL
// (history.replaceState) agar tetap terpakai saat halaman dimuat ulang atau dibagikan.
function filterChecks(root) {
    // Dropdown di dalam komponen filter bersarang ([data-filter-own], mis. modal Detail) hanya milik komponen itu
    return [...root.querySelectorAll('[data-filter]')]
        .filter((el) => { const own = el.closest('[data-filter-own]'); return !own || own === root; })
        .filter((el) => el.value !== '')
        .map((el) => [`filter${el.dataset.filter.charAt(0).toUpperCase()}${el.dataset.filter.slice(1)}`, el.value]);
}
function passesFilters(row, checks) {
    return checks.every(([key, value]) => (row.dataset[key] ?? '').split('|').includes(value));
}
function rememberFilter(el) {
    if (!el.name) return;
    const url = new URL(window.location.href);
    if (el.value !== '') url.searchParams.set(el.name, el.value);
    else url.searchParams.delete(el.name);
    window.history.replaceState(window.history.state, '', url);
}
// Tautan yang ikut pilihan filter (mis. Ekspor iGadis per kelas): <a data-filter-link="kelas" data-href="…">.
// Parameter ditambahkan ke data-href; tautan nonaktif selama pilihan masih kosong.
function syncFilterLinks(el) {
    if (!el.name) return;
    document.querySelectorAll(`[data-filter-link="${el.name}"]`).forEach((link) => {
        const url = new URL(link.dataset.href ?? link.href, window.location.href);
        if (el.value !== '') url.searchParams.set(el.name, el.value);
        else url.searchParams.delete(el.name);
        link.href = url.toString();
        const off = el.value === '';
        link.classList.toggle('pointer-events-none', off);
        link.classList.toggle('opacity-50', off);
        if (off) link.setAttribute('aria-disabled', 'true');
        else link.removeAttribute('aria-disabled');
    });
}

// Daftar yang semua barisnya sudah dimuat: cari (data-search) + dropdown [data-filter] langsung di browser.
// Baris = [data-filter-item]; "empty" dipakai pesan "tidak ada yang cocok".
Alpine.data('listFilter', () => ({
    query: '',
    count: 0,
    empty: false,
    init() {
        const input = this.$root.querySelector('[data-filter-search]');
        this.query = (input?.value ?? '').trim().toLowerCase();
        this.apply();
    },
    search(event) {
        this.query = event.target.value.trim().toLowerCase();
        this.apply();
    },
    filter(event) {
        rememberFilter(event.target);
        syncFilterLinks(event.target);
        this.apply();
    },
    apply() {
        const checks = filterChecks(this.$root);
        const rows = [...this.$root.querySelectorAll('[data-filter-item]')];
        let visible = 0;
        rows.forEach((row) => {
            row.hidden = (this.query !== '' && !(row.dataset.search ?? '').includes(this.query)) || !passesFilters(row, checks);
            if (!row.hidden) visible += 1;
            row.querySelectorAll('[data-sort-no]').forEach((cell) => {
                cell.textContent = String(visible);
            });
        });
        // Kelompok (mis. per tanggal deadline) disembunyikan bila tidak ada baris yang tampil
        this.$root.querySelectorAll('[data-filter-section]').forEach((section) => {
            section.hidden = !section.querySelector('[data-filter-item]:not([hidden])');
        });
        this.count = visible;
        this.empty = visible === 0 && rows.length > 0;
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

// Tabel Plotting (tim & dosen): cari + satu tombol "Filter" berisi kotak centang beberapa kelompok — langsung di browser.
// Dalam satu kelompok centang digabung "atau", antar kelompok "dan". Baris [data-row] membawa data-search (teks kecil)
// dan satu atribut data per kelompok (nilai dipisah "|"); kotak centang [data-filter-group] menyebut nama atribut itu
// dalam bentuk dataset (mis. data-filter-group="quotaSource" ↔ data-quota-source pada baris). Komponen boleh bersarang
// (mis. daftar tim di modal Detail Plottingan di dalam tabel dosen): tiap komponen hanya mengurus elemen yang
// [data-table-filter] terdekatnya adalah akarnya sendiri (atribut itu wajib ditulis di markup akar komponen).
Alpine.data('tableFilter', () => ({
    query: '',
    picked: {},
    menuOpen: false,
    active: 0,
    count: 0,
    empty: false,
    init() {
        this.apply();
    },
    owned(selector) {
        return [...this.$root.querySelectorAll(selector)].filter((el) => el.closest('[data-table-filter]') === this.$root);
    },
    search(event) {
        this.query = event.target.value.trim().toLowerCase();
        this.apply();
    },
    toggleMenu() {
        this.menuOpen = !this.menuOpen;
    },
    closeMenu() {
        this.menuOpen = false;
    },
    pick() {
        const picked = {};
        this.owned('[data-filter-group]:checked').forEach((box) => {
            (picked[box.dataset.filterGroup] ??= []).push(box.value);
        });
        this.picked = picked;
        this.active = Object.values(picked).reduce((total, values) => total + values.length, 0);
        this.apply();
    },
    reset() {
        this.owned('[data-filter-group]').forEach((box) => {
            box.checked = false;
        });
        this.pick();
    },
    get hasActive() {
        return this.active > 0;
    },
    apply() {
        const groups = Object.entries(this.picked);
        const rows = this.owned('[data-row]');
        let visible = 0;
        rows.forEach((row) => {
            row.hidden = (this.query !== '' && !row.dataset.search.includes(this.query))
                || groups.some(([group, values]) => !values.some((value) => (row.dataset[group] ?? '').split('|').includes(value)));
            if (!row.hidden) visible += 1;
        });
        this.count = visible;
        this.empty = visible === 0 && rows.length > 0;
    },
}));

// Pilih satu dosen dengan pencarian nama (modal Plot dosen). Opsi dirender server: [data-option] berisi data-id,
// data-name (nama asli) dan data-search (teks kecil); opsi nonaktif memakai atribut disabled. Nilai terpilih dikirim
// lewat input tersembunyi name="lecturer_id". Awal: data-selected & data-selected-name pada akar komponen.
Alpine.data('plotLecturerPicker', () => ({
    open: false,
    query: '',
    selected: '',
    selectedName: '',
    noResults: false,
    init() {
        this.selected = this.$el.dataset.selected ?? '';
        this.selectedName = this.$el.dataset.selectedName ?? '';
    },
    toggle() {
        this.open = !this.open;
        if (this.open) this.$nextTick(() => this.$refs.search.focus());
    },
    hide() {
        this.open = false;
    },
    search(event) {
        this.query = event.target.value.trim().toLowerCase();
        let visible = 0;
        this.$root.querySelectorAll('[data-option]').forEach((option) => {
            option.hidden = this.query !== '' && !option.dataset.search.includes(this.query);
            if (!option.hidden) visible += 1;
        });
        this.noResults = visible === 0;
    },
    choose(event) {
        const option = event.currentTarget;
        this.selected = option.dataset.id;
        this.selectedName = option.dataset.name;
        this.open = false;
    },
    isChosen(id) {
        return this.selected === String(id);
    },
    get buttonLabel() {
        return this.selectedName || 'Pilih dosen';
    },
    get nothingChosen() {
        return this.selected === '';
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
    // Saring langsung di browser tanpa memuat ulang: teks cari (data-search) dan syarat screening (data-requirement).
    query: '',
    requirement: '',
    // Halaman (opsional, bila akar ber-data-page-size): baris hasil saring dibagi per halaman; 0 = tampilkan semua.
    // Baris di halaman lain disembunyikan & tidak ikut dipilih, jadi "Pilih semua" hanya untuk halaman yang tampil.
    perPage: 0,
    page: 1,
    matches: 0,
    init() {
        this.perPage = Number(this.$el.dataset.pageSize ?? 0);
        this.apply();
    },
    setPerPage(event) {
        this.perPage = Number(event.target.value);
        this.page = 1;
        this.apply();
    },
    prevPage() {
        if (this.page > 1) this.goTo(this.page - 1);
    },
    nextPage() {
        if (this.page < this.pages) this.goTo(this.page + 1);
    },
    pickPage(event) {
        this.goTo(Number(event.currentTarget.dataset.page));
    },
    goTo(page) {
        this.page = page;
        this.apply();
    },
    get pages() {
        return this.perPage > 0 ? Math.max(1, Math.ceil(this.matches / this.perPage)) : 1;
    },
    get pageList() {
        return Array.from({ length: this.pages }, (_, i) => i + 1);
    },
    get onFirstPage() {
        return this.page <= 1;
    },
    get onLastPage() {
        return this.page >= this.pages;
    },
    change(event) {
        this.value = event.target.value;
        this.apply();
    },
    search(event) {
        this.query = event.target.value.trim().toLowerCase();
        this.apply();
    },
    filterRequirement(event) {
        this.requirement = event.target.value;
        this.apply();
    },
    // Dropdown [data-filter] (mis. Peran, Status) langsung di browser
    filter(event) {
        rememberFilter(event.target);
        syncFilterLinks(event.target);
        this.page = 1;
        this.apply();
    },
    apply() {
        const rows = [...this.$root.querySelectorAll('[data-team]')];
        const checks = filterChecks(this.$root);
        const matched = rows.filter((row) => !((this.value !== '' && row.dataset.team !== this.value)
            || (this.query !== '' && !(row.dataset.search ?? '').includes(this.query))
            || (this.requirement !== '' && row.dataset.requirement !== this.requirement)
            || !passesFilters(row, checks)));
        this.matches = matched.length;
        this.page = Math.min(Math.max(1, this.page), this.pages);
        const start = (this.page - 1) * this.perPage;
        const onPage = new Set(this.perPage > 0 ? matched.slice(start, start + this.perPage) : matched);
        let visible = 0;
        rows.forEach((row) => {
            row.hidden = !onPage.has(row);
            // Baris tanpa kotak centang (mis. rekap nilai, dosen yang penilaiannya sudah lengkap) tetap dibagi per halaman.
            const box = row.querySelector('[data-select-row]');
            if (row.hidden && box) box.checked = false;
            if (!row.hidden) visible += 1;
        });
        this.count = visible;
        this.empty = this.matches === 0 && rows.length > 0;
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
        return [...this.$root.querySelectorAll('[data-select-row]')].filter((box) => !box.closest('tr').hidden && !box.disabled);
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

// Dropdown "Urutkan" (komponen <x-sort-by>): urutkan anak [data-sort-item] dari setiap [data-sort-list] di dalam cakupan
// [data-sort-scope] terdekat menurut data-sort-<kunci>. Angka dibandingkan sebagai angka, teks secara alfabet (bahasa
// Indonesia, angka di dalam teks dibaca sebagai angka); nilai kosong selalu di akhir; nilai sama → urutan awal.
// Baris [data-sort-follow] tepat setelah item ikut pindah; baris lain tetap di bawah. Sel [data-sort-no] dinomori ulang.
const SORT_COLLATOR = new Intl.Collator('id', { numeric: true, sensitivity: 'base' });
// Menu tombol "Sort": posisi fixed tepat di bawah tombol (rata kanan; naik ke atas bila tidak muat) agar tidak terpotong
// kartu ber-overflow-hidden. Ditutup saat klik di luar, Escape, gulir, atau ubah ukuran jendela.
function placeSortMenu(button, menu) {
    const rect = button.getBoundingClientRect();
    const width = menu.offsetWidth || 224;
    const height = menu.offsetHeight;
    menu.style.left = `${Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8))}px`;
    const below = rect.bottom + 6;
    const fitsBelow = below + height <= window.innerHeight - 8;
    menu.style.top = `${fitsBelow || rect.top - height - 6 < 8 ? below : rect.top - height - 6}px`;
}
const sortMenuMethods = {
    toggleMenu() {
        this.open = !this.open;
        if (this.open) this.$nextTick(() => placeSortMenu(this.$refs.button, this.$refs.menu));
    },
    closeMenu() {
        this.open = false;
    },
};

// Mode server: pilihan berupa tautan (?urut=&arah=), komponen ini hanya membuka / menutup menu.
Alpine.data('sortMenu', () => ({
    open: false,
    ...sortMenuMethods,
}));

Alpine.data('sortBy', () => ({
    open: false,
    key: '',
    dir: 'asc',
    ...sortMenuMethods,
    init() {
        this.key = this.$el.dataset.default ?? '';
        this.dir = this.$el.dataset.dir === 'desc' ? 'desc' : 'asc';
        this.$nextTick(() => this.apply());
    },
    pickKey(event) {
        this.key = event.currentTarget.dataset.key;
        this.closeMenu();
        this.apply();
    },
    pickDir(event) {
        this.dir = event.currentTarget.dataset.dir;
        this.closeMenu();
        this.apply();
    },
    apply() {
        const target = this.$root.dataset.sortTarget;
        const scope = target ? document.querySelector(target) : this.$root.closest('[data-sort-scope]');
        if (!scope || this.key === '') return;
        const attr = `data-sort-${this.key}`;
        const sign = this.dir === 'desc' ? -1 : 1;
        scope.querySelectorAll('[data-sort-list]').forEach((list) => {
            if (list.closest('[data-sort-scope]') !== scope) return;
            const groups = [];
            const tail = [];
            let current = null;
            [...list.children].forEach((el) => {
                if (el.hasAttribute('data-sort-item')) {
                    if (el.dataset.sortOrigin === undefined) el.dataset.sortOrigin = String(groups.length);
                    current = [el];
                    groups.push(current);
                } else if (current && el.hasAttribute('data-sort-follow')) {
                    current.push(el);
                } else if (groups.length > 0) {
                    current = null;
                    tail.push(el);
                }
            });
            groups.sort((a, b) => {
                const va = (a[0].getAttribute(attr) ?? '').trim();
                const vb = (b[0].getAttribute(attr) ?? '').trim();
                let result;
                if (va === '' || vb === '') {
                    result = Number(va === '') - Number(vb === '');
                } else {
                    const na = Number(va);
                    const nb = Number(vb);
                    result = sign * (Number.isNaN(na) || Number.isNaN(nb) ? SORT_COLLATOR.compare(va, vb) : na - nb);
                }
                return result || Number(a[0].dataset.sortOrigin) - Number(b[0].dataset.sortOrigin);
            });
            const anchor = tail[0] ?? null;
            // Nomor urut hanya untuk baris yang tampil (baris tersaring disembunyikan dengan atribut hidden)
            let number = 0;
            groups.forEach((group) => {
                group.forEach((el) => list.insertBefore(el, anchor));
                if (!group[0].hidden) number += 1;
                group[0].querySelectorAll('[data-sort-no]').forEach((cell) => {
                    cell.textContent = String(number);
                });
            });
        });
        scope.dispatchEvent(new CustomEvent('sorted'));
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

// Tab "Log Bimbingan" dosen: cari langsung di browser; satu <tbody data-search> per tim (nama tim, tanggal, catatan,
// dan feedback seluruh sesinya) disembunyikan bila tidak cocok.
Alpine.data('logSearch', () => ({
    empty: false,
    search(event) {
        const query = event.target.value.trim().toLowerCase();
        let visible = 0;
        this.$root.querySelectorAll('tbody[data-search]').forEach((group) => {
            group.hidden = query !== '' && !group.dataset.search.includes(query);
            if (!group.hidden) visible += 1;
        });
        this.empty = visible === 0;
    },
}));

// Kotak centang "Bekukan kolom indikator" (formulir penilaian dosen): menyalakan / mematikan kolom beku tabel rubrik.
// Bila dimatikan, tabel diberi kelas .no-freeze (lihat app.css) sehingga semua kolom ikut digeser. Pilihan diingat
// per browser (localStorage, bila tersedia).
const FREEZE_KEY = 'capstone.penilaian.bekukan';
Alpine.data('freezeColumns', () => ({
    frozen: true,
    init() {
        try {
            this.frozen = localStorage.getItem(FREEZE_KEY) !== '0';
        } catch {
            this.frozen = true;
        }
    },
    toggle(event) {
        this.frozen = event.target.checked;
        try {
            localStorage.setItem(FREEZE_KEY, this.frozen ? '1' : '0');
        } catch {
            // penyimpanan tidak tersedia: pilihan hanya berlaku sampai halaman dimuat ulang
        }
    },
    get tableClass() {
        return this.frozen ? '' : 'no-freeze';
    },
}));

// Tabel dengan kotak centang per baris: Rubrik Dokumen / Rubrik Lisan ("Edit Pilihan" & "Hapus Pilihan") dan
// formulir penilaian dosen (indikator yang dicentang dinilai sekaligus lewat "Simpan penilaian").
// Kotak centang baris: [data-select-row] (value = id); "pilih semua": [data-select-all]. isSelected(id) dipakai modal
// Edit Pilihan untuk menampilkan isian baris terpilih saja (isian baris lain dinonaktifkan agar tidak terkirim).
Alpine.data('rowSelection', () => ({
    ids: [],
    // Kolom terpilih (opsional): kotak centang [data-select-col] di judul kolom, mis. bentuk penilaian di tabel Indikator.
    cols: [],
    init() {
        this.sync();
    },
    // Boleh bersarang (mis. kartu rumus di dalam halaman Indikator): hanya kotak centang yang [data-row-selection]
    // terdekatnya adalah akar komponen ini (atribut itu wajib ditulis di markup akar).
    owned(selector) {
        return [...this.$root.querySelectorAll(selector)].filter((el) => el.closest('[data-row-selection]') === this.$root);
    },
    pick(event) {
        if (event.target.matches('[data-select-all]')) {
            this.owned('[data-select-row]').forEach((box) => {
                box.checked = event.target.checked;
            });
        }
        this.sync();
    },
    sync() {
        const boxes = this.owned('[data-select-row]');
        this.ids = boxes.filter((box) => box.checked).map((box) => box.value);
        this.cols = this.owned('[data-select-col]:checked').map((box) => box.value);
        const all = this.owned('[data-select-all]')[0];
        if (all) all.checked = boxes.length > 0 && this.ids.length === boxes.length;
    },
    isSelected(id) {
        return this.ids.includes(String(id));
    },
    isColSelected(key) {
        return this.cols.includes(String(key));
    },
    get count() {
        return this.ids.length + this.cols.length;
    },
    get noneSelected() {
        return this.ids.length === 0 && this.cols.length === 0;
    },
}));

/*
 * Periode baru (admin/periods/form, belum tersimpan): semua isian disusun di browser lalu dikirim sekaligus dengan
 * tombol "Buat Periode". Tanggal mulai & selesai terisi → Timeline muncul dan milestone dapat ditambah (modal);
 * milestone berjenis Dokumen → tersedia sebagai pilihan Folder Google Drive Database. Milestone dikirim sebagai JSON
 * (input draft_milestones), folder sebagai drive_folders[<kunci draf>]. Daftar & timeline digambar ulang lewat DOM
 * (build CSP Alpine tidak mengevaluasi ekspresi bebas). "Salin data master dari" menukar tabel jadwal penilaian.
 */
const DRAFT_DATE = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const DRAFT_DAY = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

function draftEscape(text) {
    const span = document.createElement('span');
    span.textContent = text ?? '';
    return span.innerHTML;
}

function draftDay(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value ?? '')) return null;
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}

Alpine.data('periodDraft', () => ({
    starts: null,
    ends: null,
    milestones: [],
    editing: null,
    expanded: false,
    seq: 0,
    labels: {},
    init() {
        this.labels = JSON.parse(this.$el.dataset.labels || '{}');
        try {
            this.milestones = JSON.parse(this.$el.dataset.oldMilestones || '[]');
        } catch {
            this.milestones = [];
        }
        this.seq = this.milestones.reduce((max, m) => Math.max(max, Number(String(m.key).replace(/\D/g, '')) || 0), 0);
        this.readDates();
        this.pickSource();
        let folders = {};
        try {
            folders = JSON.parse(this.$el.dataset.oldFolders || '{}');
        } catch {
            folders = {};
        }
        this.render(folders);
    },
    readDates() {
        this.starts = draftDay(this.$root.querySelector('#starts_on')?.value);
        this.ends = draftDay(this.$root.querySelector('#ends_on')?.value);
    },
    dates() {
        this.readDates();
        this.renderTimeline();
        this.renderList();
    },
    get hasRange() {
        return Boolean(this.starts && this.ends && this.ends > this.starts);
    },
    get noRange() {
        return !this.hasRange;
    },
    get weeks() {
        if (!this.hasRange) return 0;
        const days = Math.round((this.ends - this.starts) / 86400000);
        return Math.max(1, Math.ceil((days + 1) / 7));
    },
    get rangeLabel() {
        return this.hasRange ? `${DRAFT_DAY.format(this.starts)} – ${DRAFT_DAY.format(this.ends)}` : '';
    },
    get milestoneCount() {
        return `${this.milestones.length} milestone`;
    },
    get milestoneHeading() {
        return this.editing === null ? 'Tambah milestone' : 'Ubah milestone';
    },
    weekOf(value) {
        const due = new Date(value);
        if (!this.hasRange || Number.isNaN(due.getTime())) return null;
        const days = (due - this.starts) / 86400000;
        return Math.min(this.weeks, Math.max(1, Math.floor(days / 7) + 1));
    },

    // "Salin data master dari": tampilkan jadwal bentuk penilaian milik sumber terpilih saja.
    pickSource() {
        const select = this.$root.querySelector('#master_source');
        const value = select ? select.value : '';
        this.$root.querySelectorAll('[data-source]').forEach((group) => {
            const own = group.dataset.source === value;
            group.hidden = !own;
            group.querySelectorAll('input').forEach((input) => {
                input.disabled = !own;
            });
        });
    },

    // Modal milestone (x-modal memakai expanded & close dari komponen ini)
    open() {
        this.expanded = true;
        const form = this.form();
        requestAnimationFrame(() => form?.querySelector('input[name=name]')?.focus({ preventScroll: true }));
    },
    close() {
        this.expanded = false;
    },
    form() {
        return this.$root.querySelector('[data-draft-milestone]');
    },
    newMilestone() {
        if (this.noRange) return;
        this.editing = null;
        this.fill({ category: 'dokumen', allowed_types: [] });
        this.open();
    },
    fill(m) {
        const form = this.form();
        form.reset();
        const set = (name, value) => {
            if (form.elements[name]) form.elements[name].value = value ?? '';
        };
        set('name', m.name);
        set('starts_at', m.starts_at);
        set('due_at', m.due_at);
        set('description', m.description);
        const category = form.elements.category;
        category.value = m.category || 'dokumen';
        category.dispatchEvent(new Event('change', { bubbles: true }));
        if (m.document_type) set('document_type', m.document_type);
        if (m.kind && m.kind !== 'dokumen') set('kind', m.kind);
        form.querySelectorAll('input[name="allowed_types[]"]').forEach((box) => {
            box.checked = (m.allowed_types ?? []).includes(box.value);
            box.setCustomValidity('');
        });
        form.elements.due_at.setCustomValidity('');
    },
    saveMilestone(event) {
        const form = event.target;
        const value = (name) => (form.elements[name]?.value ?? '').trim();
        const category = value('category') || 'dokumen';
        const types = [...form.querySelectorAll('input[name="allowed_types[]"]:checked')].map((box) => box.value);
        const boxes = form.querySelectorAll('input[name="allowed_types[]"]');
        boxes.forEach((box) => box.setCustomValidity(''));
        if (category === 'dokumen' && types.length === 0 && boxes[0]) boxes[0].setCustomValidity('Pilih minimal satu tipe file.');
        const due = form.elements.due_at;
        due.setCustomValidity(value('starts_at') && value('due_at') && value('due_at') <= value('starts_at') ? 'Deadline harus setelah waktu dibuka.' : '');
        if (!form.reportValidity()) return;

        const milestone = {
            key: this.editing === null ? `d${++this.seq}` : this.milestones[this.editing].key,
            name: value('name'),
            category,
            kind: category === 'dokumen' ? 'dokumen' : value('kind'),
            document_type: category === 'dokumen' ? value('document_type') : null,
            allowed_types: category === 'dokumen' ? types : [],
            starts_at: value('starts_at'),
            due_at: value('due_at'),
            description: value('description'),
        };
        if (this.editing === null) this.milestones.push(milestone);
        else this.milestones.splice(this.editing, 1, milestone);
        this.milestones.sort((a, b) => a.due_at.localeCompare(b.due_at));
        this.editing = null;
        this.close();
        this.render();
    },
    listClick(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const index = Number(button.dataset.index);
        if (button.dataset.action === 'edit') {
            this.editing = index;
            this.fill(this.milestones[index]);
            this.open();
        } else {
            this.milestones.splice(index, 1);
            this.render();
        }
    },

    render(folders = null) {
        const input = this.$root.querySelector('input[name=draft_milestones]');
        if (input) input.value = JSON.stringify(this.milestones);
        this.renderList();
        this.renderTimeline();
        this.syncFolders(folders);
    },
    describe(m) {
        const category = this.labels.categories?.[m.category] ?? m.category;
        const detail = m.category === 'dokumen'
            ? `${this.labels.documents?.[m.document_type] ?? m.document_type} (${(m.allowed_types ?? []).map((t) => this.labels.files?.[t] ?? t).join(', ')})`
            : (this.labels.kinds?.[m.kind] ?? m.kind);
        const start = new Date(m.starts_at);
        const due = new Date(m.due_at);
        return { category, detail, range: `${DRAFT_DATE.format(start)} → ${DRAFT_DATE.format(due)}` };
    },
    renderList() {
        const list = this.$root.querySelector('[data-draft-list]');
        if (!list) return;
        list.hidden = this.milestones.length === 0;
        list.innerHTML = this.milestones.map((m, index) => {
            const info = this.describe(m);
            const week = this.weekOf(m.due_at);
            return `<li class="px-4 py-3 sm:px-6"><div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="w-14 shrink-0 tabular text-sm text-muted">${week ? `Mg ${week}` : '—'}</span>
                <div class="min-w-0 flex-1"><p class="font-medium">${draftEscape(m.name)}</p>
                <p class="text-sm text-muted"><span class="mr-1 rounded-md border border-rule bg-sunken px-1.5 py-0.5 text-caption font-medium">${draftEscape(info.category)}</span> ${draftEscape(info.detail)} · ${draftEscape(info.range)}</p></div>
                <button type="button" class="btn btn-secondary btn-sm" data-action="edit" data-index="${index}">Ubah</button>
                <button type="button" class="btn btn-ghost btn-sm text-bad-ink" data-action="remove" data-index="${index}" aria-label="Hapus ${draftEscape(m.name)}">Hapus</button>
            </div></li>`;
        }).join('');
    },
    renderTimeline() {
        const list = this.$root.querySelector('[data-draft-timeline]');
        if (!list || !this.hasRange) return;
        const weeks = this.weeks;
        const icon = this.$root.querySelector('template[data-doc-icon]')?.innerHTML ?? '';
        const byWeek = {};
        this.milestones.forEach((m) => {
            const week = this.weekOf(m.due_at);
            if (week) (byWeek[week] ??= []).push(m);
        });
        list.style.gridTemplateColumns = `repeat(${weeks}, minmax(0, 1fr))`;
        list.setAttribute('aria-label', `Timeline ${weeks} minggu`);
        let html = '';
        for (let w = 1; w <= weeks; w += 1) {
            const items = byWeek[w] ?? [];
            const start = new Date(this.starts.getTime() + (w - 1) * 7 * 86400000);
            const end = new Date(start.getTime() + 6 * 86400000);
            const tip = [`Minggu ${w} (${DRAFT_DAY.format(start)} – ${DRAFT_DAY.format(end)})`, ...items.map((m) => `• ${m.name} · deadline ${DRAFT_DATE.format(new Date(m.due_at))}`)].join('\n');
            html += `<li class="relative flex flex-col items-center" title="${draftEscape(tip)}">
                <span class="relative flex h-8 w-full items-center justify-center sm:h-9" aria-hidden="true">
                    ${w > 1 ? '<span class="absolute left-0 right-1/2 top-1/2 h-[3px] -translate-y-1/2 bg-rule-strong"></span>' : ''}
                    ${w < weeks ? '<span class="absolute left-1/2 right-0 top-1/2 h-[3px] -translate-y-1/2 bg-rule-strong"></span>' : ''}
                    <span class="relative grid h-3.5 w-3.5 shrink-0 place-items-center rounded-full border-2 sm:h-5 sm:w-5 ${items.length ? 'border-brand bg-brand-tint text-brand-deep' : 'border-rule-strong bg-surface text-muted'}">${items.length ? icon : ''}</span>
                </span>
                <span class="mt-1.5 tabular text-[0.6875rem] text-muted sm:text-caption">${w}<span class="sr-only">${items.length ? `, ${items.length} milestone` : ''}</span></span>
            </li>`;
        }
        list.innerHTML = html;
    },
    // Baris Folder Google Drive Database mengikuti milestone dokumen draf (kunci draf, label = nama milestone).
    syncFolders(values = null) {
        const box = this.$root.querySelector('[data-folder-rows]');
        const template = this.$root.querySelector('template[data-folder-template]');
        if (!box || !template) return;
        const documents = this.milestones.filter((m) => m.category === 'dokumen');
        const keys = documents.map((m) => m.key);
        box.querySelectorAll('[data-folder-row]').forEach((row) => {
            if (!keys.includes(row.dataset.key)) row.remove();
        });
        documents.forEach((m) => {
            let row = box.querySelector(`[data-folder-row][data-key="${m.key}"]`);
            if (!row) {
                row = template.content.firstElementChild.cloneNode(true);
                row.dataset.key = m.key;
                const input = row.querySelector('input');
                input.name = `drive_folders[${m.key}]`;
                input.id = `drive_folders.${m.key}`;
                row.querySelector('label').htmlFor = input.id;
                const value = values?.[m.key] ?? '';
                input.value = value;
                row.hidden = value === '';
                box.appendChild(row);
            }
            row.dataset.label = m.name;
            row.querySelector('[data-folder-label]').textContent = m.name;
        });
        window.dispatchEvent(new CustomEvent('drive-rows-changed'));
    },
}));

/*
 * Folder Google Drive Database (periode baru & ubah periode): satu baris per milestone dokumen, disembunyikan bila
 * belum diisi. Tombol "Tambah Folder" membuka modal: pilih milestone dokumen (dropdown) + tautan folder → Simpan
 * menampilkan barisnya; "Hapus" mengosongkan tautan dan menyembunyikan barisnya lagi (tersimpan kosong = dokumen
 * milestone itu tidak disinkronkan ke Drive). Folder tersimpan ke database bersama tombol Simpan/Buat Periode.
 */
Alpine.data('driveFolders', () => ({
    total: 0,
    shown: 0,
    available: 0,
    expanded: false,
    init() {
        this.refresh();
    },
    rows() {
        return [...this.$root.querySelectorAll('[data-folder-row]')];
    },
    refresh() {
        const rows = this.rows();
        const select = this.$refs.pick;
        const current = select.value;
        select.innerHTML = '';
        rows.filter((row) => row.hidden).forEach((row) => {
            select.appendChild(new Option(row.dataset.label, row.dataset.key));
        });
        if ([...select.options].some((option) => option.value === current)) select.value = current;
        this.total = rows.length;
        this.shown = rows.filter((row) => !row.hidden).length;
        this.available = select.options.length;
        if (this.available === 0) this.expanded = false;
    },
    get cannotAdd() {
        return this.available === 0;
    },
    get addTitle() {
        if (this.total === 0) return 'Tambahkan milestone berjenis Dokumen terlebih dahulu';
        return this.available === 0 ? 'Semua milestone dokumen sudah punya folder' : '';
    },
    open() {
        if (this.cannotAdd) return;
        this.$refs.url.value = '';
        this.$refs.pick.selectedIndex = 0;
        this.expanded = true;
        requestAnimationFrame(() => this.$refs.pick.focus({ preventScroll: true }));
    },
    close() {
        this.expanded = false;
    },
    save() {
        const row = this.rows().find((item) => item.dataset.key === this.$refs.pick.value);
        if (!row) return;
        row.querySelector('input').value = this.$refs.url.value.trim();
        row.hidden = false;
        this.close();
        this.refresh();
    },
    get noMilestones() {
        return this.total === 0;
    },
    get noFolders() {
        return this.total > 0 && this.shown === 0;
    },
    get hasFolders() {
        return this.shown > 0;
    },
    remove(event) {
        const row = event.target.closest('[data-folder-row]');
        row.querySelector('input').value = '';
        row.hidden = true;
        this.refresh();
    },
}));

/*
 * Periode baru: bentuk penilaian dosen tambahan (selain bawaan data master) disusun di browser dan dikirim sebagai
 * JSON (input draft_components): nama, penilai, dibuka & deadline (opsional).
 */
Alpine.data('draftComponents', () => ({
    items: [],
    expanded: false,
    init() {
        try {
            this.items = JSON.parse(this.$el.dataset.old || '[]');
        } catch {
            this.items = [];
        }
        this.render();
    },
    open() {
        const form = this.$root.querySelector('[data-draft-component]');
        form.reset();
        // Pilihan Jenis dokumen = jenis dokumen milestone draf (input draft_milestones milik periodDraft)
        let milestones = [];
        try {
            milestones = JSON.parse(document.querySelector('input[name=draft_milestones]')?.value || '[]');
        } catch {
            milestones = [];
        }
        const labels = JSON.parse(this.$root.dataset.documents || '{}');
        const used = new Set(milestones.filter((m) => m.category === 'dokumen' && m.document_type).map((m) => m.document_type));
        const types = Object.keys(labels).filter((type) => used.has(type));
        form.querySelector('[data-draft-doc-types]').innerHTML = types.map((type) => `<label class="flex min-h-[44px] items-center gap-3 text-sm text-ink">
            <input type="checkbox" class="checkbox" name="document_types[]" value="${draftEscape(type)}"> ${draftEscape(labels[type] ?? type)}</label>`).join('');
        form.querySelector('[data-draft-doc-empty]').hidden = types.length > 0;
        this.expanded = true;
        requestAnimationFrame(() => form.querySelector('input[name=label]')?.focus({ preventScroll: true }));
    },
    close() {
        this.expanded = false;
    },
    save(event) {
        const form = event.target;
        const value = (name) => (form.elements[name]?.value ?? '').trim();
        const roles = [...form.querySelectorAll('input[name="roles[]"]:checked')].map((box) => box.value);
        const boxes = form.querySelectorAll('input[name="roles[]"]');
        boxes.forEach((box) => box.setCustomValidity(''));
        if (roles.length === 0 && boxes[0]) boxes[0].setCustomValidity('Pilih minimal satu penilai.');
        const due = form.elements.due_at;
        const opens = value('opens_at');
        const dueAt = value('due_at');
        due.setCustomValidity((opens === '') !== (dueAt === '') ? 'Isi waktu dibuka dan deadline, atau kosongkan keduanya.'
            : (opens && dueAt <= opens ? 'Deadline penilaian harus setelah waktu dibuka.' : ''));
        if (!form.reportValidity()) return;
        const documentTypes = [...form.querySelectorAll('input[name="document_types[]"]:checked')].map((box) => box.value);
        this.items.push({ label: value('label'), roles, document_types: documentTypes, opens_at: opens, due_at: dueAt });
        this.close();
        this.render();
    },
    listClick(event) {
        const button = event.target.closest('[data-action=remove]');
        if (!button) return;
        this.items.splice(Number(button.dataset.index), 1);
        this.render();
    },
    render() {
        this.$root.querySelector('input[name=draft_components]').value = JSON.stringify(this.items);
        const body = this.$root.querySelector('[data-draft-components]');
        const roleLabels = JSON.parse(this.$root.dataset.roles || '{}');
        const docLabels = JSON.parse(this.$root.dataset.documents || '{}');
        const when = (value) => (value ? DRAFT_DATE.format(new Date(value)) : 'Belum diatur');
        body.innerHTML = this.items.map((item, index) => `<tr>
            <td class="font-medium">${draftEscape(item.label)} <span class="ml-1 rounded-md border border-rule bg-sunken px-1.5 py-0.5 text-caption font-medium text-muted">Baru</span></td>
            <td class="text-muted">${draftEscape(item.roles.map((r) => roleLabels[r] ?? r).join(' & '))}</td>
            <td class="text-muted">${draftEscape((item.document_types ?? []).map((t) => docLabels[t] ?? t).join(', ') || '—')}</td>
            <td class="tabular ${item.opens_at ? 'text-ink' : 'text-muted'}">${draftEscape(when(item.opens_at))}</td>
            <td class="tabular ${item.due_at ? 'text-ink' : 'text-muted'}">${draftEscape(when(item.due_at))}</td>
            <td><div class="flex justify-end"><button type="button" class="btn btn-ghost btn-sm text-bad-ink" data-action="remove" data-index="${index}">Hapus</button></div></td>
        </tr>`).join('');
    },
}));

// Modal "Ubah rumus" (kartu Cara nilai dihitung): isian angka tetap [data-fixed] hanya aktif bila pembagi = angka tetap.
Alpine.data('formulaDivisor', () => ({
    pick(event) {
        const input = this.$root.querySelector('[data-fixed]');
        if (input) input.disabled = event.target.value !== 'fixed';
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
    get chevronClass() {
        return this.expanded ? 'rotate-180' : '';
    },
}));

// Formulir di atas halaman (registrasi tim, registrasi topik): tersembunyi sampai tombolnya ditekan,
// terbuka bila ada error validasi (data-open="true"). Panel formulir diberi x-ref="panel".
// Tabel penilaian dosen: centang indikator (satu per satu, semua indikator satu CLO lewat centang baris judul CLO, atau
// semua) untuk menilainya sekaligus. Jenis centang (data-kind): both = skenario 1 (interval + lisan sekaligus),
// doc / oral = skenario 2 (kolom Dokumen & Lisan dicentang terpisah). Nilai pada baris tercentang PALING ATAS yang aktif;
// dropdown baris tercentang lainnya mati (kelas score-mirror, tetap terkirim) dan mengikuti nilainya — interval dibatasi
// data-max tiap indikator. Baris yang tidak dicentang dinilai sendiri. Skenario 1 (data-mode="sequential"): CLO berikutnya
// terkunci sampai CLO sebelumnya lengkap. Submit Final aktif bila semua indikator terisi (indikator lisan dengan
// interval > 0 wajib lisan). data-locked = sebelum jendela penilaian dibuka / admin (lihat saja).
Alpine.data('scoreSheet', () => ({
    missing: 0,
    locked: false,
    // Simpan otomatis sebagai draft: setiap perubahan interval / lisan dikirim ±0,6 dtk setelah berhenti mengubah,
    // dan dikirim seketika (keepalive) bila halaman ditinggalkan sebelum sempat tersimpan.
    autosave: false,
    saveState: 'idle', // idle | pending | saving | saved | error
    savedAt: '',
    wasFinal: false,
    dirty: false,
    timer: null,
    inFlight: false,
    init() {
        this.locked = this.$el.dataset.locked === 'true';
        this.autosave = this.$el.dataset.autosave === 'true';
        this.savedAt = this.$el.dataset.savedAt || '';
        if (this.savedAt) this.saveState = 'saved';
        this.sync();
        const form = document.getElementById('nilai-form');
        if (form) {
            // Simpan Final mengirim semua isian sendiri: batalkan simpan otomatis yang tertunda
            form.addEventListener('submit', () => {
                clearTimeout(this.timer);
                this.dirty = false;
            });
        }
        window.addEventListener('pagehide', () => {
            if (this.dirty) this.save(true);
        });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden' && this.dirty) this.save(true);
        });
    },
    schedule() {
        if (!this.autosave) return;
        this.dirty = true;
        this.saveState = 'pending';
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(false), 600);
    },
    async save(leaving) {
        const form = document.getElementById('nilai-form');
        if (!form || !this.dirty) return;
        if (this.inFlight && !leaving) {
            // Sedang mengirim: kirim ulang setelah selesai agar perubahan terakhir ikut tersimpan
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(false), 400);
            return;
        }
        clearTimeout(this.timer);
        this.dirty = false;
        this.inFlight = true;
        this.saveState = 'saving';
        const body = new FormData(form);
        body.set('mode', 'draft');
        body.set('autosave', '1');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                keepalive: leaving,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error(String(response.status));
            this.applySaved(await response.json());
        } catch {
            this.dirty = true;
            this.saveState = 'error';
        } finally {
            this.inFlight = false;
        }
    },
    applySaved(data) {
        if (data.status === 'none') {
            this.saveState = 'idle';
            return;
        }
        this.savedAt = data.saved_at || '';
        this.wasFinal = this.wasFinal || Boolean(data.was_final);
        this.saveState = this.dirty ? 'pending' : 'saved';
        const scored = new Set((data.scored || []).map(String));
        // Baris hijau = indikator yang sudah tersimpan nilainya; progres & status di judul halaman ikut diperbarui
        this.$root.querySelectorAll('tr[data-clo]').forEach((row) => {
            const select = row.querySelector('[data-interval]');
            if (!select) return;
            const on = scored.has(select.dataset.interval);
            row.classList.toggle('bg-ok-tint', on);
            row.toggleAttribute('data-scored', on);
        });
        const count = this.$root.querySelector('[data-scored-count]');
        if (count) count.textContent = String(scored.size);
        const bar = this.$root.querySelector('#nilai-form [role="progressbar"]');
        if (bar) {
            bar.setAttribute('aria-valuenow', String(Math.min(scored.size, data.total)));
            const fill = bar.firstElementChild;
            if (fill) fill.style.width = `${data.total > 0 ? Math.round((scored.size / data.total) * 100) : 0}%`;
        }
        document.querySelectorAll('[data-status-badge]').forEach((badge) => {
            badge.hidden = badge.dataset.statusBadge !== (data.status === 'final' ? 'final' : 'belum');
        });
    },
    get saveLabel() {
        if (!this.autosave) return 'Penilaian dikunci';
        if (this.saveState === 'pending' || this.saveState === 'saving') return 'Menyimpan draft…';
        if (this.saveState === 'error') return 'Gagal menyimpan otomatis, periksa koneksi lalu ubah isian lagi';
        if (this.saveState === 'saved') {
            return `Draft tersimpan otomatis${this.savedAt ? ` pukul ${this.savedAt}` : ''}${this.wasFinal ? ', klik Submit Final lagi agar terlihat mahasiswa' : ''}`;
        }
        return 'Perubahan tersimpan otomatis sebagai draft';
    },
    get saveTone() {
        if (this.saveState === 'error') return 'text-bad-ink';
        if (this.saveState === 'saved') return 'text-ok-ink';
        return 'text-muted';
    },
    boxes(selector = '[data-select-row]') {
        return [...this.$root.querySelectorAll(selector)];
    },
    change(event) {
        const target = event.target;
        // Centang semua / satu CLO berlaku untuk jenis centangnya: both (skenario 1), doc / oral (skenario 2)
        const kind = target.dataset.kind || 'both';
        if (target.matches('[data-select-all]')) {
            this.boxes(`[data-select-row][data-kind="${kind}"]`).filter((box) => !box.disabled).forEach((box) => { box.checked = target.checked; });
        }
        if (target.dataset.selectGroup !== undefined) {
            this.boxes(`[data-select-row][data-kind="${kind}"][data-clo="${target.dataset.selectGroup}"]`).filter((box) => !box.disabled)
                .forEach((box) => { box.checked = target.checked; });
        }
        this.sync();
        // Perubahan interval / pemahaman lisan, atau centang yang membuat baris mengikuti nilai baris lain → simpan otomatis
        if (target.matches('[data-interval], [data-oral], [data-select-row], [data-select-group], [data-select-all]')) this.schedule();
    },
    mirror(selects, value, clamp) {
        selects.forEach((select) => {
            select.value = clamp && value !== '' ? String(Math.min(Number(value), Number(select.dataset.max))) : value;
        });
    },
    lock(select, on) {
        if (!select) return;
        select.classList.toggle('score-mirror', on);
        select.tabIndex = on ? -1 : 0;
        if (on) select.setAttribute('aria-disabled', 'true'); else select.removeAttribute('aria-disabled');
    },
    interval(id) {
        return this.$root.querySelector(`[data-interval="${id}"]`);
    },
    oral(id) {
        return this.$root.querySelector(`[data-oral="${id}"]`);
    },
    // Indikator lengkap: interval terisi, dan lisan terisi bila indikatornya berlisan (interval 0 = tanpa lisan)
    filled(id) {
        const select = this.interval(id);
        const o = this.oral(id);
        return Boolean(select) && select.value !== '' && (!o || select.value === '0' || o.value !== '');
    },
    sync() {
        this.boxes('[data-interval]').forEach((select) => this.lock(select, false));
        this.boxes('[data-oral]').forEach((select) => this.lock(select, false));
        const kinds = [...new Set(this.boxes().map((box) => box.dataset.kind || 'both'))];
        kinds.forEach((kind) => {
            const rows = this.boxes(`[data-select-row][data-kind="${kind}"]`);
            const checked = rows.filter((box) => box.checked && !box.disabled).map((box) => box.value);
            // Baris tercentang paling atas = sumber nilai; baris tercentang lainnya mati & mengikuti nilainya
            if (kind !== 'oral' && checked.length) {
                checked.slice(1).forEach((id) => this.lock(this.interval(id), true));
                this.mirror(checked.slice(1).map((id) => this.interval(id)), this.interval(checked[0]).value, true);
            }
            if (kind !== 'doc') {
                const orals = checked.filter((id) => this.oral(id));
                if (orals.length) {
                    orals.slice(1).forEach((id) => this.lock(this.oral(id), true));
                    this.mirror(orals.slice(1).map((id) => this.oral(id)), this.oral(orals[0]).value, false);
                }
            }
            // Centang baris judul CLO & centang semua: penuh / sebagian
            this.boxes(`[data-select-group][data-kind="${kind}"]`).forEach((group) => {
                const own = rows.filter((box) => box.dataset.clo === group.dataset.selectGroup);
                const on = own.filter((box) => box.checked).length;
                group.checked = on > 0 && on === own.length;
                group.indeterminate = on > 0 && on < own.length;
            });
            const all = this.$root.querySelector(`[data-select-all][data-kind="${kind}"]`);
            if (all) {
                const on = rows.filter((box) => box.checked).length;
                all.checked = on > 0 && on === rows.length;
                all.indeterminate = on > 0 && on < rows.length;
            }
        });
        if (this.$root.dataset.mode === 'sequential' && !this.locked) this.sequence();

        this.missing = this.boxes('[data-interval]').filter((select) => !this.filled(select.dataset.interval)).length;
    },
    // Skenario 1: CLO dinilai berurutan — CLO berikutnya terkunci (dropdown & centang mati) sampai semua indikator
    // CLO sebelumnya terisi. Dropdown yang mati tidak ikut terkirim, sehingga nilai tersimpannya tidak berubah.
    sequence() {
        let blocked = false;
        this.boxes('tbody[data-group]').forEach((group) => {
            const rows = [...group.querySelectorAll('tr[data-clo]')];
            group.querySelectorAll('[data-select-row], [data-select-group], [data-interval], [data-oral]').forEach((el) => {
                el.disabled = blocked;
                if (blocked && el.type === 'checkbox') el.checked = false;
            });
            group.querySelector('[data-group-lock]')?.toggleAttribute('hidden', !blocked);
            rows.forEach((row) => row.classList.toggle('opacity-60', blocked));
            if (!blocked && !rows.every((row) => this.filled(row.querySelector('[data-interval]')?.dataset.interval))) blocked = true;
        });
    },
    get finalLocked() {
        return this.locked || this.missing > 0;
    },
    get finalTitle() {
        return this.missing > 0 ? `Masih ${this.missing} indikator belum diisi` : 'Simpan sebagai nilai final';
    },
}));

// Teks panjang (poin rubrikasi): kalimat utama + "…"; tombol › membuka / menutup teks lengkap.
Alpine.data('clampText', () => ({
    open: false,
    toggle() {
        this.open = !this.open;
    },
    get closed() {
        return !this.open;
    },
    get label() {
        return this.open ? 'Sembunyikan sebagian teks' : 'Tampilkan semua teks';
    },
    get iconClass() {
        return this.open ? 'rotate-90' : '';
    },
}));

// Uji Reminder (admin): tombol "Kirim pesan uji" aktif bila nomor WhatsApp atau email uji diisi (minimal salah satu).
Alpine.data('reminderTest', () => ({
    ready: false,
    init() {
        this.check();
    },
    check() {
        this.ready = ['phone', 'email'].some((ref) => (this.$refs[ref]?.value ?? '').trim() !== '');
    },
}));

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
            if (field('class_name') && option.class_name) field('class_name').value = option.class_name;
            if (option.region) section.querySelector('[data-region-picker]')?.dispatchEvent(new CustomEvent('region-fill', { detail: option.region }));
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

// Daerah asal (registrasi akun & tim): pilih satu kabupaten/kota dari #data-wilayah ([nama, provinsi], urut abjad).
// Cari per kata pada nama & provinsi ("kab bandung", "jawa barat"); Enter memilih hasil teratas. Nilai dikirim lewat
// input tersembunyi; studentPicker mengisinya lewat event "region-fill" (detail = nama); nama di luar daftar = isian manual.
let regionOptions = null;

Alpine.data('regionPicker', () => ({
    options: [],
    query: '',
    open: false,
    selected: '',
    // Diketik manual (kabupaten/kota tidak ada di daftar): pilihan "Gunakan ..." muncul saat pencarian kosong
    manual: false,
    init() {
        regionOptions ??= JSON.parse(document.getElementById('data-wilayah')?.textContent ?? '[]')
            .map(([name, province]) => ({ name, province, search: `${name} ${province}`.toLowerCase() }));
        this.options = regionOptions;
        // Nilai tersimpan (old() / data mahasiswa) boleh di luar daftar = isian manual sebelumnya
        this.pick(this.$el.dataset.selected ?? '', true);
    },
    get results() {
        const words = this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
        return words.length ? this.options.filter((o) => words.every((w) => o.search.includes(w))) : this.options;
    },
    get noResults() {
        return this.results.length === 0;
    },
    get hasSelection() {
        return this.selected !== '';
    },
    get noSelection() {
        return this.selected === '';
    },
    get expanded() {
        return this.open ? 'true' : 'false';
    },
    get selectedLabel() {
        const option = this.options.find((o) => o.name === this.selected);
        return option ? `${option.name}, ${option.province}` : this.selected;
    },
    get typed() {
        return this.query.trim().replace(/\s+/g, ' ');
    },
    get canUseTyped() {
        return this.noResults && this.typed.length >= 3;
    },
    get typedLabel() {
        return `Gunakan "${this.typed}"`;
    },
    useTyped() {
        if (!this.canUseTyped) return;
        this.selected = this.typed;
        this.manual = true;
        this.query = '';
        this.open = false;
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
        this.pick(event.currentTarget.dataset.name);
    },
    chooseFirst() {
        if (this.results.length) this.pick(this.results[0].name);
        else this.useTyped();
    },
    fill(event) {
        this.pick(event.detail ?? '', true);
    },
    // allowManual: nilai di luar daftar diterima sebagai isian manual (nilai tersimpan / data mahasiswa)
    pick(name, allowManual = false) {
        const listed = this.options.some((o) => o.name === name);
        if (!listed && !(allowManual && String(name).trim() !== '')) return;
        this.selected = name;
        this.manual = !listed;
        this.query = '';
        this.open = false;
    },
    clear() {
        this.selected = '';
        this.manual = false;
        this.open = true;
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
        const box = this.$refs.rows;
        if (!box) return;
        const rows = [...box.querySelectorAll('[data-search]')].filter((row) => !row.hidden);
        this.overflow = rows.length > 10;
        box.style.maxHeight = this.overflow ? `${rows[10].offsetTop + rows[10].offsetHeight / 2}px` : '';
        this.track();
    },
    track() {
        const box = this.$refs.rows;
        this.atEnd = box.scrollTop + box.clientHeight >= box.scrollHeight - 2;
    },
    get showArrow() {
        return this.overflow && !this.atEnd;
    },
    down() {
        const box = this.$refs.rows;
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
// ketik: data-confirm-type="teks" → tombol aktif setelah teks itu diketik persis; nilainya dikirim sebagai data-confirm-type-field
Alpine.store('confirm', { open: false, message: '', label: 'Hapus', tone: 'danger', notes: null, notesLabel: '', typeText: null, typeField: null, form: null, submitter: null });

Alpine.data('confirmDialog', () => ({
    typed: '',
    init() {
        this.$watch('open', (open) => {
            if (!open) return;
            this.$refs.notes.value = '';
            this.$refs.typed.value = '';
            this.typed = '';
            this.$nextTick(() => {
                if (this.withTyping) this.$refs.typed.focus();
                else (this.withNotes ? this.$refs.notes : this.$refs.cancel).focus();
            });
        });
    },
    typing(event) {
        this.typed = event.target.value;
    },
    get withTyping() {
        return Boolean(Alpine.store('confirm').typeText);
    },
    get typeText() {
        return Alpine.store('confirm').typeText ?? '';
    },
    get blocked() {
        return this.withTyping && this.typed.trim() !== this.typeText;
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
        if (this.blocked) return;
        const { form, submitter, notes, typeField } = Alpine.store('confirm');
        Alpine.store('confirm').open = false;
        if (typeField) {
            const field = form.elements[typeField] ?? form.appendChild(Object.assign(document.createElement('input'), { type: 'hidden', name: typeField }));
            field.value = this.typed.trim();
        }
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
        typeText: form.dataset.confirmType ?? null,
        typeField: form.dataset.confirmTypeField ?? null,
        form,
        submitter: event.submitter ?? null,
    });
});

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

// Posisi gulir menu sidebar dipertahankan antarhalaman: disimpan di sini saat menggulir / meninggalkan halaman, dipulihkan
// oleh skrip kecil tepat setelah <nav> di layout (sebelum halaman tampil, tanpa lompatan).
const sidebarScroll = document.querySelector('[data-sidebar-scroll]');
if (sidebarScroll) {
    const remember = () => {
        try {
            sessionStorage.setItem('sidebar-scroll', String(sidebarScroll.scrollTop));
        } catch {
            // penyimpanan diblokir browser: abaikan
        }
    };
    sidebarScroll.addEventListener('scroll', remember, { passive: true });
    window.addEventListener('pagehide', remember);
}

window.Alpine = Alpine;
Alpine.start();
