<!DOCTYPE html>
<html lang="uz" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    @include('partials.theme-init')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirish — Scraping Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/ui.css') }}?v={{ filemtime(public_path('css/ui.css')) }}" rel="stylesheet">
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="text-center mb-4">
                <span class="brand-mark mx-auto mb-3" style="width: 44px; height: 44px; font-size: 20px; border-radius: 12px;">
                    <i class="fas fa-spider"></i>
                </span>
                <h1 class="h4 fw-bold mb-1">Scraping Dashboard</h1>
                <p class="text-muted mb-0">Davom etish uchun tizimga kiring</p>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('login.attempt') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username"
                                   placeholder="siz@izisol.uz">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Parol</label>
                            <input id="password" type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                            <label class="form-check-label" for="remember">Eslab qolish</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            Kirish <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
