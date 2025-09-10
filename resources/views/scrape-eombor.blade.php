<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Ombor Scraper</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            padding: 30px;
        }
        .page-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            min-height: 400px;
        }
        .page-title {
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }
        .page-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            text-align: center;
        }
        .header-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 30px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .card {
            border: none;
            border-radius: 15px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }
        .info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="main-content">
                    <h1 class="header-title">
                        <i class="fas fa-warehouse me-2"></i>
                        E-Ombor Scraper
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-warehouse" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;">E-Ombor Scraper</h2>
                                        <p class="page-subtitle">E-ombor va Mintrans ma'lumotlarini avtomatik olish tizimi</p>
                                    </div>
                                    
                                    @if(session('error'))
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            {{ session('error') }}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    @endif

                                    @if(session('success'))
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="fas fa-check-circle me-2"></i>
                                            {{ session('success') }}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    @endif

                                    <div class="card shadow-sm">
                                        <div class="card-header">
                                            <h5 class="mb-0">
                                                <i class="fas fa-cogs me-2"></i>
                                                Scraping Parametrlari
                                            </h5>
                                        </div>
                                        <div class="card-body p-4">
                                            <form action="{{ route('scrape.eombor.process') }}" method="POST" id="eomborForm">
                                                @csrf
                                                <div class="row">
                                                    <div class="col-md-12 mb-4">
                                                        <label for="command_type" class="form-label fw-bold">
                                                            <i class="fas fa-list-ul me-2 text-primary"></i>Command Type:
                                                        </label>
                                                        <select id="command_type" name="command_type" class="form-select form-select-lg" required>
                                                            <option value="" disabled selected>Komanda turini tanlang</option>
                                                            <option value="scrape_eombor">
                                                                🏭 E-Ombor - Tranzit ID bo'yicha qidirish
                                                            </option>
                                                            <option value="scrape_mintrans">
                                                                🚛 Mintrans - Transport raqami bo'yicha litsenziya
                                                            </option>
                                                        </select>
                                                        <div class="invalid-feedback" id="command_type_error">
                                                            Iltimos, komanda turini tanlang
                                                        </div>
                                                        @error('command_type')
                                                            <div class="text-danger mt-1">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6 mb-4">
                                                        <label for="start_id" class="form-label fw-bold">
                                                            <i class="fas fa-play me-2 text-success"></i>Boshlang'ich ID:
                                                        </label>
                                                        <input type="text" 
                                                               id="start_id" 
                                                               name="start_id" 
                                                               class="form-control form-control-lg" 
                                                               required 
                                                               placeholder="AT2025XXXXXXX"
                                                               pattern="^[A-Z]{2}\d{11}$"
                                                               value="{{ old('start_id') }}">
                                                        <div class="form-text">
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Format: 2 ta harf + 11 ta raqam (masalan: AT20250346677)
                                                        </div>
                                                        <div class="invalid-feedback" id="start_id_error">
                                                            Iltimos, to'g'ri formatda ID kiriting
                                                        </div>
                                                        @error('start_id')
                                                            <div class="text-danger mt-1">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="col-md-6 mb-4">
                                                        <label for="count" class="form-label fw-bold">
                                                            <i class="fas fa-hashtag me-2 text-warning"></i>Soni:
                                                        </label>
                                                        <input type="number" 
                                                               id="count" 
                                                               name="count" 
                                                               class="form-control form-control-lg" 
                                                               required 
                                                               min="1" 
                                                               max="4000" 
                                                               placeholder="Nechta ma'lumot olish kerak?"
                                                               value="{{ old('count') }}">
                                                        <div class="form-text">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                            Maksimal: 4000 ta
                                                        </div>
                                                        <div class="invalid-feedback" id="count_error">
                                                            Iltimos, 1 dan 4000 gacha son kiriting
                                                        </div>
                                                        @error('count')
                                                            <div class="text-danger mt-1">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="d-grid gap-2">
                                                    <button type="submit" class="btn btn-primary btn-lg py-3">
                                                        <i class="fas fa-rocket me-2"></i>
                                                        Scrapingni Boshlash
                                                        <i class="fas fa-arrow-right ms-2"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-md-6 mb-3">
                                            <div class="card info-card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title text-primary">
                                                        <i class="fas fa-warehouse me-2"></i>E-Ombor Xususiyatlari
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Tranzit ID bo'yicha qidirish</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Bojxona ma'lumotlari</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Transport va yuk ma'lumotlari</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>INN va korxona ma'lumotlari</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="card info-card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title text-primary">
                                                        <i class="fas fa-truck me-2"></i>Mintrans Xususiyatlari
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Transport raqami bo'yicha qidirish</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Litsenziya ma'lumotlari</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Korxona va faoliyat turi</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Hududiy boshqarma ma'lumotlari</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <div class="card" style="border-left: 4px solid #667eea;">
                                            <div class="card-body">
                                                <h5 class="card-title text-primary">
                                                    <i class="fas fa-lightbulb me-2"></i>Muhim ma'lumotlar:
                                                </h5>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <ul class="list-unstyled mb-0">
                                                            <li class="mb-2"><i class="fas fa-download text-info me-2"></i>Excel fayl avtomatik yuklab olinadi</li>
                                                            <li class="mb-2"><i class="fas fa-robot text-info me-2"></i>Selenium WebDriver ishlatiladi</li>
                                                        </ul>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <ul class="list-unstyled mb-0">
                                                            <li class="mb-2"><i class="fas fa-clock text-info me-2"></i>Jarayon vaqt talab qiladi</li>
                                                            <li class="mb-0"><i class="fas fa-shield-alt text-info me-2"></i>Xavfsiz va tezkor ishlaydi</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const eomborForm = document.getElementById('eomborForm');
            
            if (eomborForm) {
                eomborForm.addEventListener('submit', function(e) {
                    const commandType = document.getElementById('command_type').value;
                    const startId = document.getElementById('start_id').value;
                    const count = document.getElementById('count').value;
                    
                    // Clear previous errors
                    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

                    let hasError = false;

                    // Command type validation
                    if (!commandType) {
                        document.getElementById('command_type').classList.add('is-invalid');
                        hasError = true;
                    }

                    // Start ID validation
                    if (!/^[A-Z]{2}\d{11}$/.test(startId)) {
                        document.getElementById('start_id').classList.add('is-invalid');
                        hasError = true;
                    }

                    // Count validation
                    if (count <= 0 || count > 4000 || !Number.isInteger(Number(count))) {
                        document.getElementById('count').classList.add('is-invalid');
                        hasError = true;
                    }

                    if (hasError) {
                        e.preventDefault();
                        // Scroll to first error
                        const firstError = document.querySelector('.is-invalid');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                    } else {
                        // Show loading state
                        const submitBtn = e.target.querySelector('button[type="submit"]');
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Iltimos kuting...';
                        submitBtn.disabled = true;
                    }
                });
            }

            // Real-time validation
            const startIdInput = document.getElementById('start_id');
            const countInput = document.getElementById('count');

            if (startIdInput) {
                startIdInput.addEventListener('input', function() {
                    if (/^[A-Z]{2}\d{11}$/.test(this.value)) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                    }
                });
            }

            if (countInput) {
                countInput.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    if (value >= 1 && value <= 4000) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                    }
                });
            }
        });
    </script>
</body>
</html>