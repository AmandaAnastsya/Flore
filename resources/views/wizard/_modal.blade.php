{{--
    Smart Bouquet Recommendation Wizard (multi-step modal).
    Butuh: $wizardOptions dari RecommendationEngine::options()
    Buka dengan tombol: data-bs-toggle="modal" data-bs-target="#wizardModal"
--}}
@php
    $wizardOptions ??= app(\App\Services\RecommendationEngine::class)->options();
    $openWizard ??= request()->boolean('wizard');
    $label = fn ($v) => ucwords(str_replace('-', ' ', $v));

    // Teks tampilan untuk tiap nilai. Nilai baru dari admin tetap tampil (pakai $label).
    $display = [
        'occasion' => [
            'anniversary' => ['Anniversary', '💞', 'Rayakan hari jadi'],
            'birthday' => ['Ulang Tahun', '🎂', 'Kejutan di hari spesial'],
            'graduation' => ['Wisuda', '🎓', 'Ucapan selamat kelulusan'],
            'apology' => ['Permintaan Maaf', '🤍', 'Sampaikan dengan tulus'],
            'grand-opening' => ['Grand Opening', '🎉', 'Usaha atau toko baru'],
            'wedding' => ['Pernikahan', '💍', 'Hari bahagia'],
        ],
        'color' => [
            'pink' => ['Pink', '#f4b6c2'],
            'white' => ['Putih', '#ffffff'],
            'lavender' => ['Lavender', '#c9b6e4'],
            'peach' => ['Peach', '#f8c8a8'],
            'red' => ['Merah', '#c0392b'],
        ],
        'style' => [
            'soft-romantic' => ['Soft Romantic', 'Lembut, pastel, penuh perasaan'],
            'modern' => ['Modern', 'Bersih, minimalis, kekinian'],
            'classic' => ['Classic', 'Abadi dan elegan'],
            'luxury' => ['Luxury', 'Mewah dan berkesan'],
        ],
        'size' => [
            'mini' => ['Mini', '± 3–5 tangkai'],
            'small' => ['Small', '± 6–10 tangkai'],
            'medium' => ['Medium', '± 11–20 tangkai'],
            'large' => ['Large', '20+ tangkai'],
        ],
    ];

    // [judul, petunjuk, nama singkat untuk progress]
    $steps = [
        'occasion' => ['Untuk momen spesial apa?', 'Pilih acara agar rangkaian terasa pas untuk penerimanya.', 'Momen'],
        'color' => ['Warna apa yang paling mewakili perasaanmu?', 'Kami cocokkan dengan palet bunga yang tersedia.', 'Warna'],
        'style' => ['Gaya rangkaian seperti apa?', 'Tampilan bouquet yang paling kamu suka.', 'Gaya'],
        'size' => ['Seberapa besar bouquet-nya?', 'Ukuran menentukan jumlah tangkai dan kesan.', 'Ukuran'],
        'budget' => ['Berapa anggaran danamu?', 'Kami carikan yang paling pas dengan budget.', 'Budget'],
    ];
@endphp

<div class="modal fade flore-wizard" id="wizardModal" tabindex="-1" aria-labelledby="wizardTitle" aria-hidden="true"
     data-endpoint="{{ route('wizard.recommend') }}" data-auto-open="{{ !empty($openWizard) ? 1 : 0 }}">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 d-block">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-baseline gap-3">
                        <span class="brand serif">Floré</span>
                        <h2 class="modal-title eyebrow mb-0" id="wizardTitle">Smart Bouquet Wizard</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="mt-3" data-progress-wrap>
                    <div class="d-flex justify-content-between progress-label mb-2">
                        <span data-step-label>STEP 1 OF {{ count($steps) }}: MOMEN</span>
                        <span data-step-percent>20% Completed</span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="Progres wizard" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" data-progress style="width: 20%"></div>
                    </div>
                </div>
            </div>

            <div class="modal-body">
                <form data-wizard-form onsubmit="return false">
                    @foreach($steps as $name => [$title, $hint, $short])
                        @php $values = $name === 'budget' ? array_keys($wizardOptions['budget']) : ($wizardOptions[$name] ?? []); @endphp

                        <fieldset class="wizard-step" data-step="{{ $name }}" data-short="{{ $short }}" @if(!$loop->first) hidden @endif>
                            <legend class="step-title serif text-center mb-1">{{ $title }}</legend>
                            <p class="text-muted text-center small mb-4">{{ $hint }}</p>

                            @if(empty($values))
                                <p class="text-muted fst-italic">Belum ada pilihan untuk langkah ini. Silakan lewati.</p>
                            @endif

                            <div class="option-grid option-grid-{{ $name }}">
                                @foreach($values as $value)
                                    @php
                                        $d = $display[$name][$value] ?? null;
                                        $text = $name === 'budget' ? $wizardOptions['budget'][$value] : ($d[0] ?? $label($value));
                                    @endphp
                                    <label class="option">
                                        <input type="radio" class="visually-hidden" name="{{ $name }}" value="{{ $value }}" data-text="{{ $text }}">
                                        <span class="option-body">
                                            @if($name === 'occasion')
                                                <span class="option-icon" aria-hidden="true">{{ $d[1] ?? '🌸' }}</span>
                                            @elseif($name === 'color')
                                                <span class="swatch" style="background: {{ $d[1] ?? '#e8c6cf' }}" aria-hidden="true"></span>
                                            @endif
                                            <span class="option-title">{{ $text }}</span>
                                            @if(in_array($name, ['occasion', 'style', 'size']) && isset($d[$name === 'occasion' ? 2 : 1]))
                                                <span class="option-hint">{{ $d[$name === 'occasion' ? 2 : 1] }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    {{-- Penjelasan rule-based yang berubah sesuai pilihan (mockup hal. 46) --}}
                    <div class="engine-hint mt-4" data-hint aria-live="polite">
                        <div class="eyebrow mb-1">Floré Recommendation Engine</div>
                        <div data-hint-text>Pilih preferensimu, kami akan menilai setiap bouquet berdasarkan kecocokan momen, warna, gaya, ukuran, dan budget.</div>
                    </div>

                    {{-- Hasil --}}
                    <section class="wizard-step" data-step="result" hidden aria-live="polite">
                        <div data-loading class="text-center py-5" hidden>
                            <div class="spinner-border text-rose" role="status"></div>
                            <p class="text-muted mt-3 mb-0">Merangkai rekomendasi untukmu…</p>
                        </div>

                        <div data-result hidden>
                            <h3 class="fs-4 serif mb-1" data-result-message></h3>
                            <div class="d-flex flex-wrap gap-2 mb-3" data-summary></div>
                            <div class="row g-3" data-result-list></div>
                        </div>

                        <div data-error class="alert alert-danger" hidden>
                            Gagal memuat rekomendasi. Periksa koneksi lalu coba lagi.
                        </div>
                    </section>
                </form>
            </div>

            <div class="modal-footer border-0 justify-content-between">
                <button type="button" class="btn btn-outline-plum px-4" data-action="back">Kembali</button>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-link text-muted" data-action="skip">Lewati</button>
                    <button type="button" class="btn btn-plum px-4" data-action="next" disabled>Lanjut →</button>
                    <button type="button" class="btn btn-outline-plum" data-action="restart" hidden>Ulangi Wizard</button>
                    <a href="{{ route('catalog.index') }}" class="btn btn-rose" data-action="catalog" hidden>Lihat Katalog</a>
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<style>
    .flore-wizard { --rose: #8e5a66; --rose-dark: #6f4350; --plum: #4a2a30; --blush: #f5e9ec; --line: #f0dde2; }
    .flore-wizard .serif { font-family: Georgia, 'Times New Roman', serif; color: var(--plum); }
    .flore-wizard .brand { font-size: 1.6rem; font-weight: 700; }
    .flore-wizard .eyebrow { font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--rose); }
    .flore-wizard .progress-label { font-size: .72rem; letter-spacing: .06em; color: #6c757d; }
    .flore-wizard .step-title { font-size: 1.6rem; font-weight: 700; }
    .flore-wizard .modal-content { border: 0; border-radius: .75rem; background: #fbf7f8; }
    .flore-wizard .btn-plum { background: var(--plum); color: #fff; border: 0; }
    .flore-wizard .btn-plum:hover, .flore-wizard .btn-plum:focus { background: #351c21; color: #fff; }
    .flore-wizard .btn-plum:disabled { background: #a89297; color: #fff; }
    .flore-wizard .btn-outline-plum { border: 1px solid var(--plum); color: var(--plum); background: #fff; }
    .flore-wizard .btn-outline-plum:hover { background: var(--plum); color: #fff; }
    .flore-wizard .engine-hint { border: 1px solid #e8c6cf; background: #fff; border-radius: .4rem; padding: .85rem 1rem; font-size: .85rem; }
    .flore-wizard .progress { height: .4rem; background: var(--blush); }
    .flore-wizard .progress-bar { background: var(--rose); transition: width .3s ease; }
    .flore-wizard .btn-rose { background: var(--rose); color: #fff; border: 0; }
    .flore-wizard .btn-rose:hover, .flore-wizard .btn-rose:focus { background: var(--rose-dark); color: #fff; }
    .flore-wizard .btn-rose:disabled { background: #c9a9b1; color: #fff; }
    .flore-wizard .text-rose { color: var(--rose); }
    .flore-wizard legend { float: none; }

    .flore-wizard .option-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
    .flore-wizard .option-grid-budget { grid-template-columns: 1fr; }
    .flore-wizard .option { cursor: pointer; margin: 0; }
    .flore-wizard .option-body {
        display: flex; flex-direction: column; align-items: center; text-align: center; gap: .35rem; height: 100%;
        padding: 1rem .75rem; border: 1.5px solid var(--line); border-radius: .6rem; background: #fff;
        transition: border-color .15s, background .15s, transform .15s;
    }
    .flore-wizard .option-grid-budget .option-body { flex-direction: row; justify-content: flex-start; padding: .85rem 1rem; }
    .flore-wizard .option:hover .option-body { border-color: #d9aab6; transform: translateY(-1px); }
    .flore-wizard .option input:checked + .option-body { border-color: var(--plum); background: var(--plum); color: #fff; }
    .flore-wizard .option input:checked + .option-body .option-hint { color: #e8c6cf; }
    .flore-wizard .option input:checked + .option-body .swatch { border-color: #fff; box-shadow: 0 0 0 2px #fff; }
    .flore-wizard .option input:focus-visible + .option-body { outline: 3px solid #d9aab6; outline-offset: 2px; }
    .flore-wizard .option-icon { font-size: 1.75rem; line-height: 1; }
    .flore-wizard .option-title { font-weight: 600; }
    .flore-wizard .option-hint { font-size: .8rem; color: #6c757d; }
    .flore-wizard .swatch { width: 2.25rem; height: 2.25rem; border-radius: 50%; border: 1px solid #ddd; }

    .flore-wizard .chip { font-size: .78rem; background: var(--blush); color: var(--rose-dark); border: 0; border-radius: 1rem; padding: .2rem .7rem; }
    .flore-wizard button.chip:hover { background: #ebd3da; }
    .flore-wizard .rec { border: 1px solid var(--line); border-radius: .6rem; overflow: hidden; height: 100%; display: flex; flex-direction: column; background: #fff; }
    .flore-wizard .rec img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: var(--blush); }
    .flore-wizard .rec .match { position: absolute; top: .5rem; left: .5rem; background: var(--rose); color: #fff; font-size: .72rem; padding: .15rem .5rem; border-radius: .25rem; }
    .flore-wizard .rec .price { color: var(--rose); font-weight: 700; }
    .flore-wizard .reason { font-size: .72rem; color: #6c757d; }
    .flore-wizard .reason::before { content: '✓ '; color: var(--rose); }
    .flore-wizard .reason.warn::before { content: '• '; color: #c08a00; }

    @media (prefers-reduced-motion: reduce) {
        .flore-wizard .progress-bar, .flore-wizard .option-body { transition: none; }
    }
</style>

<script type="module">
(() => {
    const modal = document.getElementById('wizardModal');
    if (!modal) return;

    const form = modal.querySelector('[data-wizard-form]');
    const steps = [...modal.querySelectorAll('fieldset.wizard-step')];
    const resultStep = modal.querySelector('[data-step="result"]');
    const btn = (a) => modal.querySelector(`[data-action="${a}"]`);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let current = 0;
    let request = 0;   // abaikan respons lama kalau user mengulang cepat

    const answers = () => Object.fromEntries(
        steps.map((s) => [s.dataset.step, s.querySelector('input:checked')?.value ?? ''])
    );

    function show(index) {
        current = index;
        const onResult = index >= steps.length;

        steps.forEach((s, i) => (s.hidden = i !== index));
        resultStep.hidden = !onResult;

        modal.querySelector('[data-progress-wrap]').hidden = onResult;
        modal.querySelector('[data-hint]').hidden = onResult;
        if (!onResult) {
            const pct = Math.round(((index + 1) / steps.length) * 100);
            modal.querySelector('[data-step-label]').textContent =
                `STEP ${index + 1} OF ${steps.length}: ${steps[index].dataset.short.toUpperCase()}`;
            modal.querySelector('[data-step-percent]').textContent = `${pct}% Completed`;
            modal.querySelector('[data-progress]').style.width = `${pct}%`;
            modal.querySelector('[data-progress]').parentElement.setAttribute('aria-valuenow', pct);
            updateHint();
        }

        btn('back').style.visibility = index === 0 ? 'hidden' : 'visible';
        btn('skip').hidden = onResult;
        btn('next').hidden = onResult;
        btn('restart').hidden = !onResult;
        btn('catalog').hidden = !onResult;

        if (!onResult) {
            const step = steps[index];
            btn('next').disabled = !step.querySelector('input:checked');
            btn('next').textContent = index === steps.length - 1 ? 'Lihat Rekomendasi →' : `Lanjut (${index + 2}/${steps.length}) →`;
            (step.querySelector('input:checked') ?? step.querySelector('input'))?.focus({ preventScroll: true });
        }

        modal.querySelector('.modal-body').scrollTop = 0;
    }

    // Kotak penjelasan: rangkum pilihan sejauh ini
    function updateHint() {
        const picked = steps.map((s) => s.querySelector('input:checked')?.dataset.text).filter(Boolean);
        modal.querySelector('[data-hint-text]').textContent = picked.length
            ? `Berdasarkan pilihanmu (${picked.join(' · ')}), kami akan menilai setiap bouquet dan menampilkan yang paling cocok lebih dulu.`
            : 'Pilih preferensimu, kami akan menilai setiap bouquet berdasarkan kecocokan momen, warna, gaya, ukuran, dan budget.';
    }

    function next() {
        current + 1 >= steps.length ? showResults() : show(current + 1);
    }

    async function showResults() {
        show(steps.length);
        const loading = resultStep.querySelector('[data-loading]');
        const result = resultStep.querySelector('[data-result]');
        const error = resultStep.querySelector('[data-error]');
        loading.hidden = false; result.hidden = true; error.hidden = true;

        const id = ++request;
        const params = new URLSearchParams(Object.entries(answers()).filter(([, v]) => v));

        try {
            const res = await fetch(`${modal.dataset.endpoint}?${params}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            if (id !== request) return;
            render(data);
            loading.hidden = true; result.hidden = false;
        } catch (e) {
            if (id !== request) return;
            loading.hidden = true; error.hidden = false;
        }
    }

    function render(data) {
        resultStep.querySelector('[data-result-message]').textContent = data.message;

        // Ringkasan pilihan; klik untuk mengubah langkah itu
        resultStep.querySelector('[data-summary]').innerHTML = steps.map((s, i) => {
            const input = s.querySelector('input:checked');
            return input ? `<button type="button" class="chip" data-goto="${i}" title="Ubah pilihan">${esc(input.dataset.text)} ✎</button>` : '';
        }).join('') || '<span class="text-muted small">Tanpa preferensi khusus</span>';

        const list = resultStep.querySelector('[data-result-list]');
        if (!data.items.length) {
            list.innerHTML = '<div class="col-12 text-muted">Coba ubah pilihanmu atau lihat seluruh katalog.</div>';
            return;
        }

        list.innerHTML = data.items.map((b) => `
            <div class="col-12 col-sm-6 col-lg-4">
                <article class="rec">
                    <a href="${esc(b.url)}" class="position-relative d-block">
                        <img src="${esc(b.image)}" alt="${esc(b.name)}" loading="lazy">
                        ${b.percent ? `<span class="match">${b.is_perfect ? 'Paling cocok' : `${b.percent}% cocok`}</span>` : ''}
                    </a>
                    <div class="p-3 d-flex flex-column flex-grow-1">
                        ${b.category ? `<div class="small text-muted">${esc(b.category)}</div>` : ''}
                        <a href="${esc(b.url)}" class="fw-semibold text-dark text-decoration-none">${esc(b.name)}</a>
                        <div class="price my-1">${esc(b.price)}</div>
                        <div class="d-flex flex-column mb-2">
                            ${b.reasons.map((r) => `<span class="reason ${r.startsWith('Sedikit') ? 'warn' : ''}">${esc(r)}</span>`).join('')}
                        </div>
                        ${b.low_stock ? '<div class="small text-warning mb-2">Stok menipis</div>' : ''}
                        <form method="POST" action="${esc(b.cart_url)}" class="mt-auto">
                            <input type="hidden" name="_token" value="${esc(csrf)}">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn-rose btn-sm w-100">Tambah ke Keranjang</button>
                        </form>
                    </div>
                </article>
            </div>`).join('');
    }

    // Pilih opsi → aktifkan Lanjut; klik mouse/tap langsung lanjut otomatis
    form.addEventListener('change', (e) => {
        if (e.target.matches('input[type="radio"]')) { btn('next').disabled = false; updateHint(); }
    });
    form.addEventListener('pointerup', (e) => {
        if (e.target.closest('.option')) setTimeout(next, 220);
    });
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !btn('next').disabled && !btn('next').hidden) { e.preventDefault(); next(); }
    });
    resultStep.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-goto]');
        if (chip) show(Number(chip.dataset.goto));
    });

    btn('next').addEventListener('click', next);
    btn('back').addEventListener('click', () => current > 0 && show(current - 1));
    btn('skip').addEventListener('click', () => {
        steps[current].querySelectorAll('input').forEach((i) => (i.checked = false));
        next();
    });
    btn('restart').addEventListener('click', () => { form.reset(); show(0); });

    modal.addEventListener('shown.bs.modal', () => show(current));

    // /wizard atau /home?wizard=1 → buka modal otomatis
    if (modal.dataset.autoOpen === '1') {
        document.querySelector('[data-bs-target="#wizardModal"]')?.click();
    }
})();
</script>
@endpush
@endonce
