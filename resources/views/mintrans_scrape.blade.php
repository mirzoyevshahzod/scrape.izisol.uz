@extends('layouts.app')

@section('title', 'Mintrans Litsenziya')

@section('content')
    <div class="page-header">
        <div>
            <h1>Mintrans Litsenziya</h1>
            <p>Litsenziya varaqasi raqamlari bo'yicha Mintrans ma'lumotlarini ketma-ket yig'ish.</p>
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

    @if (session(key: 'error'))
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
                        <span class="icon-tile"><i class="fas fa-rocket"></i></span>
                        <div>
                            <h2>Scraping</h2>
                            <p>Boshlang'ich litsenziya raqami va nechta ma'lumot olinishini kiriting — tayyor Excel avtomatik yuklab olinadi.</p>
                        </div>
                    </div>

                    <form id="logisticsForm">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="id" class="form-label">Litsenziya varaqasi raqami</label>
                                <input
                                    type="text"
                                    id="id"
                                    name="id"
                                    class="form-control"
                                    required
                                    placeholder="9123456"
                                    pattern="\d{7,8,9}"
                                    value="{{ old('id') }}">
                                <div class="form-text">7 yoki 8 ta raqam (masalan: 9123456 yoki 91234567)</div>
                                <div class="invalid-feedback" id="id_error">
                                    Iltimos, 7 yoki 8 ta raqamdan iborat ID kiriting
                                </div>
                                @error('id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="count" class="form-label">Soni</label>
                                <input
                                    type="number"
                                    id="count"
                                    name="count"
                                    class="form-control"
                                    required
                                    min="1"
                                    placeholder="Nechta ma'lumot olish kerak?"
                                    value="{{ old('count') }}">
                                <div class="form-text">Maksimal: 100000</div>
                                <div class="invalid-feedback" id="count_error">
                                    Iltimos, 1 dan yuqori son kiriting
                                </div>
                                @error('count')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn">
                            <i class="fas fa-play me-1"></i>Scrapingni boshlash
                        </button>
                    </form>

                    <div id="progressContainer" class="d-none">
                        <div class="d-flex align-items-center gap-2 small text-muted mb-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            Scraping davom etmoqda...
                        </div>
                        <div class="progress" style="height: 18px;">
                            <div id="progressBar"
                                class="progress-bar progress-bar-striped progress-bar-animated"
                                role="progressbar"
                                style="width: 0%">
                                0%
                            </div>
                        </div>
                    </div>
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
                            <p>Avval yig'ilgan litsenziya fayllaridan birini tanlang.</p>
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
                        <li><i class="fas fa-check text-success me-2"></i>Boshlang'ich litsenziya raqamidan ketma-ket qidiriladi</li>
                        <li><i class="fas fa-check text-success me-2"></i>Transport, korxona, faoliyat turi va holati olinadi</li>
                        <li><i class="fas fa-check text-success me-2"></i>Jarayon Selenium orqali bajariladi va vaqt talab qiladi</li>
                        <li><i class="fas fa-check text-success me-2"></i>Tayyor Excel fayl avtomatik yuklab olinadi</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

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
@endpush
