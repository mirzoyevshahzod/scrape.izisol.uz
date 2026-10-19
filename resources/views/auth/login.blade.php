<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirish — Scraping Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            max-width: 400px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
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
    </style>
</head>
<body class="d-flex align-items-center justify-content-center px-3">
    <div class="card login-card w-100">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="fas fa-spider fa-3x mb-3" style="color: #764ba2;"></i>
                <h4 class="fw-bold mb-1">Scraping Dashboard</h4>
                <p class="text-muted small mb-0">Davom etish uchun tizimga kiring</p>
            </div>

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
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

                <button type="submit" class="btn btn-gradient w-100 py-2">
                    <i class="fas fa-sign-in-alt me-2"></i>Kirish
                </button>
            </form>
        </div>
    </div>
</body>
</html>
