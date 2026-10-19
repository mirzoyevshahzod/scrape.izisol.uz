@extends('layouts.app')

@section('title', 'Mintrans Avtoraqam')

@section('content')
    <div class="page-header">
        <div>
            <h1>Mintrans Avtoraqam</h1>
            <p>Excel fayldagi avtoraqamlar bo'yicha Mintrans ma'lumotlarini avtomatik olish.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light" onclick="window.open('https://info.mintrans.uz/#/info/onSearch', '_blank')">
                <i class="fas fa-arrow-up-right-from-square me-1"></i>Saytga o'tish
            </button>
            <button type="button" class="btn btn-light" onclick="copyLink()">
                <i class="fas fa-copy me-1"></i>Linkni nusxalash
            </button>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card action-card h-100">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-file-arrow-up"></i></span>
                        <div>
                            <h2>Fayl yuklash</h2>
                            <p>Avtoraqamlar ro'yxati bor Excel faylni yuklang — natija tayyor bo'lgach avtomatik yuklab olinadi.</p>
                        </div>
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data">
                        @csrf
                        <div class="dropzone upload-area" id="uploadArea">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <div class="dropzone-file">Excel faylni bu yerga tashlang</div>
                            <div class="small">yoki tanlash uchun bosing · .xlsx, .xls, .csv</div>
                            <input type="file" id="fileInput" name="excel_file" accept=".xlsx,.xls,.csv" class="d-none">
                        </div>

                        <div id="fileInfo" class="file-chip mt-3 hidden">
                            <span class="icon-tile file-chip-icon"><i class="fas fa-file-excel"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <p id="fileName" class="fw-medium mb-0 text-truncate"></p>
                                <p id="fileSize" class="text-muted small mb-0"></p>
                            </div>
                            <button id="removeFile" type="button" class="btn btn-sm btn-light text-danger" title="Olib tashlash">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>

                        <button id="uploadBtn" type="submit" class="btn btn-primary w-100 py-2 mt-3" disabled>
                            <i class="fas fa-upload me-1"></i>
                            <span id="btnText">Avval fayl tanlang</span>
                        </button>

                        <div id="progressContainer" class="mt-3 hidden">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Yuklanmoqda...</span>
                                <span id="progressPercent">0%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div id="progressBar" class="progress-bar" style="width: 0%"></div>
                            </div>
                        </div>

                        <div id="successMessage" class="alert alert-success text-center mt-3 mb-0 hidden">
                            <i class="fas fa-circle-check me-2"></i>
                            <span>Fayl muvaffaqiyatli yuklandi!</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card action-card mb-4">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-file-arrow-down"></i></span>
                        <div>
                            <h2>Fayllarni yuklab olish</h2>
                            <p>Avval tayyorlangan natija fayllaridan birini tanlang.</p>
                        </div>
                    </div>

                    <form id="belarusSelect">
                        @csrf
                        <label for="region" class="form-label">Fayl</label>
                        <select name="region" id="region" class="form-select mb-3" required>
                            <option value="">-- Fayllar --</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary w-100 py-2" id="Download">
                            <i class="fas fa-download me-1" aria-hidden="true"></i>Yuklab olish
                        </button>
                    </form>
                    <div id="status" class="status-line"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-circle-info me-2 text-muted"></i>Qanday ishlaydi</div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-0 d-grid gap-2">
                        <li><i class="fas fa-check text-success me-2"></i>Excel faylni tanlang yoki tashlang (.xlsx, .xls, .csv)</li>
                        <li><i class="fas fa-check text-success me-2"></i>Har bir avtoraqam bo'yicha transport va korxona ma'lumotlari olinadi</li>
                        <li><i class="fas fa-check text-success me-2"></i>Jarayon Selenium orqali bajariladi va vaqt talab qiladi</li>
                        <li><i class="fas fa-check text-success me-2"></i>Natija tayyor bo'lgach fayl avtomatik yuklab olinadi</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        /* Tanlangan fayl haqida qator (JS #fileInfo'dan 'hidden' klassini olib tashlaydi) */
        .file-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid var(--ui-border);
            border-radius: 10px;
            background: var(--ui-surface-2);
        }

        .file-chip-icon {
            background: var(--bs-success-bg-subtle);
            color: var(--bs-success-text-emphasis);
        }

        .min-w-0 {
            min-width: 0;
        }
    </style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
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
@endpush
