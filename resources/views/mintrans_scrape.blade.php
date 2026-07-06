<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mintrans Litzenziya Scraper</title>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="main-content">
                    <h1 class="header-title">
                        <i class="fas fa-database me-2"></i>
                        Mintrans Litzenziya Scraper
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-database" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;">Mintrans Litzenziya Scraper</h2>
                                        <p class="page-subtitle">Mintrans litsenziya raqamlari bo'yicha ma'lumotlarni avtomatik olish tizimi</p>
                                    </div>
                                    
                                    @if(session(key: 'error'))
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
                                            <form id="logisticsForm">
                                                @csrf
                                                <div class="row">
                                                    <div class="col-md-6 mb-4">
                                                        <label for="id" class="form-label fw-bold">
                                                            <i class="fas fa-play me-2 text-success"></i>Litsenziya varaqasi raqami:
                                                        </label>
                                                        <input 
                                                            type="text" 
                                                            id="id" 
                                                            name="id" 
                                                            class="form-control form-control-lg" 
                                                            required 
                                                            placeholder="9123456"
                                                            pattern="\d{7,8,9}"
                                                            value="{{ old('id') }}">
                                                        <div class="form-text">
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Format: 7 yoki 8 ta raqam (masalan: 9123456 yoki 91234567)
                                                        </div>
                                                        <div class="invalid-feedback" id="id_error">
                                                            Iltimos, 7 yoki 8 ta raqamdan iborat ID kiriting
                                                        </div>
                                                        @error('id')
                                                            <div class="text-danger mt-1">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="col-md-6 mb-4">
                                                        <label for="count" class="form-label fw-bold">
                                                            <i class="fas fa-hashtag me-2 text-warning"></i>Soni:
                                                        </label>
                                                        <input 
                                                            type="number" 
                                                            id="count" 
                                                            name="count" 
                                                            class="form-control form-control-lg" 
                                                            required 
                                                            min="1" 
                                                            placeholder="Nechta ma'lumot olish kerak?"
                                                            value="{{ old('count') }}">
                                                        <div class="form-text">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                                            Maksimal: 100000
                                                        </div>
                                                        <div class="invalid-feedback" id="count_error">
                                                            Iltimos, 1 dan yuqori son kiriting
                                                        </div>
                                                        @error('count')
                                                            <div class="text-danger mt-1">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="d-grid gap-2">
                                                    <button type="submit" class="btn btn-primary btn-lg py-3" id="submitBtn">
                                                        <i class="fas fa-rocket me-2"></i>
                                                        Scrapingni Boshlash
                                                        <i class="fas fa-arrow-right ms-2"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <div id="progressContainer" class="mt-4 d-none">

                                    <div class="text-center mb-3">
                                        <div class="spinner-border text-primary" role="status"></div>
                                        <p class="mt-2">Scraping davom etmoqda...</p>
                                    </div>

                                    <div class="progress" style="height: 25px;">
                                        <div id="progressBar"
                                            class="progress-bar progress-bar-striped progress-bar-animated"
                                            role="progressbar"
                                            style="width: 0%">
                                            0%
                                        </div>
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
                                                        <i class="fas fa-database me-2"></i>Mintrans Xususiyatlari
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Litsenziya raqami bo'yicha qidirish</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Transport ma'lumotlari</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Korxona ma'lumotlari</li>
                                                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Faoliyat turi va holati</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="card info-card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title text-primary">
                                                        <i class="fas fa-truck me-2"></i>Foydalanish Tartibi
                                                    </h5>
                                                    <ul class="list-unstyled mb-0">
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Boshlang'ich litsenziya raqamini kiriting</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Kerakli ma'lumotlar sonini belgilang</li>
                                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Excel fayl avtomatik yuklanadi</li>
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
document.getElementById('logisticsForm').addEventListener('submit', async function(e) {

    e.preventDefault();

    const formData = new FormData(this);

    const status = document.getElementById('status');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');

    status.innerText = "⏳ Scraping boshlandi...";
    progressContainer.classList.remove('d-none');

    let percent = 0;

    const fakeProgress = setInterval(() => {

        if (percent < 90) {
            percent += Math.random() * 5;
            percent = Math.min(percent, 90);

            progressBar.style.width = percent + "%";
            progressBar.innerText = Math.floor(percent) + "%";
        }

    }, 1000);

    const res = await fetch('/api/mintrans/submit', {
        method: 'POST',
        body: formData
    });

    const data = await res.json();
    const jobId = data.job_id;

    const interval = setInterval(async () => {

        const check = await fetch(`/api/mintrans/check/${jobId}`);
        const result = await check.json();

        if(result.status === 'ready'){

            clearInterval(interval);
            clearInterval(fakeProgress);

            progressBar.style.width = "100%";
            progressBar.innerText = "100%";

            status.innerText = "✅ Tayyor! Fayl yuklanmoqda...";

            const a = document.createElement('a');
            a.href = result.download_url;
            a.click();
        }

    },5000);

});
document.addEventListener('DOMContentLoaded', async () => {
    const select = document.getElementById('region');
    const form = document.getElementById('belarusSelect');
    const status = document.getElementById('status');

    // --- 1. Fayllarni yuklab olish ---
    try {
        status.textContent = "Fayllar yuklanmoqda...";

        const response = await fetch("https://scrape.izisol.uz/api/mintrans/all-files");
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
            const response = await fetch("https://scrape.izisol.uz/api/mintrans/download", {
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