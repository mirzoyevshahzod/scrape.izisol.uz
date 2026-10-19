<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    @include('partials.auth-head')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — Scraping Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar-title {
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 25px 20px 15px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            white-space: nowrap;
            padding: 12px 14px;
            margin: 4px 8px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
        }
        .main-content {
            padding: 70px 30px 30px;
        }
        .page-card {
            background: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-gradient:hover {
            color: white;
            opacity: 0.9;
        }
        @media (max-width: 767.98px) {
            .sidebar { min-height: auto; }
            .sidebar-title { padding-top: 64px; }
            .main-content { padding: 70px 16px 24px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar">
                    <div class="sidebar-title">
                        <i class="fas fa-spider me-2"></i>
                        Scraping Loyihalar
                    </div>
                    <nav class="nav flex-column pb-3">
                        <a class="nav-link" href="{{ route('home') }}">
                            <i class="fas fa-home me-2"></i>Bosh sahifa
                        </a>
                        @canany(['operation.crm-login', 'operation.pipeline', 'operation.telegram', 'operation.scraping'])
                            <a class="nav-link @if(request()->routeIs('operations.*')) active @endif" href="{{ route('operations.index') }}">
                                <i class="fas fa-sliders-h me-2"></i>Boshqaruv
                            </a>
                        @endcanany
                        @can('admin.users')
                            <a class="nav-link @if(request()->routeIs('admin.users.*')) active @endif" href="{{ route('admin.users.index') }}">
                                <i class="fas fa-users me-2"></i>Foydalanuvchilar
                            </a>
                        @endcan
                        @can('admin.roles')
                            <a class="nav-link @if(request()->routeIs('admin.roles.*')) active @endif" href="{{ route('admin.roles.index') }}">
                                <i class="fas fa-user-shield me-2"></i>Rollar
                            </a>
                        @endcan
                    </nav>
                </div>
            </div>

            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    @if (session('status'))
                        <div class="alert alert-success alert-dismissible fade show">
                            {{ session('status') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if ($errors->has('user') || $errors->has('role'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            {{ $errors->first('user') ?: $errors->first('role') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
