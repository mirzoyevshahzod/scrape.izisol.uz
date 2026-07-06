<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Orginfo Data Scraper</title>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="main-content">
                    <h1 class="header-title">
                        <i class="fas fa-cog me-2"></i>
                        Orginfo Chegara
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-cog" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;">Orginfo Data Scraper</h2>
                                        <p class="page-subtitle">Orginfo dan ma'lumotlarni avtomatik yig'ish tizimi</p>
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
                                            <div class="d-flex gap-2 mb-3">
                                                <button type="button" class="btn btn-outline-primary" 
                                                    onclick="window.open('https://orginfo.uz/', '_blank')">
                                                    <i class="fas fa-globe"></i>
                                                    Saytga o'tish
                                                </button>

                                                <button type="button" class="btn btn-outline-secondary"
                                                    onclick="copyLink()">
                                                    <i class="fas fa-copy"></i>
                                                    Linkni nusxalash
                                                </button>
                                            </div>
                                            <script>
                                                function copyLink() {
                                                    navigator.clipboard.writeText('https://orginfo.uz/');

                                                    Swal.fire({
                                                        toast: true,
                                                        position: 'top-end',
                                                        icon: 'success',
                                                        title: 'Link nusxalandi!',
                                                        showConfirmButton: false,
                                                        timer: 2000,
                                                        timerProgressBar: true
                                                    });
                                                }
                                            </script>
                                            <form id="scrapeForm">
                                                <div class="mb-4">
                                                <label for="region" class="form-label fw-bold">
                                                    <i class="fas fa-globe me-2 text-primary"></i>Innlarni olish uchun fayl tanlang:
                                                </label>
                                                <select name="region" id="inn" class="form-select form-select-lg" required>
                                                    <option value="">--Zanjeer Fayllar --</option>
                                                </select>
                                                <div class="form-text">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Innlarni olish uchun fayl tanlang
                                                </div>
                                            </div>
                                                <div class="d-grid gap-2">
                                                    <button type="submit" class="btn btn-primary btn-lg py-3" id="submitBtn">
                                                        <i class="fas fa-rocket me-2"></i>
                                                        Innlarni Olish
                                                        <i class="fas fa-arrow-right ms-2"></i>
                                                    </button>
                                                    <div id="scrapeStatus" class="mt-3 text-center fw-bold"></div>
                                                </div>
                                            </form>

                                            <div class="loading" id="loadingIndicator">
                                                <div class="spinner"></div>
                                                <div class="loading-text">Ma'lumotlar qayta ishlanmoqda...</div>
                                            </div>
                                        </div>
                                    </div>
                                      <div id="status" class="mt-3"></div>
                                    <div class="card-body p-4">
                                        <form id="belarusSelect">
                                            <div class="mb-4">
                                                <label for="region" class="form-label fw-bold">
                                                    <i class="fas fa-globe me-2 text-primary"></i>Yuklab olish uchun fayl tanlang:
                                                </label>
                                                <select name="region" id="region" class="form-select form-select-lg" required>
                                                    <option value="">-- Fayllar --</option>
                                                </select>
                                                <div class="form-text">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Yuklab olish uchun fayl tanlang
                                                </div>
                                            </div>

                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-primary btn-lg py-3" id="Download">
                                                    <i class="fas fa-download me-2" aria-hidden="true"></i>
                                                    Yuklab Olish
                                                </button>
                                            </div>
                                        </form>
                                        <div id="downloadStatus" style="margin-top: 15px; font-weight: bold;"></div>
                                        </div>

                                    <!-- Filter Form -->
                               
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
const fileSelect =
    document.getElementById('inn');

const fileSelect1 =
    document.getElementById('region');
    
const loading =
    document.getElementById('loadingIndicator');

document
    .getElementById('scrapeForm')
    .addEventListener('submit', async function (e) {

    e.preventDefault();

    const selectedFile = fileSelect.value;

    const status =
        document.getElementById('scrapeStatus');

    const button =
        document.getElementById('submitBtn');

    if (!selectedFile) {

        Swal.fire({
            icon: 'warning',
            title: 'Fayl tanlang'
        });

        return;
    }

    try {

        // BUTTON DISABLE
        button.disabled = true;

        // LOADING SHOW
        loading.classList.add('show');

        status.innerHTML =
            'Scraping boshlandi...';

        // START REQUEST
        const response = await fetch(
            '/api/orginfo/fill-inn',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',

                    'X-CSRF-TOKEN': document
                        .querySelector(
                            'meta[name="csrf-token"]'
                        )
                        .getAttribute('content')
                },

                body: JSON.stringify({
                    file: selectedFile
                })
            }
        );

        const data =
            await response.json();

        if (!response.ok) {

            throw new Error(
                data.message ||
                'Xatolik yuz berdi'
            );
        }

        const fileName =
            data.file_name;

        status.innerHTML =
            'Maʼlumotlar yigʻilmoqda...';

        // CHECK EVERY 3 SECOND
        const interval = setInterval(
            async () => {

            try {

                const checkResponse =
                    await fetch(
                        '/api/orginfo/check/' +
                        fileName
                    );

                const checkData =
                    await checkResponse.json();

                // FILE READY
                if (checkData.ready) {

                    clearInterval(interval);

                    status.innerHTML =
                        'Tayyor ✅';

                    // HIDE LOADING
                    loading.classList.remove(
                        'show'
                    );

                    // ENABLE BUTTON
                    button.disabled = false;

                    Swal.fire({
                        icon: 'success',
                        title: 'Excel tayyor'
                    });

                    // AUTO DOWNLOAD
                    window.location.href =
                        checkData.download_url;
                }

            } catch (error) {

                clearInterval(interval);

                loading.classList.remove(
                    'show'
                );

                button.disabled = false;

                status.innerHTML =
                    'Xatolik ❌';

                Swal.fire({
                    icon: 'error',
                    title: 'Tekshirishda xatolik'
                });
            }

        }, 3000);

    } catch (error) {

        console.error(error);

        // HIDE LOADING
        loading.classList.remove('show');

        // ENABLE BUTTON
        button.disabled = false;

        status.innerHTML =
            'Xatolik ❌';

        Swal.fire({
            icon: 'error',
            title: 'Xatolik',
            text: error.message
        });
    }
});

// LOAD FILES
async function loadFiles()
{
    try {

        const response =
            await fetch('/api/zanjeer/files');

        const data =
            await response.json();

        fileSelect.innerHTML =
            '<option value="">--Zanjeer Fayllar --</option>';

        if (
            data.status &&
            data.files.length
        ) {

            data.files.forEach(file => {

                const option =
                    document.createElement('option');

                option.value = file;

                option.textContent = file;

                fileSelect.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
}

async function loadOrginfoFiles()
{
    try {

        const response =
            await fetch('/api/orginfo/files');

        const data =
            await response.json();

        fileSelect1.innerHTML =
            '<option value="">--Tayyor Fayllar --</option>';

        if (
            data.status &&
            data.files.length
        ) {

            data.files.forEach(file => {

                const option =
                    document.createElement('option');

                option.value = file;

                option.textContent = file;

                fileSelect1.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
}

// DOWNLOAD BUTTON
document
    .getElementById('belarusSelect')
    .addEventListener('submit', function (e) {

    e.preventDefault();

    const selectedFile =
        fileSelect1.value;

    const status =
        document.getElementById(
            'downloadStatus'
        );

    if (!selectedFile) {

        Swal.fire({
            icon: 'warning',
            title: 'Fayl tanlang'
        });

        return;
    }

    status.innerHTML =
        'Yuklab olinmoqda...';

    // DOWNLOAD
    window.location.href =
        '/api/orginfo/file-download/' +
        encodeURIComponent(selectedFile);

    setTimeout(() => {

        status.innerHTML =
            'Yuklab olindi ✅';

    }, 1500);
});


document.addEventListener(
    'DOMContentLoaded',
    loadFiles
);

document.addEventListener(
    'DOMContentLoaded',
    loadOrginfoFiles
);
</script>
</body>
</html>
