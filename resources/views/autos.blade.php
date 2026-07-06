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
            padding: 20px;
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
             max-height: 700px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        #autosTable {
            min-width: 2500px;
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                                <div class="col-md-12">
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

                                   <div class="card shadow-sm mt-4">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="fas fa-truck me-2"></i>
                                            Autos Table
                                        </h5>

                                        <div class="d-flex gap-2">
                                            <button class="btn btn-success" id="uploadBtn">
                                                <i class="fas fa-upload me-1"></i>
                                                Upload
                                            </button>

                                            <button class="btn btn-primary" id="downloadBtn">
                                                <i class="fas fa-download me-1"></i>
                                                Download
                                            </button>

                                            <button class="btn btn-warning text-white" onclick="loadAutos(currentPage)"">
                                                <i class="fas fa-rotate-right me-1"></i>
                                                Refresh
                                            </button>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle" id="autosTable">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Model</th>
                                                        <th>Volume</th>
                                                        <th>License</th>
                                                        <th>Trailer License</th>
                                                        <th>Trailer Type</th>
                                                        <th>Car Number</th>
                                                        <th>Company</th>
                                                        <th>Tin</th>
                                                        <th>Phone</th>
                                                        <th>New Phone</th>
                                                        <th>Type Of Activity</th>
                                                        <th>Transport Type</th>
                                                        <th>Cargo Type</th>
                                                        <th>Given At</th>
                                                        <th>Expired At</th>
                                                        <th>Status</th>
                                                        <th>Regional Administrator</th>
                                                        <th>Created By</th>
                                                        <th>Driver Fio</th>
                                                        <th>Driver Phones</th>
                                                        <th>Deleted At</th>
                                                        <th>Created At</th>
                                                        <th>Updated At</th>
                                                    </tr>
                                                </thead>

                                                <tbody id="autosTableBody">
                                                    <tr>
                                                        <td colspan="24" class="text-center">
                                                            Yuklanmoqda...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div id="paginationInfo" class="text-muted"></div>

                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-primary" id="prevPageBtn">
                                            <i class="fas fa-angle-left"></i>
                                            Previous
                                        </button>

                                        <span class="btn btn-light" id="currentPage">
                                            1
                                        </span>

                                        <button class="btn btn-outline-primary" id="nextPageBtn">
                                            Next
                                            <i class="fas fa-angle-right"></i>
                                        </button>
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

        let currentPage = 1;
let lastPage = 1;
async function loadAutos(page = 1) {
    const tbody = document.getElementById('autosTableBody');

    tbody.innerHTML = `
        <tr>
            <td colspan="24" class="text-center">
                Yuklanmoqda...
            </td>
        </tr>
    `;

    try {
        const response = await fetch(`/api/autos?page=${page}`);
        const data = await response.json();

        tbody.innerHTML = '';

        data.data.forEach(auto => {
            tbody.innerHTML += `
                <tr>
                    <td>${auto.id ?? '-'}</td>
                    <td>${auto.model ?? '-'}</td>
                    <td>${auto.volume ?? '-'}</td>
                    <td>${auto.license ?? '-'}</td>
                    <td>${auto.trailer_license ?? '-'}</td>
                    <td>${auto.trailer_type ?? '-'}</td>
                    <td>${auto.car_number ?? '-'}</td>
                    <td>${auto.company_name ?? '-'}</td>
                    <td>${auto.tin ?? '-'}</td>
                    <td>${auto.phone ?? '-'}</td>
                    <td>${auto.new_phone ?? '-'}</td>
                    <td>${auto.type_of_activity ?? '-'}</td>
                    <td>${auto.transport_type ?? '-'}</td>
                    <td>${auto.cargo_type ?? '-'}</td>
                    <td>${auto.given_date ?? '-'}</td>
                    <td>${auto.expried_at ?? '-'}</td>
                    <td>
                        <span class="badge bg-success">
                            ${auto.status ?? '-'}
                        </span>
                    </td>
                    <td>${auto.regional_administration ?? '-'}</td>
                    <td>${auto.created_by ?? '-'}</td>
                    <td>${auto.driver_fio ?? '-'}</td>
                    <td>${auto.driver_phones ?? '-'}</td>
                    <td>${auto.deleted_at ?? '-'}</td>
                    <td>${auto.created_at ?? '-'}</td>
                    <td>${auto.updated_at ?? '-'}</td>
                </tr>
            `;
        });

        currentPage = data.current_page;
        lastPage = data.last_page;
        document.getElementById('currentPage').textContent =
        `${currentPage} / ${lastPage}`;
        document.getElementById('paginationInfo').textContent = `Total: ${data.total} records`;
        document.getElementById('prevPageBtn').disabled = currentPage === 1;
        document.getElementById('nextPageBtn').disabled = currentPage === lastPage;


    } catch (error) {
        console.error(error);

        tbody.innerHTML = `
            <tr>
                <td colspan="24" class="text-danger text-center">
                    Xatolik yuz berdi
                </td>
            </tr>
        `;
    }
}

loadAutos();

document.getElementById('prevPageBtn')
.addEventListener('click', () => {

    if (currentPage > 1) {
        loadAutos(currentPage - 1);
    }
});

document.getElementById('nextPageBtn')
.addEventListener('click', () => {

    if (currentPage < lastPage) {
        loadAutos(currentPage + 1);
    }
});


</script>
</body>
</html>
