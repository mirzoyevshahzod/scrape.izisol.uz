@extends('layouts.app')

@section('title', 'Mintrans Avtoraqam')

@push('styles')
    <style>
        .run-progress {
            height: 6px;
            min-width: 120px;
        }
        .api-status:empty {
            display: none;
        }
        .log-line-found { color: #7ee2a8; }
        .log-line-missing { color: #ff9b9b; }
        .log-line-error { color: #ffd479; }
        .stage-option {
            display: flex;
            gap: 10px;
            padding: 10px 12px 10px 36px;
            border: 1px solid var(--ui-border);
            border-radius: 10px;
            cursor: pointer;
            margin: 0;
        }
        .stage-option:has(input:checked) {
            border-color: rgba(var(--ui-primary-rgb), 0.6);
            background: var(--ui-primary-soft);
        }
        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--ui-surface-2);
            font-size: 0.8125rem;
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Mintrans Avtoraqam</h1>
            <p>Excel'dagi O'zbekiston avtomobil raqamlari bo'yicha Mintrans litsenziya ma'lumotlarini olish.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span id="api-status" class="api-status"></span>
            <button type="button" class="btn btn-light" id="ping-btn">
                <i class="fas fa-plug-circle-check me-1"></i>API'ni tekshirish
            </button>
        </div>
    </div>

    <div id="worker-warning" class="alert alert-warning d-none">
        <i class="fas fa-triangle-exclamation me-1"></i>
        Ish bir daqiqadan beri navbatda turibdi — serverda queue worker ishlamayotgan bo'lishi mumkin.
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card action-card h-100">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-file-arrow-up"></i></span>
                        <div>
                            <h2>Excel yuklash</h2>
                            <p>A ustundan faqat O'zbekiston avtomobil raqamlari olinadi, qolgan yozuvlar tashlab yuboriladi.</p>
                        </div>
                    </div>

                    <form id="start-form" class="d-flex flex-column gap-3">
                        <div>
                            <div class="form-label mb-2">Qaysi bosqichdan boshlash?</div>
                            <div class="d-flex flex-column gap-2">
                                <label class="form-check stage-option">
                                    <input class="form-check-input" type="radio" name="stage" value="full" checked>
                                    <span class="form-check-label">
                                        <b>1-bosqichdan</b> — Mintrans ma'lumoti, nomlarni formatlash va INN
                                        <span class="text-muted small d-block">A ustunida avtoraqamlar bo'lgan Excel.</span>
                                    </span>
                                </label>
                                <label class="form-check stage-option">
                                    <input class="form-check-input" type="radio" name="stage" value="names">
                                    <span class="form-check-label">
                                        <b>2-bosqichdan</b> — korxona nomlarini formatlash va INN
                                        <span class="text-muted small d-block">Mintrans ma'lumoti olingan Excel ("Korxona nomi" ustuni bor). Mintrans'ga so'rov yuborilmaydi.</span>
                                    </span>
                                </label>
                                <label class="form-check stage-option">
                                    <input class="form-check-input" type="radio" name="stage" value="inn">
                                    <span class="form-check-label">
                                        <b>Faqat 3-bosqich</b> — orginfo.uz'dan INN qo'shish
                                        <span class="text-muted small d-block">Nomlari formatlangan Excel. Natija auto_import_template formatida (N: инн, O: Yangi telefon raqami).</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <label class="dropzone" for="file-input" id="dropzone">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <span class="dropzone-file" id="file-name">Excel faylni tanlang yoki shu yerga tashlang</span>
                            <span class="small">.xlsx, .xls, .csv · {{ $maxUploadMb + 0 }} MB gacha</span>
                        </label>
                        <input type="file" id="file-input" name="file" accept=".xlsx,.xls,.csv" class="d-none">

                        <div class="form-check" id="skip-missing-wrap">
                            <input class="form-check-input" type="checkbox" id="skip-missing" name="skip_missing">
                            <label class="form-check-label" for="skip-missing">
                                Avval topilmagan raqamlarni qayta so'ramaslik
                                <span class="text-muted small d-block">Bazadagi "topilmadi" ro'yxatidagi raqamlar o'tkazib yuboriladi — vaqt tejaladi.</span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary py-2" id="start-btn" disabled>
                            <i class="fas fa-play me-1"></i><span id="start-label">Boshlash</span>
                        </button>
                    </form>

                    <div class="small text-muted border-top pt-3">
                        <div><b>1-bosqich:</b> har bir raqam Mintrans'dan so'raladi (0.3 s oraliq bilan); ma'lumoti chiqmaganlar bazaga yoziladi.</div>
                        <div><b>2-bosqich:</b> korxona nomlari tozalanadi va <code>OOO "NOMI"</code> ko'rinishiga keltiriladi.</div>
                        <div><b>3-bosqich:</b> nom bo'yicha orginfo.uz'dan INN topiladi (faqat nomi aynan mos, faol tashkilot); topilmasa bo'sh qoladi.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-circle-question me-2 text-muted"></i>Topilmagan raqamlar <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" id="missing-total">{{ $missingCount }}</span></span>
                    <a href="{{ route('mintrans.missing.export') }}" class="btn btn-sm btn-light"><i class="fas fa-download me-1"></i>Excel</a>
                </div>
                <div class="card-body pb-2">
                    <input type="search" class="form-control form-control-sm mb-2" id="missing-search" placeholder="Raqam bo'yicha qidirish">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <tbody id="missing-body">
                                <tr><td class="text-muted small">Yuklanmoqda…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small text-muted mt-2">
                        <span id="missing-page"></span>
                        <span class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-light" id="missing-prev"><i class="fas fa-chevron-left"></i></button>
                            <button type="button" class="btn btn-light" id="missing-next"><i class="fas fa-chevron-right"></i></button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>Ishlar tarixi</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fayl</th>
                        <th>Holat</th>
                        <th>Jarayon</th>
                        <th class="d-none d-md-table-cell">Kim</th>
                        <th class="d-none d-sm-table-cell">Boshlangan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="runs-body">
                    <tr><td colspan="7" class="text-center text-muted py-4">Yuklanmoqda…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="output-modal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="output-title">Natija</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="output-stats" class="d-flex flex-wrap gap-2 mb-3"></div>
                    <div class="progress run-progress mb-3"><div class="progress-bar" id="output-bar" style="width: 0%"></div></div>
                    <pre class="console-output mb-0" id="output-body"></pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            // serverning haqiqiy yuklash chegarasi (PHP upload_max_filesize / post_max_size hisobga olingan)
            const MAX_UPLOAD_MB = @json($maxUploadMb);

            const urls = {
                start: @json(route('mintrans.start')),
                runs: @json(route('mintrans.runs')),
                run: @json(url('mintrans-auto/runs')),
                ping: @json(route('mintrans.ping')),
                missing: @json(route('mintrans.missing')),
            };

            const STATUS = {
                queued: ['bg-secondary-subtle text-secondary-emphasis', 'Navbatda'],
                running: ['bg-primary-subtle text-primary-emphasis', 'Ishlayapti'],
                success: ['bg-success-subtle text-success-emphasis', 'Tayyor'],
                failed: ['bg-danger-subtle text-danger-emphasis', 'Xato'],
                stale: ['bg-warning-subtle text-warning-emphasis', 'Tiqilib qolgan'],
            };

            const modal = new bootstrap.Modal(document.getElementById('output-modal'));
            let openRunId = null;
            let pollTimer = null;
            let missingPage = 1;
            let wasActive = false;
            let missingLast = 1;

            function el(tag, className, text) {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined && text !== null) node.textContent = text;
                return node;
            }

            function badge(status) {
                const [cls, label] = STATUS[status] || ['bg-secondary-subtle text-secondary-emphasis', status];
                const node = el('span', 'badge ' + cls, label);
                if (status === 'running') node.prepend(el('span', 'spinner-border spinner-border-sm me-1'));
                return node;
            }

            function percent(progress) {
                if (!progress || !progress.total) return 0;
                return Math.round(progress.done / progress.total * 100);
            }

            function statPills(progress) {
                const wrap = el('div', 'd-flex flex-wrap gap-2');
                if (!progress) return wrap;
                [
                    ['Raqamlar', progress.done + ' / ' + progress.total],
                    ['✅ Topildi', progress.found],
                    ['❌ Topilmadi', progress.not_found],
                    ['⚠️ Xatolik', progress.errors],
                    ['Tashlab yuborilgan yozuv', progress.dropped],
                ].concat(progress.skipped ? [['O\'tkazib yuborildi', progress.skipped]] : []).forEach(function ([label, value]) {
                    const pill = el('span', 'stat-pill');
                    pill.append(el('span', 'text-muted', label + ':'), el('b', null, value));
                    wrap.append(pill);
                });
                return wrap;
            }

            function innPills(inn) {
                const wrap = el('div', 'd-flex flex-wrap gap-2');
                if (!inn) return wrap;
                [
                    ['INN: nomlar', inn.done + ' / ' + inn.total],
                    ['✅ INN topildi', inn.found],
                    ['❌ topilmadi', inn.not_found],
                ].concat(inn.errors ? [['⚠️ Xatolik', inn.errors]] : []).forEach(function ([label, value]) {
                    const pill = el('span', 'stat-pill');
                    pill.append(el('span', 'text-muted', label + ':'), el('b', null, value));
                    wrap.append(pill);
                });
                return wrap;
            }

            // ---------- API tekshiruvi ----------
            document.getElementById('ping-btn').addEventListener('click', async function () {
                const button = this;
                const status = document.getElementById('api-status');
                button.disabled = true;
                status.replaceChildren(el('span', 'text-muted small', 'Tekshirilmoqda…'));

                try {
                    const response = await fetch(urls.ping, { headers: { Accept: 'application/json' } });
                    const data = await response.json();
                    status.replaceChildren(el('span',
                        'badge ' + (data.ok ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis'),
                        (data.ok ? '✅ API ishlayapti' : '❌ API ishlamayapti') + ' · ' + data.ms + ' ms'));
                    if (!data.ok) uiToast(data.message, 'danger');
                } catch (e) {
                    status.replaceChildren(el('span', 'badge bg-danger-subtle text-danger-emphasis', '❌ Tekshirib bo\'lmadi'));
                } finally {
                    button.disabled = false;
                }
            });

            // ---------- Fayl tanlash va boshlash ----------
            const fileInput = document.getElementById('file-input');
            const dropzone = document.getElementById('dropzone');
            const startBtn = document.getElementById('start-btn');

            function showFile() {
                const file = fileInput.files[0];
                document.getElementById('file-name').textContent = file ? file.name : 'Excel faylni tanlang yoki shu yerga tashlang';
                dropzone.classList.toggle('file-uploaded', !!file);
                startBtn.disabled = !file;
            }

            fileInput.addEventListener('change', showFile);

            function selectedStage() {
                return document.querySelector('input[name="stage"]:checked').value;
            }

            function syncStage() {
                const stage = selectedStage();
                document.getElementById('skip-missing-wrap').classList.toggle('d-none', stage !== 'full');
                document.getElementById('start-label').textContent = { full: 'Boshlash', names: 'Nomlarni formatlash va INN', inn: 'INN qo\'shish' }[stage];
            }

            document.querySelectorAll('input[name="stage"]').forEach(function (radio) {
                radio.addEventListener('change', syncStage);
            });
            syncStage();
            ['dragenter', 'dragover'].forEach(function (type) {
                dropzone.addEventListener(type, function (e) { e.preventDefault(); dropzone.classList.add('is-dragover'); });
            });
            ['dragleave', 'drop'].forEach(function (type) {
                dropzone.addEventListener(type, function () { dropzone.classList.remove('is-dragover'); });
            });
            dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    showFile();
                }
            });

            document.getElementById('start-form').addEventListener('submit', async function (e) {
                e.preventDefault();
                if (!fileInput.files[0]) return;

                const sizeMb = fileInput.files[0].size / 1048576;
                if (sizeMb > MAX_UPLOAD_MB) {
                    uiToast('Fayl ' + sizeMb.toFixed(1) + ' MB — serverga ' + MAX_UPLOAD_MB + ' MB gacha yuklash mumkin. '
                        + 'Administrator PHP\'da upload_max_filesize va post_max_size\'ni oshirishi kerak.', 'danger');
                    return;
                }

                const form = new FormData();
                form.append('file', fileInput.files[0]);
                form.append('stage', selectedStage());
                form.append('skip_missing', document.getElementById('skip-missing').checked ? '1' : '0');

                startBtn.disabled = true;

                try {
                    const response = await fetch(urls.start, { method: 'POST', headers: { Accept: 'application/json' }, body: form });
                    const data = await response.json().catch(function () { return {}; });

                    if (!response.ok) {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                        uiToast(firstError || data.message || 'Xatolik: HTTP ' + response.status, 'danger');
                        startBtn.disabled = false;
                        return;
                    }

                    uiToast(data.message, 'success');
                    fileInput.value = '';
                    showFile();
                    refresh();
                    openOutput(data.run.id, data.run.file);
                } catch (error) {
                    uiToast('Yuklashda xatolik: ' + error.message, 'danger');
                    startBtn.disabled = false;
                }
            });

            // ---------- Ishlar tarixi ----------
            function renderRuns(runs) {
                const body = document.getElementById('runs-body');
                body.replaceChildren();

                if (!runs.length) {
                    const row = el('tr');
                    const cell = el('td', 'text-center text-muted py-4', 'Hali hech narsa yuklanmagan.');
                    cell.colSpan = 7;
                    row.append(cell);
                    body.append(row);
                    return;
                }

                runs.forEach(function (run) {
                    const row = el('tr');
                    const statusCell = el('td');
                    statusCell.append(badge(run.status));

                    const progressCell = el('td');
                    if (run.progress) {
                        const bar = el('div', 'progress run-progress mb-1');
                        const fill = el('div', 'progress-bar' + (run.active ? ' progress-bar-striped progress-bar-animated' : ''));
                        fill.style.width = percent(run.progress) + '%';
                        bar.append(fill);
                        progressCell.append(bar, el('div', 'small text-muted',
                            run.progress.done + '/' + run.progress.total + ' · ✅ ' + run.progress.found + ' · ❌ ' + run.progress.not_found +
                            (run.progress.errors ? ' · ⚠️ ' + run.progress.errors : '')));
                    } else if (!run.inn) {
                        progressCell.append(el('span', 'text-muted small', '—'));
                    }

                    if (run.inn) {
                        progressCell.append(el('div', 'small text-muted', 'INN: ' + run.inn.done + '/' + run.inn.total + ' · ✅ ' + run.inn.found));
                    }

                    const actions = el('td', 'text-end text-nowrap');
                    const resultBtn = el('button', 'btn btn-sm btn-light', 'Natija');
                    resultBtn.type = 'button';
                    resultBtn.addEventListener('click', function () { openOutput(run.id, run.file); });
                    actions.append(resultBtn);

                    if (run.resumable) {
                        const resumeBtn = el('button', 'btn btn-sm btn-warning ms-1');
                        resumeBtn.type = 'button';
                        resumeBtn.title = 'To\'xtagan joyidan davom ettirish';
                        resumeBtn.append(el('i', 'fas fa-play me-1'), document.createTextNode('Davom ettirish'));
                        resumeBtn.addEventListener('click', function () { resumeRun(run); });
                        actions.append(resumeBtn);
                    }

                    if (run.plates_ready) {
                        const platesLink = el('a', 'btn btn-sm btn-light ms-1');
                        platesLink.href = urls.run + '/' + run.id + '/plates';
                        platesLink.title = 'A ustundan ajratilgan O\'zbekiston avtoraqamlari';
                        platesLink.append(el('i', 'fas fa-list-ol me-1'), document.createTextNode('Raqamlar'));
                        actions.append(platesLink);
                    }

                    if (run.downloadable) {
                        const link = el('a', 'btn btn-sm btn-primary ms-1');
                        link.href = urls.run + '/' + run.id + '/download';
                        link.append(el('i', 'fas fa-download me-1'), document.createTextNode('Excel'));
                        actions.append(link);
                    }

                    // faqat nom kesiladi — belgilar doim ko'rinsin
                    const fileCell = el('td', 'text-nowrap');
                    const fileName = el('span', 'd-inline-block text-truncate align-middle', run.file);
                    fileName.style.maxWidth = '200px';
                    fileName.title = run.file;
                    fileCell.append(fileName);
                    if (run.stage === 'names') fileCell.append(el('span', 'badge bg-primary-subtle text-primary-emphasis ms-1', '2-bosqichdan'));
                    if (run.stage === 'inn') fileCell.append(el('span', 'badge bg-primary-subtle text-primary-emphasis ms-1', '3-bosqich'));
                    if (run.skip_missing) fileCell.append(el('span', 'badge bg-secondary-subtle text-secondary-emphasis ms-1', 'eskilarsiz'));

                    row.append(
                        el('td', 'text-muted', run.id),
                        fileCell,
                        statusCell,
                        progressCell,
                        el('td', 'd-none d-md-table-cell', run.user || '—'),
                        el('td', 'text-muted small d-none d-sm-table-cell', run.started_at || run.created_at),
                        actions
                    );
                    body.append(row);
                });
            }

            function resumeRun(run) {
                const done = run.progress ? run.progress.done + ' / ' + run.progress.total : null;

                uiConfirm({
                    title: '#' + run.id + ' davom ettirilsinmi?',
                    message: (done ? '1-bosqich ' + done + ' joyidan davom etadi' : '1-bosqich to\'xtagan joyidan davom etadi')
                        + ': natija faylida bor va "topilmadi" deb yozilgan raqamlar qayta so\'ralmaydi, yangi qatorlar shu faylga qo\'shiladi. '
                        + 'Keyin 2- va 3-bosqich butun fayl bo\'yicha boshidan ishlaydi.',
                    icon: 'fa-play',
                    confirmText: 'Davom ettirish',
                    onConfirm: async function () {
                        const response = await fetch(urls.run + '/' + run.id + '/resume', {
                            method: 'POST',
                            headers: { Accept: 'application/json' },
                        });
                        const data = await response.json().catch(function () { return {}; });

                        if (!response.ok) return data.message || 'Xatolik: HTTP ' + response.status;

                        uiToast(data.message, 'success');
                        refresh();
                        openOutput(run.id, run.file);
                        return true;
                    },
                });
            }

            async function refresh() {
                try {
                    const response = await fetch(urls.runs, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();

                    renderRuns(data.runs);

                    const anyActive = data.runs.some(function (run) { return run.active; });
                    // bitta bosqich bir vaqtda faqat bir marta — buni server tekshiradi (409)
                    startBtn.disabled = !fileInput.files[0];
                    document.getElementById('worker-warning').classList.toggle('d-none',
                        !data.runs.some(function (run) { return run.status === 'queued' && run.queued_seconds > 60; }));

                    // ish tugaganda topilmaganlar ro'yxati yangilansin
                    if (wasActive && !anyActive) loadMissing();
                    wasActive = anyActive;
                    schedule(anyActive ? 3000 : 15000);
                } catch (e) {
                    schedule(15000);
                }
            }

            function schedule(ms) {
                clearTimeout(pollTimer);
                pollTimer = setTimeout(refresh, ms);
            }

            // ---------- Natija oynasi ----------
            function renderLog(text) {
                const pre = document.getElementById('output-body');
                const atBottom = pre.scrollTop + pre.clientHeight >= pre.scrollHeight - 20;
                pre.replaceChildren();

                (text || '(hali chiqish yo\'q)').split('\n').forEach(function (line) {
                    let cls = null;
                    if (line.startsWith('✅')) cls = 'log-line-found';
                    else if (line.startsWith('❌')) cls = 'log-line-missing';
                    else if (line.startsWith('⚠️')) cls = 'log-line-error';
                    pre.append(el('span', cls, line + '\n'));
                });

                if (atBottom) pre.scrollTop = pre.scrollHeight;
            }

            async function loadOutput() {
                if (openRunId === null) return;

                const response = await fetch(urls.run + '/' + openRunId, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const run = await response.json();

                document.getElementById('output-title').replaceChildren(
                    document.createTextNode('#' + run.id + ' ' + run.file + ' '), badge(run.status));
                document.getElementById('output-stats').replaceChildren(statPills(run.progress), innPills(run.inn));
                document.getElementById('output-bar').style.width = percent(run.progress) + '%';
                document.getElementById('output-bar').parentElement.classList.toggle('d-none', !run.progress);
                renderLog(run.output);

                if (run.active) setTimeout(loadOutput, 2000);
            }

            function openOutput(id, name) {
                openRunId = id;
                document.getElementById('output-title').textContent = '#' + id + ' ' + name;
                document.getElementById('output-body').textContent = 'Yuklanmoqda…';
                document.getElementById('output-stats').replaceChildren();
                modal.show();
                loadOutput();
            }

            document.getElementById('output-modal').addEventListener('hidden.bs.modal', function () { openRunId = null; });

            // ---------- Topilmagan raqamlar ----------
            async function loadMissing() {
                const q = document.getElementById('missing-search').value;
                const response = await fetch(urls.missing + '?page=' + missingPage + '&q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                const body = document.getElementById('missing-body');

                missingLast = data.last_page;
                document.getElementById('missing-total').textContent = data.total;
                document.getElementById('missing-page').textContent = data.page + ' / ' + data.last_page;
                body.replaceChildren();

                if (!data.items.length) {
                    const row = el('tr');
                    row.append(el('td', 'text-muted small', q ? 'Topilmadi.' : 'Hozircha bo\'sh.'));
                    body.append(row);
                    return;
                }

                data.items.forEach(function (item) {
                    const row = el('tr');
                    const plate = el('td', 'fw-medium', item.plate);
                    const state = el('td');
                    state.append(el('span', 'badge ' + (item.status === 'error' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'),
                        item.status === 'error' ? 'xatolik' : 'topilmadi'));
                    if (item.message) state.title = item.message;
                    row.append(plate, state, el('td', 'text-muted small text-end', item.checked_at + (item.checks > 1 ? ' · ' + item.checks + ' marta' : '')));
                    body.append(row);
                });
            }

            let searchTimer = null;
            document.getElementById('missing-search').addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { missingPage = 1; loadMissing(); }, 300);
            });
            document.getElementById('missing-prev').addEventListener('click', function () { if (missingPage > 1) { missingPage--; loadMissing(); } });
            document.getElementById('missing-next').addEventListener('click', function () { if (missingPage < missingLast) { missingPage++; loadMissing(); } });

            refresh();
            loadMissing();
        })();
    </script>
@endpush
