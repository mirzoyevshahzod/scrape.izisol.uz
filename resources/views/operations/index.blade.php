@extends('layouts.admin')

@section('title', 'Boshqaruv')

@push('styles')
    <style>
        .op-card .op-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 20px;
        }
        .op-output {
            background: #1e1e2e;
            color: #e0e0e0;
            border-radius: 10px;
            padding: 16px;
            max-height: 60vh;
            overflow: auto;
            font-size: 12.5px;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
@endpush

@section('content')
    <div class="mb-4">
        <h3 class="mb-1"><i class="fas fa-sliders-h me-2"></i>Boshqaruv</h3>
        <p class="text-muted mb-0">
            Amallar fonda (queue orqali) bajariladi — sahifani yopib qo'ysangiz ham davom etadi.
            Bir xil amal bir vaqtda faqat bir marta ishlaydi.
        </p>
    </div>

    <div id="worker-warning" class="alert alert-warning d-none">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Ish bir daqiqadan beri navbatda turibdi — serverda queue worker ishlamayotgan bo'lishi mumkin.
    </div>

    <div class="row g-4 mb-4">
        @foreach ($operations as $key => $operation)
            <div class="col-xl-3 col-md-6">
                <div class="card page-card op-card h-100" data-operation="{{ $key }}">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="op-icon"><i class="fas {{ $operation['icon'] }}"></i></div>
                            <h5 class="mb-0">{{ $operation['label'] }}</h5>
                        </div>
                        <p class="text-muted small flex-grow-1">{{ $operation['description'] }}</p>
                        <div class="small mb-3 op-last text-muted">Hali ishga tushirilmagan.</div>
                        <button type="button" class="btn btn-gradient w-100 op-run" data-label="{{ $operation['label'] }}">
                            <i class="fas fa-play me-1"></i>Ishga tushirish
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card page-card">
        <div class="card-body">
            <h5 class="mb-3">So'nggi ishlar</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Amal</th>
                            <th>Holat</th>
                            <th>Kim</th>
                            <th>Boshlangan</th>
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
                    <pre class="op-output mb-0" id="output-body"></pre>
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

            const STATUS = {
                queued: ['bg-secondary', 'Navbatda'],
                running: ['bg-primary', 'Ishlayapti'],
                success: ['bg-success', 'Muvaffaqiyatli'],
                failed: ['bg-danger', 'Xato'],
                stale: ['bg-warning text-dark', 'Tiqilib qolgan'],
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
                const [cls, label] = STATUS[status] || ['bg-secondary', status];
                const node = el('span', 'badge ' + cls, label);
                if (status === 'running') {
                    node.prepend(el('span', 'spinner-border spinner-border-sm me-1'));
                }
                return node;
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
                    last.append(el('span', null, (run.finished_at || run.started_at || run.created_at) + (run.user ? ' · ' + run.user : '')));
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
                    const button = el('button', 'btn btn-sm btn-outline-secondary', 'Natija');
                    button.type = 'button';
                    button.addEventListener('click', function () { openOutput(run.id, run.label); });
                    actions.append(button);

                    row.append(
                        el('td', 'text-muted', run.id),
                        el('td', null, run.label),
                        statusCell,
                        el('td', null, run.user || 'jadval'),
                        el('td', 'text-muted small', run.started_at || run.created_at),
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
                title.replaceChildren(document.createTextNode('#' + run.id + ' ' + run.label + ' '), badge(run.status));

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
                button.addEventListener('click', async function () {
                    const operation = button.closest('[data-operation]').dataset.operation;

                    if (!confirm('"' + button.dataset.label + '" ishga tushirilsinmi?')) return;

                    button.disabled = true;

                    try {
                        const response = await fetch(urls.run + '/' + operation + '/run', {
                            method: 'POST',
                            headers: { Accept: 'application/json' },
                        });
                        const data = await response.json().catch(function () { return {}; });

                        if (!response.ok) {
                            alert(data.message || 'Xatolik: HTTP ' + response.status);
                        }
                    } finally {
                        refresh();
                    }
                });
            });

            refresh();
        })();
    </script>
@endpush
