@extends('layouts.app')

@section('title', 'Boshqaruv')


@section('content')
    <div class="page-header">
        <div>
            <h1>Boshqaruv</h1>
            <p>
                Amallar fonda (queue orqali) bajariladi — sahifani yopib qo'ysangiz ham davom etadi.
                Bir xil amal bir vaqtda faqat bir marta ishlaydi.
            </p>
        </div>
    </div>

    <div id="worker-warning" class="alert alert-warning d-none">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Ish bir daqiqadan beri navbatda turibdi — serverda queue worker ishlamayotgan bo'lishi mumkin.
    </div>

    <div class="row g-4 mb-4">
        @foreach ($operations as $key => $operation)
            <div class="col-xxl col-xl-4 col-md-6">
                <div class="card h-100" data-operation="{{ $key }}" data-needs-date="{{ empty($operation['date']) ? '0' : '1' }}"
                     data-description="{{ $operation['description'] }}" data-icon="{{ $operation['icon'] }}"
                     data-fields="{{ json_encode($operation['fields'] ?: new stdClass, JSON_UNESCAPED_UNICODE) }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="icon-tile"><i class="fas {{ $operation['icon'] }}"></i></span>
                            <h5 class="mb-0 fs-6 fw-semibold">{{ $operation['label'] }}</h5>
                        </div>
                        <p class="text-muted small flex-grow-1">{{ $operation['description'] }}</p>
                        @if (! empty($operation['date']) || $operation['fields'])
                            <div class="small text-muted mb-2">
                                <i class="fas fa-calendar-day me-1"></i>Ishga tushirishda {{ $operation['fields'] ? 'kun va fayl' : 'kun' }} so'raladi
                            </div>
                        @endif
                        <div class="small mb-3 op-last text-muted">Hali ishga tushirilmagan.</div>
                        <button type="button" class="btn btn-primary w-100 op-run" data-label="{{ $operation['label'] }}">
                            <i class="fas fa-play me-1"></i>Ishga tushirish
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>So'nggi ishlar</div>
        <div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Amal</th>
                            <th>Holat</th>
                            <th class="d-none d-md-table-cell">Kim</th>
                            <th class="d-none d-sm-table-cell">Boshlangan</th>
                            <th>Davomiyligi</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="runs-body">
                        <tr><td colspan="7" class="text-center text-muted py-4">Yuklanmoqda…</td></tr>
                    </tbody>
                </table>
            </div>
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
                    <pre class="console-output mb-0" id="output-body"></pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const urls = {
                runs: @json(route('operations.runs')),
                show: @json(url('operations/runs')),
                run: @json(url('operations')),
            };

            // "Bugun" serverning vaqt zonasi bo'yicha (brauzerniki boshqa bo'lishi mumkin)
            const TODAY = @json(today()->format('Y-m-d'));

            const STATUS = {
                queued: ['bg-secondary-subtle text-secondary-emphasis', 'Navbatda'],
                running: ['bg-primary-subtle text-primary-emphasis', 'Ishlayapti'],
                success: ['bg-success-subtle text-success-emphasis', 'Muvaffaqiyatli'],
                failed: ['bg-danger-subtle text-danger-emphasis', 'Xato'],
                stale: ['bg-warning-subtle text-warning-emphasis', 'Tiqilib qolgan'],
            };

            const modal = new bootstrap.Modal(document.getElementById('output-modal'));
            let openRunId = null;
            let pollTimer = null;

            function el(tag, className, text) {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined && text !== null) node.textContent = text;
                return node;
            }

            function badge(status) {
                const [cls, label] = STATUS[status] || ['bg-secondary-subtle text-secondary-emphasis', status];
                const node = el('span', 'badge ' + cls, label);
                if (status === 'running') {
                    node.prepend(el('span', 'spinner-border spinner-border-sm me-1'));
                }
                return node;
            }

            // "2026-10-20" -> "20.10.2026"
            function formatDay(day) {
                if (!day) return '';
                const [y, m, d] = day.split('-');
                return d + '.' + m + '.' + y;
            }

            function shiftDay(day, delta) {
                const date = new Date(day + 'T00:00:00');
                date.setDate(date.getDate() + delta);
                const pad = function (n) { return String(n).padStart(2, '0'); };
                return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
            }

            /** Oynadagi kun tanlash maydoni: <input type=date> + "Bugun/Kecha/2 kun oldin" */
            function datePicker() {
                const wrap = el('div');
                const label = el('label', 'form-label', 'Qaysi kun uchun?');
                label.htmlFor = 'op-date';
                const input = el('input', 'form-control');
                input.type = 'date';
                input.id = 'op-date';
                input.max = TODAY;
                input.value = TODAY;
                input.required = true;

                const chips = el('div', 'day-chips');
                const options = [['Bugun', 0], ['Kecha', -1], ['2 kun oldin', -2]];
                const buttons = options.map(function ([text, delta]) {
                    const button = el('button', 'btn btn-light', text);
                    button.type = 'button';
                    button.dataset.day = shiftDay(TODAY, delta);
                    button.addEventListener('click', function () {
                        input.value = button.dataset.day;
                        sync();
                    });
                    return button;
                });
                chips.append.apply(chips, buttons);

                const hint = el('div', 'form-text', 'Nomida shu sana bor fayllar ishlatiladi.');

                function sync() {
                    buttons.forEach(function (button) { button.classList.toggle('active', button.dataset.day === input.value); });
                }

                input.addEventListener('input', sync);
                sync();

                wrap.append(label, input, chips, hint);
                return { node: wrap, value: function () { return input.value; } };
            }

            /** config'dagi qo'shimcha maydonlar: select (masalan "Qaysi fayl?") va checkbox */
            function fieldsForm(fields) {
                const wrap = el('div');
                const getters = {};

                Object.entries(fields).forEach(function ([name, field]) {
                    const id = 'op-field-' + name;
                    const group = el('div', 'mt-3');

                    if (field.type === 'select') {
                        const label = el('label', 'form-label', field.label);
                        label.htmlFor = id;
                        const select = el('select', 'form-select');
                        select.id = id;
                        const empty = el('option', null, field.empty || '—');
                        empty.value = '';
                        select.append(empty);
                        Object.entries(field.choices).forEach(function ([value, text]) {
                            const option = el('option', null, text);
                            option.value = value;
                            select.append(option);
                        });
                        group.append(label, select);
                        getters[name] = function () { return select.value; };
                    } else if (field.type === 'checkbox') {
                        const check = el('div', 'form-check');
                        const input = el('input', 'form-check-input');
                        input.type = 'checkbox';
                        input.id = id;
                        const label = el('label', 'form-check-label', field.label);
                        label.htmlFor = id;
                        check.append(input, label);
                        group.append(check);
                        getters[name] = function () { return input.checked; };
                    }

                    wrap.append(group);
                });

                return {
                    node: wrap,
                    values: function () {
                        const values = {};
                        Object.keys(getters).forEach(function (name) { values[name] = getters[name](); });
                        return values;
                    },
                };
            }

            function duration(seconds) {
                if (seconds === null || seconds === undefined) return '—';
                seconds = Math.round(seconds);
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return m ? m + ' daq ' + s + ' s' : s + ' s';
            }

            function renderCards(latest) {
                document.querySelectorAll('[data-operation]').forEach(function (card) {
                    const run = latest[card.dataset.operation];
                    const last = card.querySelector('.op-last');
                    const button = card.querySelector('.op-run');

                    button.disabled = !!(run && run.active);
                    last.replaceChildren();

                    if (!run) {
                        last.textContent = 'Hali ishga tushirilmagan.';
                        return;
                    }

                    last.append(badge(run.status), ' ');
                    last.append(el('span', null,
                        (run.date ? formatDay(run.date) + ' kuni uchun · ' : '') +
                        (run.tags && run.tags.length ? run.tags.join(', ') + ' · ' : '') +
                        (run.finished_at || run.started_at || run.created_at) + (run.user ? ' · ' + run.user : '')));
                });
            }

            function renderRuns(runs) {
                const body = document.getElementById('runs-body');
                body.replaceChildren();

                if (!runs.length) {
                    const row = el('tr');
                    const cell = el('td', 'text-center text-muted py-4', 'Hali hech narsa ishga tushirilmagan.');
                    cell.colSpan = 7;
                    row.append(cell);
                    body.append(row);
                    return;
                }

                runs.forEach(function (run) {
                    const row = el('tr');
                    const statusCell = el('td');
                    statusCell.append(badge(run.status));

                    const actions = el('td', 'text-end');
                    const button = el('button', 'btn btn-sm btn-light', 'Natija');
                    button.type = 'button';
                    button.addEventListener('click', function () { openOutput(run.id, run.label); });
                    actions.append(button);

                    const labelCell = el('td', null, run.label);
                    if (run.date) {
                        labelCell.append(el('span', 'badge bg-secondary-subtle text-secondary-emphasis ms-2', formatDay(run.date)));
                    }
                    (run.tags || []).forEach(function (tag) {
                        labelCell.append(el('span', 'badge bg-primary-subtle text-primary-emphasis ms-1', tag));
                    });

                    row.append(
                        el('td', 'text-muted', run.id),
                        labelCell,
                        statusCell,
                        el('td', 'd-none d-md-table-cell', run.user || 'jadval'),
                        el('td', 'text-muted small d-none d-sm-table-cell', run.started_at || run.created_at),
                        el('td', 'text-muted small', duration(run.duration)),
                        actions
                    );
                    body.append(row);
                });
            }

            async function refresh() {
                try {
                    const response = await fetch(urls.runs, { headers: { Accept: 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();

                    renderCards(data.latest);
                    renderRuns(data.runs);

                    const waiting = data.runs.some(function (run) { return run.status === 'queued' && run.queued_seconds > 60; });
                    document.getElementById('worker-warning').classList.toggle('d-none', !waiting);

                    const anyActive = data.runs.some(function (run) { return run.active; });
                    schedule(anyActive ? 3000 : 15000);
                } catch (e) {
                    schedule(15000);
                }
            }

            function schedule(ms) {
                clearTimeout(pollTimer);
                pollTimer = setTimeout(refresh, ms);
            }

            async function loadOutput() {
                if (openRunId === null) return;

                const response = await fetch(urls.show + '/' + openRunId, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const run = await response.json();

                const pre = document.getElementById('output-body');
                const atBottom = pre.scrollTop + pre.clientHeight >= pre.scrollHeight - 20;
                pre.textContent = run.output || '(hali chiqish yo\'q)';
                if (atBottom) pre.scrollTop = pre.scrollHeight;

                const title = document.getElementById('output-title');
                title.replaceChildren(document.createTextNode('#' + run.id + ' ' + run.label + (run.date ? ' · ' + formatDay(run.date) : '') + ' '), badge(run.status));

                if (run.active) {
                    setTimeout(loadOutput, 3000);
                }
            }

            function openOutput(id, label) {
                openRunId = id;
                document.getElementById('output-title').textContent = '#' + id + ' ' + label;
                document.getElementById('output-body').textContent = 'Yuklanmoqda…';
                modal.show();
                loadOutput();
            }

            document.getElementById('output-modal').addEventListener('hidden.bs.modal', function () {
                openRunId = null;
            });

            document.querySelectorAll('.op-run').forEach(function (button) {
                button.addEventListener('click', function () {
                    const card = button.closest('[data-operation]');
                    const operation = card.dataset.operation;
                    const picker = card.dataset.needsDate === '1' ? datePicker() : null;
                    const fields = JSON.parse(card.dataset.fields || '{}');
                    const extra = Object.keys(fields).length ? fieldsForm(fields) : null;

                    let body = null;
                    if (picker || extra) {
                        body = el('div');
                        if (picker) body.append(picker.node);
                        if (extra) body.append(extra.node);
                    }

                    uiConfirm({
                        title: '"' + button.dataset.label + '" ishga tushirilsinmi?',
                        message: card.dataset.description,
                        icon: card.dataset.icon,
                        confirmText: 'Ishga tushirish',
                        body: body,
                        onConfirm: async function () {
                            const payload = {};

                            if (picker) {
                                if (!picker.value()) return 'Kunni tanlang.';
                                payload.date = picker.value();
                            }

                            if (extra) {
                                Object.assign(payload, extra.values());
                            }

                            const response = await fetch(urls.run + '/' + operation + '/run', {
                                method: 'POST',
                                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                                body: JSON.stringify(payload),
                            });
                            const data = await response.json().catch(function () { return {}; });

                            if (!response.ok) {
                                const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                                return firstError || data.message || 'Xatolik: HTTP ' + response.status;
                            }

                            uiToast(data.message || 'Navbatga qo\'shildi.', 'success');
                            refresh();
                            return true;
                        },
                    });
                });
            });

            refresh();
        })();
    </script>
@endpush
