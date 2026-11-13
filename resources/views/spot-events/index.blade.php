@extends('layouts.app')

@section('title', 'Spot.uz tadbirlari')

@push('styles')
    <style>
        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--ui-surface-2);
            font-size: 0.8125rem;
        }
        .event-title {
            min-width: 220px;
        }
        tr.event-past td {
            opacity: 0.55;
        }
        .keyword-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 8px 4px 12px;
            border-radius: 999px;
            background: var(--ui-primary-soft);
            font-size: 0.875rem;
            font-weight: 500;
        }
        .keyword-chip .btn-close {
            font-size: 0.6rem;
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Spot.uz tadbirlari</h1>
            <p>
                <a href="https://www.spot.uz/ru/events/" target="_blank" rel="noopener">spot.uz/ru/events</a> har 2 soatda tekshiriladi;
                sarlavhasida kalit so'z bor yangi tadbirlar Telegram'ga ({{ $chatCount }} ta chat) yuboriladi, tadbirdan bir kun oldin eslatma keladi.
            </p>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
            <button type="button" class="btn btn-primary" id="search-btn" @disabled($lastRun['active'] ?? false)
                    title="spot.uz'ni hozir tekshirib, mos yangi tadbirlarni Telegram'ga yuborish">
                <i class="fas fa-magnifying-glass me-1"></i><span id="search-label">Qidirish</span>
            </button>
            <span class="small text-muted text-end" id="run-status"></span>
        </div>
    </div>

    <div id="worker-warning" class="alert alert-warning d-none">
        <i class="fas fa-triangle-exclamation me-1"></i>
        Qidiruv bir daqiqadan beri navbatda turibdi — serverda queue worker (operations) ishlamayotgan bo'lishi mumkin.
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="stat-pill"><span class="text-muted">Jami tadbirlar:</span><b>{{ $stats['total'] }}</b></span>
        <span class="stat-pill"><span class="text-muted">Mos, hali o'tmagan:</span><b>{{ $stats['upcoming'] }}</b></span>
        <span class="stat-pill"><span class="text-muted">Telegram'ga yuborilgan:</span><b>{{ $stats['sent'] }}</b></span>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-key me-2 text-muted"></i>Kalit so'zlar</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3" id="keyword-list">
                @forelse ($keywords as $keyword)
                    <form method="POST" action="{{ route('spot-events.keywords.destroy', $keyword) }}" class="keyword-chip">
                        @csrf
                        @method('DELETE')
                        <span>{{ $keyword->keyword }}</span>
                        <button type="submit" class="btn-close btn-close-sm" aria-label="O'chirish" title="O'chirish"
                                data-keyword="{{ $keyword->keyword }}"></button>
                    </form>
                @empty
                    <span class="text-danger small"><i class="fas fa-triangle-exclamation me-1"></i>Kalit so'z yo'q — Telegram'ga hech narsa yuborilmaydi.</span>
                @endforelse
            </div>

            <form method="POST" action="{{ route('spot-events.keywords.store') }}" class="d-flex flex-wrap gap-2 align-items-start">
                @csrf
                <div style="flex: 1 1 240px; max-width: 360px">
                    <input type="text" name="keyword" value="{{ old('keyword') }}" maxlength="100" required
                           class="form-control form-control-sm @if ($errors->keyword->has('keyword')) is-invalid @endif"
                           placeholder="Yangi kalit so'z, masalan: конференция">
                    @if ($errors->keyword->has('keyword'))
                        <div class="invalid-feedback">{{ $errors->keyword->first('keyword') }}</div>
                    @endif
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Qo'shish</button>
            </form>
            <div class="small text-muted mt-2">
                Tadbir sarlavhasida shu so'zlardan biri bo'lsa, Telegram'ga yuboriladi. Ruscha yozing; katta-kichik harf va defis farq qilmaydi,
                so'z qismi ham mos keladi (<i>форум</i> → <i>бизнес-форум</i>, <i>форума</i>).
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" action="{{ route('spot-events.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" style="max-width: 280px"
                       placeholder="Sarlavha bo'yicha…">
                <select name="filter" class="form-select form-select-sm" style="max-width: 220px" onchange="this.form.submit()">
                    <option value="all" @selected($filter === 'all')>Hammasi</option>
                    <option value="matched" @selected($filter === 'matched')>Kalit so'zga moslar</option>
                    <option value="sent" @selected($filter === 'sent')>Telegram'ga yuborilganlar</option>
                </select>
                <button type="submit" class="btn btn-sm btn-light"><i class="fas fa-filter me-1"></i>Filtrlash</button>
                @if ($q !== '' || $filter !== 'all')
                    <a href="{{ route('spot-events.index') }}" class="btn btn-sm btn-link text-muted">Tozalash</a>
                @endif
            </form>
        </div>

        @if ($events->isEmpty())
            <div class="empty-state">
                <i class="fas fa-calendar-xmark d-block"></i>
                {{ $stats['total'] ? 'Filtrga mos tadbir yo\'q.' : 'Hali tadbir yo\'q — "Qidirish" tugmasini bosing.' }}
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="d-none d-sm-table-cell">Sana</th>
                            <th>Tadbir</th>
                            <th class="d-none d-md-table-cell">Shahar</th>
                            <th class="d-none d-lg-table-cell">Narx</th>
                            <th>Telegram</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            @php($isPast = $event->ends_on && $event->ends_on->lt(today()))
                            <tr @class(['event-past' => $isPast])>
                                <td class="text-nowrap small d-none d-sm-table-cell">
                                    {{ $event->date_text ?? $event->starts_on?->format('d.m.Y') ?? '—' }}
                                    @if ($event->start_time)
                                        <div class="text-muted">{{ $event->start_time }}</div>
                                    @endif
                                </td>
                                <td class="event-title">
                                    <div class="small text-muted d-sm-none">
                                        {{ $event->date_text ?? $event->starts_on?->format('d.m.Y') ?? '—' }}{{ $event->start_time ? ', ' . $event->start_time : '' }}{{ $event->city ? ' · ' . $event->city : '' }}
                                    </div>
                                    <a href="{{ $event->url }}" target="_blank" rel="noopener" class="fw-medium">{{ $event->title }}</a>
                                    <div class="small mt-1 d-flex flex-wrap gap-1">
                                        @if ($event->category)
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $event->category }}</span>
                                        @endif
                                        @if ($event->matched_keyword)
                                            <span class="badge bg-primary-subtle text-primary-emphasis"><i class="fas fa-key me-1"></i>{{ $event->matched_keyword }}</span>
                                        @endif
                                        @if ($event->registration_url)
                                            <a href="{{ $event->registration_url }}" target="_blank" rel="noopener" class="text-muted">
                                                <i class="fas fa-arrow-up-right-from-square me-1"></i>ro'yxatdan o'tish
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell small">{{ $event->city ?? '—' }}</td>
                                <td class="d-none d-lg-table-cell small text-nowrap">{{ $event->price ?? '—' }}</td>
                                <td class="small text-nowrap">
                                    @if ($event->notified_at)
                                        <span class="badge bg-success-subtle text-success-emphasis"><i class="fas fa-check me-1"></i>Yuborilgan</span>
                                        <div class="text-muted">{{ $event->notified_at->format('d.m H:i') }}</div>
                                        @if ($event->reminded_at)
                                            <div class="text-muted" title="Eslatma yuborilgan">⏰ {{ $event->reminded_at->format('d.m H:i') }}</div>
                                        @endif
                                    @elseif (! $event->matched_keyword)
                                        <span class="text-muted">Mos emas</span>
                                    @elseif ($isPast)
                                        <span class="text-muted">O'tib ketgan</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Navbatda</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($events->hasPages())
                <div class="card-body border-top py-2">
                    {{ $events->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const urls = { run: @json(route('spot-events.run')), status: @json(route('spot-events.status')) };
            const button = document.getElementById('search-btn');
            const label = document.getElementById('search-label');
            const statusLine = document.getElementById('run-status');
            const workerWarning = document.getElementById('worker-warning');
            let pollTimer = null;

            const STATUS = {
                queued: 'navbatda',
                running: 'ishlayapti',
                success: 'tugadi',
                failed: 'xato bilan tugadi',
                stale: 'osilib qoldi',
            };

            function render(run) {
                if (!run) {
                    statusLine.textContent = '';
                    return;
                }

                button.disabled = run.active;
                label.textContent = run.active ? 'Qidirilmoqda…' : 'Qidirish';
                button.querySelector('i').className = run.active ? 'spinner-border spinner-border-sm me-1' : 'fas fa-magnifying-glass me-1';
                workerWarning.classList.toggle('d-none', !(run.queued_seconds > 60));

                const when = run.finished_at || run.created_at;
                const who = run.user ? ' · ' + run.user : '';
                statusLine.textContent = run.active
                    ? 'Qidiruv ' + STATUS[run.status] + '…'
                    : 'Oxirgi qidiruv: ' + when + who + ' — ' + (STATUS[run.status] || run.status) + (run.summary ? '. ' + run.summary : '');
                statusLine.classList.toggle('text-danger', run.status === 'failed' || run.status === 'stale');
            }

            async function poll() {
                const response = await fetch(urls.status, { headers: { Accept: 'application/json' } });
                const data = await response.json().catch(function () { return {}; });
                render(data.run);

                if (data.run && data.run.active) {
                    pollTimer = setTimeout(poll, 3000);
                } else if (pollTimer !== null) {
                    // qidiruv shu sahifada tugadi — jadvalni yangilaymiz
                    uiToast(data.run && data.run.status === 'success' ? 'Qidiruv tugadi.' : 'Qidiruv xato bilan tugadi.', data.run && data.run.status === 'success' ? 'success' : 'danger');
                    setTimeout(function () { window.location.reload(); }, 1200);
                }
            }

            button.addEventListener('click', async function () {
                button.disabled = true;
                const response = await fetch(urls.run, { method: 'POST', headers: { Accept: 'application/json' } });
                const data = await response.json().catch(function () { return {}; });

                if (!response.ok) {
                    uiToast(data.message || 'Xatolik: HTTP ' + response.status, 'danger');
                    button.disabled = false;
                    return;
                }

                render(data.run);
                pollTimer = setTimeout(poll, 2000);
            });

            document.querySelectorAll('.keyword-chip .btn-close').forEach(function (closeButton) {
                closeButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    const form = closeButton.closest('form');

                    uiConfirm({
                        title: '"' + closeButton.dataset.keyword + '" o\'chirilsinmi?',
                        message: 'Bu so\'zga mos yangi tadbirlar endi Telegram\'ga yuborilmaydi. Avval yuborilganlariga ta\'sir qilmaydi.',
                        icon: 'fa-trash',
                        variant: 'danger',
                        confirmText: 'O\'chirish',
                        onConfirm: function () {
                            form.submit();
                            return true;
                        },
                    });
                });
            });

            render(@json($lastRun));

            @if ($lastRun['active'] ?? false)
                pollTimer = setTimeout(poll, 3000);
            @endif
        })();
    </script>
@endpush
