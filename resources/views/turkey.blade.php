<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Turkey Data Scraper</title>
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
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        .loading.show {
            display: block;
        }
        .spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 32px;
            height: 32px;
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
        }
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: sticky;
            top: 0;
            z-index: 1;
            border: none;
            padding: 15px 12px;
            font-weight: 600;
        }
        .table tbody tr {
            transition: all 0.3s ease;
        }
        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.08);
            transform: translateY(-1px);
        }
        .table tbody tr:nth-child(even) {
            background-color: rgba(102, 126, 234, 0.02);
        }
        .table tbody td {
            padding: 15px 12px;
            vertical-align: middle;
            border: none;
            border-bottom: 1px solid #e9ecef;
        }
        .view-details-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }
        .view-details-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
            transform: scale(1.1);
            color: white;
        }
        .filter-form .form-control {
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

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
            padding: 20px 25px;
        }
        .modal-title {
            font-weight: 600;
            font-size: 1.3rem;
        }
        .modal-body {
            padding: 25px;
            background: #f8f9fa;
        }
        .modal-footer {
            border: none;
            padding: 20px 25px;
            background: white;
            border-radius: 0 0 15px 15px;
        }
        
        /* Data Group Styles */
        .data-group {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 4px solid;
        }
        .data-group.transport { border-left-color: #28a745; }
        .data-group.license { border-left-color: #dc3545; }
        .data-group.company { border-left-color: #fd7e14; }
        .data-group.system { border-left-color: #6c757d; }

        .data-group-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .data-group-title i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .data-row:last-child {
            border-bottom: none;
        }
        .data-label {
            font-weight: 500;
            color: #495057;
        }
        .data-value {
            font-weight: 400;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }
        
        /* Value color coding */
        .value-primary { color: #667eea; font-weight: 600; }
        .value-success { color: #28a745; font-weight: 600; }
        .value-warning { color: #fd7e14; font-weight: 600; }
        .value-danger { color: #dc3545; font-weight: 600; }
        .value-info { color: #17a2b8; font-weight: 600; }
        .value-muted { color: #6c757d; }

        .btn-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: rgba(255,255,255,0.8);
        }
        .btn-close:hover {
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="main-content">
                    <h1 class="header-title">
                        <i class="fas fa-cog me-2"></i>
                        Turkiya Gruziya Chegara
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-cog" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;">Turkey Data Scraper</h2>
                                        <p class="page-subtitle">Turkiyadan ma'lumotlarni avtomatik yig'ish tizimi</p>
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
                                            <form id="scrapeForm" action="{{ route('turkey.scrape') }}" method="POST">
                                                @csrf
                                                <div class="d-grid gap-2">
                                                    <button type="submit" class="btn btn-primary btn-lg py-3" id="submitBtn">
                                                        <i class="fas fa-rocket me-2"></i>
                                                        Scrapingni Boshlash
                                                        <i class="fas fa-arrow-right ms-2"></i>
                                                    </button>
                                                </div>
                                            </form>

                                            <div class="loading" id="loadingIndicator">
                                                <div class="spinner"></div>
                                                <div class="loading-text">Ma'lumotlar qayta ishlanmoqda...</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Filter Form -->
                                    <div class="filter-form mt-4">
                                        <form action="{{ route('turkey.index') }}" method="GET">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <input type="text" name="search" class="form-control" placeholder="Mashina raqami bo'yicha qidirish" value="{{ request('search') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <select name="region_filter" class="form-select">
                                                        <option value="">Barcha kirish joylari</option>
                                                        <option value="Arhavi" {{ request('region_filter') == 'Arhavi' ? 'selected' : '' }}>Arhavi</option>
                                                        <!-- Boshqa kirish joylari bo'lsa, bu yerga qo'shing -->
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
                                                    @if(isset($allData) && $allData->total() > 0)
                                                        <span class="badge bg-light text-dark ms-2">{{ $allData->total() }} ta yozuv</span>
                                                    @endif
                                                </h5>
                                            </div>
                                            <div class="card-body p-4">
                                                @if(isset($allData) && $allData->isEmpty())
                                                    <div class="alert alert-info" role="alert">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        Hozircha saqlangan ma'lumotlar mavjud emas.
                                                    </div>
                                                @elseif(isset($allData))
                                                    <div class="table-responsive">
                                                        <table class="table table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col" style="width: 120px;">Navbat Raqami</th>
                                                                    <th scope="col" style="width: 140px;">Avtomobil Raqami</th>
                                                                    <th scope="col" style="width: 120px;">Sana</th>
                                                                    <th scope="col" style="width: 120px;">Kirish Joyi</th>
                                                                    <th scope="col">Korxona Nomi</th>
                                                                    <th scope="col" style="width: 80px; text-align: center;">Tafsilot</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($allData as $data)
                                                                    <tr>
                                                                        <td><span class="value-primary">{{ $data->sira ?? '-' }}</span></td>
                                                                        <td><strong class="value-success">{{ $data->plaka ?? '-' }}</strong></td>
                                                                        <td><span class="value-info">{{ $data->tarih ?? '-' }}</span></td>
                                                                        <td><span class="value-warning">{{ $data->yer ?? '-' }}</span></td>
                                                                        <td>
                                                                            <span class="value-danger">
                                                                                {{ $data->company ? (strlen($data->company) > 40 ? substr($data->company, 0, 40) . '...' : $data->company) : '-' }}
                                                                            </span>
                                                                        </td>
                                                                        <td class="text-center">
                                                                            <button type="button" class="btn view-details-btn" data-id="{{ $data->id }}" title="Tafsilotlarni ko'rish">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </td>
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
                                                        <i class="fas fa-cog me-2"></i>Turkey Data Scraper Xususiyatlari
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Turkiyadan ma'lumotlarni yig'ish</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Avtomatik va tezkor jarayon</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Natijalarni Excel formatida saqlash</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Xavfsiz va ishonchli tizim</li>
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
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>"Scrapingni boshlash" tugmasini bosing</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Jarayon yakunlanishini kuting</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Natijalar avtomatik saqlanadi</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Selenium orqali xavfsiz scraping</li>
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
                                                            <li class="mb-2"><i class="fas fa-download text-info me-2"></i>Natijalar Excel fayl sifatida yuklanadi</li>
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

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Ma'lumot tafsilotlari
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalBodyContent">
                    <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yuklanmoqda...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Yopish
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scrapeForm = document.getElementById('scrapeForm');
            const submitBtn = document.getElementById('submitBtn');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));

            // Scraping form submission
            if (scrapeForm) {
                scrapeForm.addEventListener('submit', function(e) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Iltimos kuting...';
                    submitBtn.disabled = true;
                    loadingIndicator.classList.add('show');
                });
            }

            // Details button click handlers
            document.addEventListener('click', function(e) {
                if (e.target.closest('.view-details-btn')) {
                    const btn = e.target.closest('.view-details-btn');
                    const id = btn.getAttribute('data-id');
                    loadDetails(id);
                }
            });

            // Load details function
            function loadDetails(id) {
                const modalBody = document.getElementById('modalBodyContent');
                
                // Show loading state
                modalBody.innerHTML = `
                    <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yuklanmoqda...</span>
                        </div>
                    </div>
                `;
                
                // Show modal
                detailsModal.show();
                
                // Fetch data
                fetch(`/turkey/details/${id}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderDetailsModal(data.data);
                    } else {
                        showError('Ma\'lumot yuklanmadi: ' + (data.message || 'Noma\'lum xato'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Ma\'lumot yuklashda xatolik yuz berdi');
                });
            }

            // Render details in modal
            function renderDetailsModal(data) {
                const modalBody = document.getElementById('modalBodyContent');
                
                modalBody.innerHTML = `
                    <!-- Asosiy Ma'lumotlar -->
                    <div class="data-group transport">
                        <div class="data-group-title">
                            <i class="fas fa-car text-success"></i>
                            Asosiy Ma'lumotlar
                        </div>
                        <div class="data-row">
                            <span class="data-label">Tartib raqami:</span>
                            <span class="data-value value-primary">${data.sira || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Kirish tartib raqami:</span>
                            <span class="data-value value-info">${data.giris || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Avtomobil raqami:</span>
                            <span class="data-value value-success"><strong>${data.plaka || '-'}</strong></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Kirish sanasi:</span>
                            <span class="data-value value-warning">${data.tarih || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Kirish joyi:</span>
                            <span class="data-value value-danger">${data.yer || '-'}</span>
                        </div>
                    </div>

                    <!-- Transport Ma'lumotlari -->
                    <div class="data-group transport">
                        <div class="data-group-title">
                            <i class="fas fa-truck text-success"></i>
                            Transport Ma'lumotlari
                        </div>
                        <div class="data-row">
                            <span class="data-label">Rusumi:</span>
                            <span class="data-value value-primary">${data.rusumi || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Yuk ko'tarish qobiliyati:</span>
                            <span class="data-value value-info">${data.yuk_qobiliyati || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Transport turi:</span>
                            <span class="data-value value-success">${data.transport_turi || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Yuk turi:</span>
                            <span class="data-value value-warning">${data.yuk_turi || '-'}</span>
                        </div>
                    </div>

                    <!-- Litsenziya Ma'lumotlari -->
                    <div class="data-group license">
                        <div class="data-group-title">
                            <i class="fas fa-certificate text-danger"></i>
                            Litsenziya Ma'lumotlari
                        </div>
                        <div class="data-row">
                            <span class="data-label">Litsenziya varaqasi:</span>
                            <span class="data-value value-danger">${data.license || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Davlat raqami:</span>
                            <span class="data-value value-primary">${data.state_number || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Berilgan sana:</span>
                            <span class="data-value value-info">${data.berilgan_sana || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Amal qilish muddati:</span>
                            <span class="data-value value-warning">${data.amal_muddati || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Holati:</span>
                            <span class="data-value ${getStatusColor(data.holati)}">${data.holati || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Hududiy boshqarma:</span>
                            <span class="data-value value-muted">${data.hududiy_boshqarma || '-'}</span>
                        </div>
                    </div>

                    <!-- Korxona Ma'lumotlari -->
                    <div class="data-group company">
                        <div class="data-group-title">
                            <i class="fas fa-building text-warning"></i>
                            Korxona Ma'lumotlari
                        </div>
                        <div class="data-row">
                            <span class="data-label">Korxona nomi:</span>
                            <span class="data-value value-danger">${data.company || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Telefon raqami:</span>
                            <span class="data-value value-success">${data.phone_number || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Faoliyat turi:</span>
                            <span class="data-value value-info">${data.faoliyat_turi || '-'}</span>
                        </div>
                    </div>

                    <!-- Tizim Ma'lumotlari -->
                    <div class="data-group system">
                        <div class="data-group-title">
                            <i class="fas fa-cog text-muted"></i>
                            Tizim Ma'lumotlari
                        </div>
                        <div class="data-row">
                            <span class="data-label">Qo'shilgan vaqt:</span>
                            <span class="data-value value-muted">${data.created_at || '-'}</span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Oxirgi yangilanish:</span>
                            <span class="data-value value-muted">${data.updated_at || '-'}</span>
                        </div>
                    </div>
                `;
            }

            // Get status color based on value
            function getStatusColor(status) {
                if (!status) return 'value-muted';
                const statusLower = status.toLowerCase();
                if (statusLower.includes('faol') || statusLower.includes('active')) {
                    return 'value-success';
                } else if (statusLower.includes('muddati') || statusLower.includes('expired')) {
                    return 'value-danger';
                } else if (statusLower.includes('kutish') || statusLower.includes('pending')) {
                    return 'value-warning';
                } else {
                    return 'value-info';
                }
            }

            // Show error in modal
            function showError(message) {
                const modalBody = document.getElementById('modalBodyContent');
                modalBody.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${message}
                    </div>
                `;
            }

            // Auto refresh functionality (optional)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autorefresh') === '1') {
                setTimeout(() => {
                    window.location.reload();
                }, 30000); // 30 seconds
            }
        });

        // Global function for manual refresh
        function refreshPage() {
            window.location.reload();
        }

        // Export to Excel function (if needed)
        function exportToExcel() {
            window.location.href = '{{ route("turkey.scrape") }}';
        }
    </script>
</body>
</html>