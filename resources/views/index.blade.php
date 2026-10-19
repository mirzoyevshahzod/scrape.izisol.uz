@extends('layouts.app')

@section('title', 'Bosh sahifa')

@php
    $statusBadge = [
        'queued' => ['bg-secondary-subtle text-secondary-emphasis', 'Navbatda'],
        'running' => ['bg-primary-subtle text-primary-emphasis', 'Ishlayapti'],
        'success' => ['bg-success-subtle text-success-emphasis', 'Muvaffaqiyatli'],
        'failed' => ['bg-danger-subtle text-danger-emphasis', 'Xato'],
    ];
@endphp

@section('content')
    <div class="page-header">
        <div>
            <h1>Xush kelibsiz, {{ auth()->user()->name }}</h1>
            <p>{{ now()->translatedFormat('j F Y') }} · Kerakli bo'limni tanlang</p>
        </div>
        @if ($operations)
            <a href="{{ route('operations.index') }}" class="btn btn-primary">
                <i class="fas fa-sliders me-1"></i>Boshqaruv
            </a>
        @endif
    </div>

    @if ($operations)
        <div class="card mb-2">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="fas fa-clock-rotate-left me-2 text-muted"></i>So'nggi amallar</span>
                <a href="{{ route('operations.index') }}" class="small text-decoration-none">Barchasi</a>
            </div>
            @if ($recentRuns->isEmpty())
                <div class="empty-state py-4">
                    <div><i class="fas fa-inbox"></i></div>
                    Hali hech narsa ishga tushirilmagan.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Amal</th>
                                <th>Holat</th>
                                <th class="d-none d-md-table-cell">Kim</th>
                                <th>Vaqt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentRuns as $run)
                                @php [$class, $label] = $statusBadge[$run->status] ?? ['bg-secondary-subtle text-secondary-emphasis', $run->status]; @endphp
                                <tr>
                                    <td class="fw-medium">{{ config("operations.operations.{$run->operation}.label", $run->operation) }}</td>
                                    <td><span class="badge {{ $class }}">{{ $label }}</span></td>
                                    <td class="text-muted d-none d-md-table-cell">{{ $run->user?->name ?? 'jadval' }}</td>
                                    <td class="text-muted small">{{ ($run->finished_at ?? $run->started_at ?? $run->created_at)->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @forelse ($sections as $section)
        <div class="section-title">{{ $section['title'] }}</div>
        <div class="row g-3">
            @foreach ($section['items'] as $item)
                <div class="col-xl-3 col-lg-4 col-sm-6">
                    <a href="{{ $item['url'] }}" class="link-card">
                        <span class="icon-tile"><i class="fas {{ $item['icon'] }}"></i></span>
                        <span>
                            <span class="title d-block">{{ $item['label'] }}</span>
                            @if (! empty($item['description']))
                                <span class="desc d-block">{{ $item['description'] }}</span>
                            @endif
                        </span>
                    </a>
                </div>
            @endforeach
        </div>
    @empty
        @unless ($operations)
            <div class="card">
                <div class="empty-state">
                    <div><i class="fas fa-lock"></i></div>
                    Sizga hali hech qanday bo'lim ochilmagan. Administratorga murojaat qiling.
                </div>
            </div>
        @endunless
    @endforelse
@endsection
