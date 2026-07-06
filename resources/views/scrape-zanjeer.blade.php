<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Zanjeer Data Scraper</title>
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
                        Zanjeer Chegara
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-cog" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;">Zanjeer Data Scraper</h2>
                                        <p class="page-subtitle">Zanjeerdan ma'lumotlarni avtomatik yig'ish tizimi</p>
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
                                               <div class="card-body p-4">
                                            <div class="d-flex gap-2 mb-3">
                                                <button type="button" class="btn btn-outline-primary" 
                                                    onclick="window.open('https://crm.zanjeer.uz', '_blank')">
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
                                                    navigator.clipboard.writeText('https://crm.zanjeer.uz');

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
                                            <form id="scrapeForm" enctype="multipart/form-data">

                                                    <div class="mb-4">
                                                        <label for="merge5" class="form-label fw-bold">
                                                            <i class="fas fa-globe me-2 text-primary"></i>Scraping uchun fayl tanlang:
                                                        </label>
                                                        <select name="merge5" id="merge5" class="form-select form-select-lg" >
                                                            <option value="">-- Birlashtirilgan Fayllar --</option>
                                                        </select>
                                                    </div>

                                                <div class="d-grid gap-2">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-primary btn-lg py-3"
                                                        id="submitBtn"
                                                    >
                                                        <i class="fas fa-upload me-2"></i>
                                                        Faylni Yuklash va Scrapingni Boshlash
                                                    </button>

                                                    <div
                                                        id="scrapeStatus"
                                                        class="mt-3 text-center fw-bold"
                                                    ></div>

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
                                        
                                        <div id="status" style="margin-top: 15px; font-weight: bold;"></div>
                                        </div>

                                         <div class="card-body p-4">
                                        <form id="belarusSelect">
                                            <div class="mb-4">
                                                 <label for="merge1" class="form-label fw-bold">
                                                    <i class="fas fa-globe me-2 text-primary"></i>Birlashtirish uchun fayl tanlang: Ctrl bosib
                                                </label>
                                                <select name="merge1" id="merge1" class="form-select form-select-lg"  multiple>
                                                    <option value="">-- Qozoq Scraping Fayllar --</option>
                                                </select>
                                            </div>
                                            <div class="mb-4">
                                                <label for="merge2" class="form-label fw-bold">
                                                    <i class="fas fa-globe me-2 text-primary"></i>Birlashtirish olish uchun fayl tanlang: Ctrl bosib
                                                </label>
                                                <select name="merge2" id="merge2" class="form-select form-select-lg" multiple>
                                                    <option value="">-- Belarus Scraping Faylllar --</option>
                                                </select>
                                            </div>
                                            <div class="mb-4">
                                                <label for="merge3" class="form-label fw-bold">
                                                    <i class="fas fa-globe me-2 text-primary"></i>Birlashtirish olish uchun fayl tanlang: Ctrl bosib
                                                </label>
                                                <select name="merge3" id="merge3" class="form-select form-select-lg" multiple>
                                                    <option value="">-- Zitic Scraping Fayllar --</option>
                                                </select>
                                            </div>
                                            <div class="mb-4">
                                                <label for="merge4" class="form-label fw-bold">
                                                    <i class="fas fa-globe me-2 text-primary"></i>Birlashtirish olish uchun fayl tanlang: Ctrl bosib
                                                </label>
                                                <select name="merge4" id="merge4" class="form-select form-select-lg" multiple>
                                                    <option value="">-- Arhavi Scraping Fayllar --</option>
                                                </select>
                                            </div>
                                            

                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-primary btn-lg py-3" id="Merge">
                                                    <i class="fas fa-download me-2" aria-hidden="true"></i>
                                                    Birlashtirish
                                                </button>
                                            </div>
                                        </form>
                                        
                                        <div id="status" style="margin-top: 15px; font-weight: bold;"></div>
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

// =============================
// ELEMENTS
// =============================

const scrapeForm =
    document.getElementById('scrapeForm');


const submitBtn =
    document.getElementById('submitBtn');

const scrapeStatus =
    document.getElementById('scrapeStatus');

const loadingIndicator =
    document.getElementById('loadingIndicator');

const fileSelect =
    document.getElementById('region');

const mergeSelect1 =
    document.getElementById('merge1');

const mergeSelect2 =
    document.getElementById('merge2');
const mergeSelect3 =
    document.getElementById('merge3');
const mergeSelect4 =
    document.getElementById('merge4');

    const mergeSelect5 =
        document.getElementById('merge5');

const downloadForm =
    document.getElementById('belarusSelect');

const downloadStatus =
    document.getElementById('status');


// =============================
// LOAD FILES
// =============================

async function loadFiles()
{
    try {

        const response =
            await fetch('/api/zanjeer/files');

        const data =
            await response.json();

        fileSelect.innerHTML =
            '<option value="">-- Fayllar --</option>';

        if (data.status && data.files.length) {

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

async function loadMergeFiles()
{
    try {

        const response =
            await fetch('/api/zanjeer/merge-files');

        const data =
            await response.json();

        mergeSelect5.innerHTML =
            '<option value="">-- Fayllar --</option>';

        if (data.status && data.files.length) {

            data.files.forEach(file => {

                const option =
                    document.createElement('option');

                option.value = file;

                option.textContent = file;

                mergeSelect5.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
}

async function QozoqFiles()
{
    try {

        const response =
            await fetch('/api/qozoq/import/files');

        const data =
            await response.json();

        mergeSelect1.innerHTML =
            '<option value="">-- Qozoq Scraping Fayllar --</option>';

        if (data.status && data.files.length) {

            data.files.forEach(file => {

                const option =
                    document.createElement('option');

                option.value = file;

                option.textContent = file;

                mergeSelect1.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
}

async function BelarusFiles()
{
    try {

        const response =
            await fetch('/api/scrape/files');

        const data =
            await response.json();
            console.log(data);

        mergeSelect2.innerHTML =
            '<option value="">-- Belarus Scraping Fayllar --</option>';
        if (data.success && data.files.length) {
            data.files.forEach(file => {

                const option =
                    document.createElement('option');

                option.value = file;

                option.textContent = file;

                mergeSelect2.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
}

async function ZiticFiles()
{
    try {

        const response =
            await fetch('/api/zitic/files');

        const data =
            await response.json();
            console.log(data);

        mergeSelect3.innerHTML =
            '<option value="">--Zitic Scraping Fayllar --</option>';

        if (data.status && data.files.length) {

            data.files.forEach(file => {

                const option =
                    document.createElement('option');

                option.value = file;

                option.textContent = file;

                mergeSelect3.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
}


async function ArhaviFiles()
{
    try {

        const response =
            await fetch('/api/turkey/files');

        const data =
            await response.json();
            console.log(data);

        mergeSelect4.innerHTML =
            '<option value="">--Arhavi Scraping Fayllar --</option>';

        if (data.status && data.files.length) {

            data.files.forEach(file => {

                const option =
                    document.createElement('option');

                option.value = file;

                option.textContent = file;

                mergeSelect4.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
}


// =============================
// SCRAPE FORM
// =============================

scrapeForm.addEventListener(
    'submit',
    async function(e)
{
    e.preventDefault();

    const selectedFile =
        mergeSelect5.value;

    if (!selectedFile) {

        Swal.fire({
            icon: 'warning',
            title: 'Diqqat',
            text: 'Fayl tanlang'
        });

        return;
    }

    // BUTTON HIDE
    submitBtn.style.display = 'none';

    // LOADING SHOW
    loadingIndicator.classList.add('show');

    scrapeStatus.innerHTML =
        '⏳ Scraping queue ga yuborildi...';

    try {

        const response = await fetch(
            '/api/zanjeer/scrape',
            {
                method: 'POST',

                headers: {

                    'Content-Type': 'application/json',

                    'X-CSRF-TOKEN':
                        document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content
                },

                body: JSON.stringify({
                    file: selectedFile
                })
            }
        );

        const data =
            await response.json();

        if (!data.success) {

            scrapeStatus.innerHTML =
                '❌ Xatolik yuz berdi';

            submitBtn.style.display =
                'block';

            loadingIndicator
                .classList
                .remove('show');

            Swal.fire({
                icon: 'error',
                title: 'Xatolik',
                text: data.message ??
                    'Scrapingda xatolik'
            });

            return;
        }

        scrapeStatus.innerHTML =
            '✅ Scraping backgroundda ishlayapti';

        const interval = setInterval(
            async () => {

            try {

                const response =
                    await fetch(
                        '/api/zanjeer/check'
                    );

                const data =
                    await response.json();

                if (data.ready) {

                    clearInterval(interval);

                    scrapeStatus.innerHTML =
                        '✅ Scraping tugadi';

                    await loadFiles();

                    loadingIndicator
                        .classList
                        .remove('show');

                    submitBtn.style.display =
                        'block';

                    Swal.fire({
                        icon: 'success',
                        title: 'Tayyor',
                        text: 'Scraping tugadi'
                    });

                    // AUTO DOWNLOAD

                    window.location.href =
                        `/api/zanjeer/download/${data.file}`;
                }

            } catch (error) {

                console.error(error);
            }

        }, 5000);

    } catch (error) {

        console.error(error);

        scrapeStatus.innerHTML =
            '❌ Server xatosi';

        submitBtn.style.display =
            'block';

        loadingIndicator
            .classList
            .remove('show');

        Swal.fire({
            icon: 'error',
            title: 'Server xatosi',
            text: error.message
        });
    }

});

// =============================
// DOWNLOAD FILE
// =============================

downloadForm.addEventListener(
    'submit',
    async function(e)
{
    e.preventDefault();

    const file = fileSelect.value;

    if (!file) {

        alert('Fayl tanlang');

        return;
    }

    downloadStatus.innerHTML =
        '⏳ Fayl yuklanmoqda...';

    try {

        const response = await fetch(
            `/api/zanjeer/download/${encodeURIComponent(file)}`
        );

        if (!response.ok) {

            throw new Error('Fayl topilmadi');
        }

        const blob =
            await response.blob();

        const url =
            window.URL.createObjectURL(blob);

        const a =
            document.createElement('a');

        a.href = url;

        a.download = file;

        document.body.appendChild(a);

        a.click();

        a.remove();

        window.URL.revokeObjectURL(url);

        downloadStatus.innerHTML =
            '✅ Fayl muvaffaqiyatli yuklandi';

    } catch (error) {

        console.error(error);

        downloadStatus.innerHTML =
            '❌ Yuklashda xatolik';
    }

});


const mergeButton =
    document.getElementById('Merge');

const mergeForm =
    mergeButton.closest('form');

mergeForm.addEventListener(
    'submit',
    async function(e)
{
    e.preventDefault();

    const file1 =
    Array.from(
        mergeSelect1.selectedOptions
    ).map(option => option.value);

const file2 =
    Array.from(
        mergeSelect2.selectedOptions
    ).map(option => option.value);

const file3 =
    Array.from(
        mergeSelect3.selectedOptions
    ).map(option => option.value);

const file4 =
    Array.from(
        mergeSelect4.selectedOptions
    ).map(option => option.value);

    // FAQAT TANLANGAN FILELAR
    const selectedFiles = [];

    file1.forEach(file => {

        selectedFiles.push({
            url: '/api/qozoq/import/download',
            file: file
        });

    });

    file2.forEach(file => {

        selectedFiles.push({
            url: '/api/scrape/download',
            file: file
        });

    });

    file3.forEach(file => {

        selectedFiles.push({
            url: '/api/zitic/download',
            file: file
        });

    });

    file4.forEach(file => {

        selectedFiles.push({
            url: '/api/turkey/download',
            file: file
        });

    });

    // HECH BO'LMASA 1 TA FILE
    if (!selectedFiles.length) {

        Swal.fire({
            icon: 'warning',
            title: 'Diqqat',
            text: 'Kamida 1 ta fayl tanlang'
        });

        return;
    }

    mergeButton.disabled = true;

    mergeButton.innerHTML =
        '<i class="fas fa-spinner fa-spin me-2"></i>Birlashtirilmoqda...';

    try {

        const formData = new FormData();

        async function appendFile(apiUrl, fileName)
        {
            const response = await fetch(
                `${apiUrl}/${encodeURIComponent(fileName)}`
            );

            if (!response.ok) {
                throw new Error(
                    `File topilmadi: ${fileName}`
                );
            }

            const blob = await response.blob();

            formData.append(
                'files[]',
                blob,
                fileName
            );
        }

        // FAQAT TANLANGAN FILELARNI APPEND QILISH
        for (const item of selectedFiles) {

            await appendFile(
                item.url,
                item.file
            );
        }

        // NORMALIZE API
        const response = await fetch(
            '/api/normalize-excel',
            {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN':
                        document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content
                }
            }
        );

        const data =
            await response.json();

        if (!data.success) {
            throw new Error('Merge error');
        }

        Swal.fire({
            icon: 'success',
            title: 'Tayyor',
            text: 'Fayllar muvaffaqiyatli birlashtirildi'
        });

        window.open(
            data.download_url,
            '_blank'
        );

    } catch (error) {

        console.error(error);

        Swal.fire({
            icon: 'error',
            title: 'Xatolik',
            text: error.message
        });

    } finally {

        mergeButton.disabled = false;

        mergeButton.innerHTML =
            '<i class="fas fa-download me-2"></i>Birlashtirish';
    }

});


// =============================
// AUTO LOAD FILES
// =============================

document.addEventListener(
    'DOMContentLoaded',
    loadFiles
);

document.addEventListener(
    'DOMContentLoaded',
    QozoqFiles
);

document.addEventListener(
    'DOMContentLoaded',
    BelarusFiles
);

document.addEventListener(
    'DOMContentLoaded',
    ZiticFiles
);

document.addEventListener(
    'DOMContentLoaded',
    ArhaviFiles
);

document.addEventListener(
    'DOMContentLoaded',
    loadMergeFiles
);

</script>

</body>
</html>
