<!DOCTYPE html>
<html lang="uz" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    @include('partials.auth-head')
    @include('partials.theme-init')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — Scraping Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/ui.css') }}?v={{ filemtime(public_path('css/ui.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <div class="app-shell">
        <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="app-sidebar" aria-label="Menyu">
            <div class="offcanvas-body">
                <div class="sidebar-brand">
                    <a href="{{ route('home') }}" class="d-flex align-items-center gap-2 text-reset text-decoration-none">
                        <span class="brand-mark"><i class="fas fa-spider"></i></span>
                        Scraping
                    </a>
                    <button type="button" class="btn-close ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#app-sidebar" aria-label="Yopish"></button>
                </div>

                <nav class="sidebar-nav">
                    @foreach (\App\Support\Navigation::for(auth()->user()) as $group)
                        @if ($group['title'])
                            <div class="nav-section">{{ $group['title'] }}</div>
                        @endif
                        @foreach ($group['items'] as $item)
                            <a href="{{ $item['url'] }}" class="nav-item-ui @if($item['active']) active @endif"
                               @if($item['active']) aria-current="page" @endif>
                                <i class="fas {{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <button type="button" class="icon-btn d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#app-sidebar" aria-label="Menyuni ochish">
                    <i class="fas fa-bars"></i>
                </button>

                <h2 class="topbar-title">@yield('title')</h2>

                <div class="ms-auto d-flex align-items-center gap-2">
                    <button type="button" class="icon-btn" id="theme-toggle" aria-label="Mavzuni almashtirish" title="Mavzuni almashtirish">
                        <i class="fas fa-moon"></i>
                    </button>

                    <div class="dropdown">
                        <button type="button" class="user-btn" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                            <i class="fas fa-chevron-down small text-muted"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="px-3 py-2">
                                <div class="fw-semibold">{{ auth()->user()->name }}</div>
                                <div class="text-muted small">{{ auth()->user()->email }}</div>
                                @foreach (auth()->user()->getRoleNames() as $role)
                                    <span class="badge bg-primary-subtle text-primary-emphasis mt-1">{{ $role }}</span>
                                @endforeach
                            </div>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-arrow-right-from-bracket me-2"></i>Chiqish
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="app-content">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-circle-check me-1"></i>{{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Yopish"></button>
                    </div>
                @endif

                @if ($errors->has('user') || $errors->has('role'))
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-circle-exclamation me-1"></i>{{ $errors->first('user') ?: $errors->first('role') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Yopish"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/ui.js') }}?v={{ filemtime(public_path('js/ui.js')) }}"></script>
    <script>
        (function () {
            const button = document.getElementById('theme-toggle');
            const icon = button.querySelector('i');

            function sync() {
                const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
            }

            button.addEventListener('click', function () {
                const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', next);
                try { localStorage.setItem('theme', next); } catch (e) {}
                sync();
            });

            sync();
        })();
    </script>
    @stack('scripts')
</body>
</html>
