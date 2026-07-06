<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mintrans Avtoraqam Scraper</title>
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
        .upload-area {
            border: 2px dashed rgba(102, 126, 234, 0.3);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .drag-over {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }
        .file-uploaded {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
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
                        <i class="fas fa-search me-2"></i>
                        Mintrans Avtoraqam Scraper
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-search" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;">Mintrans Avtoraqam Scraper</h2>
                                        <p class="page-subtitle">Excel fayl orqali avtoraqamlar bo'yicha ma'lumotlarni avtomatik olish tizimi</p>
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
                                                Fayl Yuklash Parametrlari
                                            </h5>
                                        </div>
                                        <div class="card-body p-4">
                                               <div class="card-body p-4">
                                            <div class="d-flex gap-2 mb-3">
                                                <button type="button" class="btn btn-outline-primary" 
                                                    onclick="window.open('https://info.mintrans.uz/#/info/onSearch', '_blank')">
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
                                                    navigator.clipboard.writeText('https://info.mintrans.uz/#/info/onSearch');

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
                                            <form id="uploadForm" enctype="multipart/form-data">
                                                @csrf
                                                <div class="upload-area" id="uploadArea">
                                                    <div class="mb-4">
                                                        <i class="fas fa-upload" style="font-size: 2rem; color: #667eea;"></i>
                                                        <h3 class="text-lg font-semibold text-gray-800 mt-2">Excel faylni bu yerga tashlang</h3>
                                                        <p class="text-gray-600">yoki tanlash uchun bosing</p>
                                                        <div class="text-sm text-gray-500 mt-2">
                                                            Qo'llab-quvvatlanadigan formatlar: .xlsx, .xls, .csv
                                                        </div>
                                                        <input type="file" id="fileInput" name="excel_file" accept=".xlsx,.xls,.csv" class="d-none">
                                                    </div>
                                                </div>

                                                <div id="fileInfo" class="mt-4 p-4 bg-gray-100 rounded-lg hidden">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="w-10 h-10 bg-green-500 rounded-lg d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-check text-white"></i>
                                                            </div>
                                                            <div>
                                                                <p id="fileName" class="text-gray-800 font-medium"></p>
                                                                <p id="fileSize" class="text-gray-600 text-sm"></p>
                                                            </div>
                                                        </div>
                                                        <button id="removeFile" type="button" class="text-red-500 hover:text-red-600 transition-colors">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="d-grid gap-2 mt-4">
                                                    <button id="uploadBtn" type="submit" class="btn btn-primary btn-lg py-3" disabled>
                                                        <i class="fas fa-upload me-2"></i>
                                                        <span id="btnText">Avval fayl tanlang</span>
                                                    </button>
                                                </div>

                                                <div id="progressContainer" class="mt-4 hidden">
                                                    <div class="d-flex justify-content-between text-sm text-gray-600 mb-2">
                                                        <span>Yuklanmoqda...</span>
                                                        <span id="progressPercent">0%</span>
                                                    </div>
                                                    <div class="w-100 bg-gray-200 rounded-full h-2">
                                                        <div id="progressBar" class="bg-gradient-to-r from-green-400 to-blue-500 h-2 rounded-full" style="width: 0%"></div>
                                                    </div>
                                                </div>

                                                <div id="successMessage" class="mt-4 p-4 bg-green-100 border border-green-300 rounded-lg text-green-600 text-center hidden">
                                                    <i class="fas fa-check-circle me-2"></i>
                                                    <span>Fayl muvaffaqiyatli yuklandi!</span>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                      <div class="card-body p-4">
                                        <form id="belarusSelect">
                                            @csrf
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


                                    <div class="row mt-4">
                                        <div class="col-md-6 mb-3">
                                            <div class="card info-card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title text-primary">
                                                        <i class="fas fa-search me-2"></i>Mintrans Avtoraqam Xususiyatlari
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Excel fayl orqali avtoraqam qidirish</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Transport ma'lumotlari</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Korxona ma'lumotlari</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Natijalarni avtomatik saqlash</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="card info-card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title text-primary">
                                                        <i class="fas fa-upload me-2"></i>Foydalanish Tartibi
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Excel faylni tanlang yoki tashlang</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>.xlsx, .xls, .csv formatlarini yuklang</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Yuklash jarayonini kuzating</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Natijalar avtomatik saqlanadi</li>
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
                                                            <li class="mb-2"><i class="fas fa-upload text-info me-2"></i>Fayl avtomatik tekshiriladi</li>
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
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadBtn = document.getElementById('uploadBtn');
    const btnText = document.getElementById('btnText');
    const removeFile = document.getElementById('removeFile');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    const successMessage = document.getElementById('successMessage');
    let selectedFile = null;
    let jobId = null;

    // Drag & Drop hodisalari
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });
    uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) handleFileSelect(e.dataTransfer.files[0]);
    });

    // Fayl input hodisasi
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) handleFileSelect(e.target.files[0]);
    });

    function handleFileSelect(file) {
        const validExt = ['.xlsx', '.xls', '.csv'];
        const ext = '.' + file.name.split('.').pop().toLowerCase();

        if (!validExt.includes(ext)) {
            alert('Faqat .xlsx, .xls, yoki .csv fayllar qabul qilinadi!');
            return;
        }

        selectedFile = file;
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.classList.remove('hidden');
        uploadBtn.disabled = false;
        btnText.textContent = 'Faylni Yuklash';
        uploadArea.classList.add('file-uploaded');
    }

    removeFile.addEventListener('click', () => {
        selectedFile = null;
        fileInput.value = '';
        fileInfo.classList.add('hidden');
        uploadBtn.disabled = true;
        btnText.textContent = 'Avval fayl tanlang';
        uploadArea.classList.remove('file-uploaded');
    });

    // Upload bosilganda APIga yuborish
    document.getElementById('uploadForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!selectedFile) return alert('Iltimos, fayl tanlang!');

        progressContainer.classList.remove('hidden');
        progressBar.style.width = '0%';
        progressPercent.textContent = '0%';
        btnText.textContent = 'Yuklanmoqda...';
        uploadBtn.disabled = true;

        const formData = new FormData();
        formData.append('excel_file', selectedFile);

        try {
            const res = await fetch('/api/upload', {
                method: 'POST',
                body: formData
            });

            if (!res.ok) throw new Error('Server bilan aloqa xatosi');
            const data = await res.json();

            jobId = data.job_id;
            btnText.textContent = 'Qayta ishlanmoqda...';

            // Progress animatsiya
            animateProgress(40);

            // Har 5 soniyada statusni tekshirish
            const interval = setInterval(async () => {
                const checkRes = await fetch(`/api/scrape/check-result/${jobId}`);
                const checkData = await checkRes.json();

                if (checkData.status === 'ready') {
                    clearInterval(interval);

                    animateProgress(100);

                    btnText.textContent = 'Yuklab olish tayyor!';
                    successMessage.classList.remove('hidden');

                    setTimeout(() => {
                        const a = document.createElement('a');
                        a.href = checkData.download_url;
                        a.download = '';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                    }, 1000);
                }
            }, 5000);

        } catch (err) {
            alert('❌ Xato: ' + err.message);
            btnText.textContent = 'Yana urinib ko‘ring';
        }
    });

    function animateProgress(target) {
        let current = parseInt(progressPercent.textContent);
        const step = () => {
            if (current < target) {
                current += 1;
                progressBar.style.width = current + '%';
                progressPercent.textContent = current + '%';
                requestAnimationFrame(step);
            }
        };
        requestAnimationFrame(step);
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        else return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Klikda fayl tanlash
    uploadArea.addEventListener('click', () => fileInput.click());
});
</script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
    const select = document.getElementById('region');
    const form = document.getElementById('belarusSelect');
    const status = document.getElementById('status');

    // --- 1. Fayllarni yuklab olish ---
    try {
        status.textContent = "Fayllar yuklanmoqda...";

        const response = await fetch("https://scrape.izisol.uz/api/scrape/all-files");
        const data = await response.json();

        select.innerHTML = '<option value="">-- Faylni tanlang --</option>';

        if (data.status && Array.isArray(data.files)) {
            data.files.forEach(file => {
                const option = document.createElement('option');
                option.value = file;
                option.textContent = file;
                select.appendChild(option);
            });
            status.textContent = "✅ Fayllar yuklandi";
        } else {
            status.textContent = "⚠️ Fayllar topilmadi";
        }

    } catch (error) {
        console.error(error);
        status.textContent = "❌ Fayllarni yuklashda xatolik";
    }

    // --- 2. Yuklab olish ---
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fileName = select.value;

        if (!fileName) {
            alert("Iltimos, faylni tanlang!");
            return;
        }

        status.textContent = "📦 Yuklab olinmoqda...";

        try {
            const response = await fetch("https://scrape.izisol.uz/api/scrape/download", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify({ file: fileName })
            });

            if (!response.ok) throw new Error("Server xatosi yoki fayl topilmadi");

            // Blob (binary data) olish
            const blob = await response.blob();

            // Faylni avtomatik yuklab olish
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = downloadUrl;
            a.download = fileName; // nomini avtomatik qo‘yadi
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(downloadUrl);

            status.textContent = "✅ Fayl muvaffaqiyatli yuklandi";

        } catch (error) {
            console.error(error);
            status.textContent = "❌ Yuklab olishda xatolik yuz berdi";
        }
    });
});
</script>

</body>
</html>