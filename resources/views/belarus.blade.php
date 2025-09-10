<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belarus Data Scraper</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            font-size: 2.5rem;
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
            transition: all 0.3s ease;
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
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
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        .loading.show {
            display: block;
        }
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .data-table-container {
            margin-top: 40px;
        }
        .table-responsive {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .table tbody tr {
            transition: background-color 0.3s ease;
        }
        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
        .table tbody tr:nth-child(even) {
            background-color: rgba(102, 126, 234, 0.05);
        }
        .table th, .table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .filter-form .form-control, .filter-form .form-select {
            border-radius: 8px;
        }
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 5px;
            color: #667eea;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .pagination .page-link:hover {
            background: #e3f2fd;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="main-content">
                    <h1 class="header-title">
                        <i class="fas fa-chart-line me-2"></i>
                        Belarus Litva Chegara
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-chart-line" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title">Data Scraper</h2>
                                        <p class="page-subtitle">Chegara hududini tanlang va ma'lumotlarni yig'ishni boshlang</p>
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
                                            <form id="scrapeForm" action="{{ route('scrape') }}" method="POST">
                                                @csrf
                                                <div class="mb-4">
                                                    <label for="region" class="form-label fw-bold">
                                                        <i class="fas fa-globe me-2 text-primary"></i>Chegara hududini tanlang:
                                                    </label>
                                                    <select name="region" id="region" class="form-select form-select-lg" required>
                                                        <option value="">-- Chegara hududini tanlang --</option>
                                                        <option value="benyakoni">Бенякони</option>
                                                        <option value="brest">Брест</option>
                                                        <option value="grigorovschina">Григоровщина</option>
                                                        <option value="kamennyy_log">Каменный Лог</option>
                                                        <option value="kozlovichi">Козловичи</option>
                                                    </select>
                                                    <div class="form-text">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Ma'lumotlarni yig'ish uchun chegara hududini tanlang
                                                    </div>
                                                    <div class="invalid-feedback" id="region_error">
                                                        Iltimos, chegara hududini tanlang
                                                    </div>
                                                    @error('region')
                                                        <div class="text-danger mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="d-grid gap-2">
                                                    <button type="submit" class="btn btn-primary btn-lg py-3" id="submitBtn">
                                                        <i class="fas fa-rocket me-2"></i>
                                                        Ma'lumotlarni yig'ishni boshlash
                                                        <i class="fas fa-arrow-right ms-2"></i>
                                                    </button>
                                                </div>
                                            </form>

                                            <div class="loading" id="loadingIndicator">
                                                <div class="spinner"></div>
                                                <div class="loading-text">Ma'lumotlar yig'ilmoqda va Excel fayl tayyorlanmoqda...</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Filter Form -->
                                    <div class="filter-form mt-4">
                                        <form action="{{ route('scrape.data') }}" method="GET">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <input type="text" name="search" class="form-control" placeholder="Mashina raqami bo'yicha qidirish" value="{{ request('search') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <select name="region_filter" class="form-select">
                                                        <option value="">Barcha hududlar</option>
                                                        <option value="benyakoni" {{ request('region_filter') == 'benyakoni' ? 'selected' : '' }}>Бенякони</option>
                                                        <option value="brest" {{ request('region_filter') == 'brest' ? 'selected' : '' }}>Брест</option>
                                                        <option value="grigorovschina" {{ request('region_filter') == 'grigorovschina' ? 'selected' : '' }}>Григоровщина</option>
                                                        <option value="kamennyy_log" {{ request('region_filter') == 'kamennyy_log' ? 'selected' : '' }}>Каменный Лог</option>
                                                        <option value="kozlovichi" {{ request('region_filter') == 'kozlovichi' ? 'selected' : '' }}>Козловичи</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <button type="submit" class="btn btn-primary w-100">
                                                        <i class="fas fa-search me-2"></i>Filtrlash
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Database Data Display -->
                                    <div class="data-table-container mt-5">
                                        <div class="card shadow-sm">
                                            <div class="card-header">
                                                <h5 class="mb-0">
                                                    <i class="fas fa-database me-2"></i>
                                                    Saqlangan Ma'lumotlar
                                                </h5>
                                            </div>
                                            <div class="card-body p-4">
                                                @if($allData->isEmpty())
                                                    <div class="alert alert-info" role="alert">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Hozircha saqlangan ma'lumotlar mavjud emas.
                                                    </div>
                                                @else
                                                    <div class="table-responsive">
                                                        <table class="table table-hover table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Mashina Raqami</th>
                                                                    <th scope="col">Navbat Raqami</th>
                                                                    <th scope="col">Navbat Turi</th>
                                                                    <th scope="col">Ro'yxatga Olingan Sana</th>
                                                                    <th scope="col">Holat O'zgargan</th>
                                                                    <th scope="col">Holati</th>
                                                                    <th scope="col">Hudud</th>
                                                                    <th scope="col">Rusumi</th>
                                                                    <th scope="col">Litsenziya</th>
                                                                    <th scope="col">Korxona</th>
                                                                    <th scope="col">Faoliyat Turi</th>
                                                                    <th scope="col">Transport Turi</th>
                                                                    <th scope="col">Yuk Turi</th>
                                                                    <th scope="col">Berilgan Sana</th>
                                                                    <th scope="col">Amal Muddati</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($allData as $data)
                                                                    <tr>
                                                                        <td>{{ $data->reg_number ?? '-' }}</td>
                                                                        <td>{{ $data->order_number ?? '-' }}</td>
                                                                        <td>{{ $data->queue_type ?? '-' }}</td>
                                                                        <td>{{ $data->registration_date ?? '-' }}</td>
                                                                        <td>{{ $data->status_changed ?? '-' }}</td>
                                                                        <td>{{ $data->declarant_status ?? '-' }}</td>
                                                                        <td>{{ $data->region ?? '-' }}</td>
                                                                        <td>{{ $data->rusumi ?? '-' }}</td>
                                                                        <td>{{ $data->license ?? '-' }}</td>
                                                                        <td>{{ $data->company ?? '-' }}</td>
                                                                        <td>{{ $data->activity_type ?? '-' }}</td>
                                                                        <td>{{ $data->transport_type ?? '-' }}</td>
                                                                        <td>{{ $data->cargo_type ?? '-' }}</td>
                                                                        <td>{{ $data->issue_date ?? '-' }}</td>
                                                                        <td>{{ $data->expiry_date ?? '-' }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                        <!-- Pagination -->
                                                        <div class="d-flex justify-content-center mt-4">
                                                            {{ $allData->appends(request()->query())->links('pagination::bootstrap-5') }}
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-md-6 mb-3">
                                            <div class="card info-card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title text-primary">
                                                        <i class="fas fa-chart-line me-2"></i>Data Scraper Xususiyatlari
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Chegara hududi bo'yicha qidirish</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Ma'lumotlarni avtomatik yig'ish</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Natijalarni Excel va database'da saqlash</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Xavfsiz va tezkor scraping</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="card info-card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title text-primary">
                                                        <i class="fas fa-info-circle me-2"></i>Foydalanish Tartibi
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Chegara hududini tanlang</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>"Ma'lumotlarni yig'ishni boshlash" tugmasini bosing</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Jarayon yakunlanishini kuting</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Excel fayl avtomatik yuklanadi va natijalar jadvalda ko'rinadi</li>
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
                                                            <li class="mb-2"><i class="fas fa-download text-info me-2"></i>Natijalar Excel fayl sifatida avtomatik yuklanadi</li>
                                                            <li class="mb-2"><i class="fas fa-robot text-info me-2"></i>Selenium orqali scraping</li>
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
            const scrapeForm = document.getElementById('scrapeForm');
            const submitBtn = document.getElementById('submitBtn');
            const regionSelect = document.getElementById('region');
            const loadingIndicator = document.getElementById('loadingIndicator');

            if (scrapeForm) {
                scrapeForm.addEventListener('submit', function(e) {
                    if (!regionSelect.value) {
                        e.preventDefault();
                        regionSelect.classList.add('is-invalid');
                        regionSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        regionSelect.focus();
                        return;
                    }

                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Iltimos kuting...';
                    submitBtn.disabled = true;
                    loadingIndicator.classList.add('show');
                });

                if (regionSelect) {
                    regionSelect.addEventListener('change', function() {
                        if (this.value) {
                            this.classList.remove('is-invalid');
                            this.classList.add('is-valid');
                        } else {
                            this.classList.remove('is-valid');
                            this.classList.add('is-invalid');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>