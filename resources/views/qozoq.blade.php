@extends('layouts.app')

@section('title', 'Qozoq Scraper')

@section('content')
    <div class="page-header">
        <div>
            <h1>Qozoq Scraper</h1>
            <p>Qoldau checkpoint'lari bo'yicha navbatdagi mashinalarni yig'ish va Excel fayllarni yuklab olish.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light" onclick="window.open('https://cgr.qoldau.kz/ru/start', '_blank')">
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
        <div class="col-lg-6">
            <div class="card action-card h-100">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-rocket"></i></span>
                        <div>
                            <h2>Scraping</h2>
                            <p>Chegara punktini tanlang — joriy navbat yig'ilib, Excel fayl tayyorlanadi.</p>
                        </div>
                    </div>

                    <form id="scrapeForm" class="mt-auto">
                        <label for="checkpoint" class="form-label">Chegara punkti</label>
                        <select name="checkpoint" id="checkpoint" class="form-select mb-3" required>
                            <option value="">-- Chegara punktlari --</option>
                            @foreach($checkpoints as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn">
                            <i class="fas fa-play me-1"></i>Scrapingni boshlash
                        </button>
                        <div id="scrapeStatus" class="status-line"></div>
                    </form>

                    <div class="loading" id="loadingIndicator">
                        <div class="spinner"></div>
                        <div class="loading-text">Ma'lumotlar qayta ishlanmoqda...</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card action-card h-100">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-file-arrow-down"></i></span>
                        <div>
                            <h2>Fayllarni yuklab olish</h2>
                            <p>Avval yig'ilgan Qozoq fayllaridan birini tanlang.</p>
                        </div>
                    </div>

                    <form id="belarusSelect" class="mt-auto">
                        <label for="region" class="form-label">Fayl</label>
                        <select name="region" id="region" class="form-select mb-3" required>
                            <option value="">-- Fayllar --</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary w-100 py-2" id="Download">
                            <i class="fas fa-download me-1" aria-hidden="true"></i>Yuklab olish
                        </button>
                    </form>
                    <div id="downloadStatus" class="status-line"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
<script>
        function copyLink() {
            navigator.clipboard.writeText('https://cgr.qoldau.kz/ru/start');

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

function slugify(text) {
    return text
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .trim();
}

document.getElementById('scrapeForm')
.addEventListener('submit', async function(e) {

    e.preventDefault();

    const checkpoint =
        document.getElementById('checkpoint').value;

    const checkpointText =
        document.getElementById('checkpoint')
        .options[
            document.getElementById('checkpoint').selectedIndex
        ].text;

    const loading =
        document.getElementById('loadingIndicator');

    const status =
        document.getElementById('scrapeStatus');

    const btn =
        document.getElementById('submitBtn');

    if (!checkpoint) {
        alert('Checkpoint tanlang!');
        return;
    }

    loading.classList.add('show');

    btn.disabled = true;

    status.innerHTML =
        '⏳ Queuega yuborildi...';

    try {

        // JOB DISPATCH
        const response = await fetch(
            `/api/qozoq/scrape/${encodeURIComponent(checkpoint)}`
        );

        const data = await response.json();

        if (!data.status) {
            throw new Error();
        }

        // FILE NAME
        let fileName;

        const groups = [
            'Kazakhstan - Russia',
            'Kazakhstan - Uzbekistan',
            'Kazakhstan - Turkmenistan',
            'Kazakhstan - China',
            'Kazakhstan - Kyrgyzstan',
            'Kazakhstan'
        ];

        if (groups.includes(checkpoint)) {

            fileName =
                slugify(checkpoint);

        } else {

            fileName =
                slugify(checkpointText);
        }

     status.innerHTML =
    '⏳ Scraping davom etmoqda...';

// FILE YARATILGAN VAQTNI TEKSHIRISH UCHUN
const scrapeStartedAt = Date.now();

// POLLING
const interval = setInterval(async () => {

    try {

        const filesResponse =
            await fetch('/api/qozoq/files');

        const filesData =
            await filesResponse.json();

        if (!filesData.status) {
            return;
        }

        const found = filesData.files.find(f => {

            // FILE NOMI MOSMI
            if (!f.startsWith(fileName)) {
                return false;
            }

            // DATENI AJRATIB OLISH
            const match = f.match(
                /(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})/
            );

            if (!match) {
                return false;
            }

            // FILE VAQTI
            const fileDate =
                `${match[1]} ${match[2].replace(/-/g, ':')}`;

            // FAQAT YANGI FILE
            return new Date(fileDate).getTime()
                >= scrapeStartedAt;
        });

        if (found) {

            clearInterval(interval);

            status.innerHTML =
                '✅ Tayyor! Fayl yuklanmoqda...';

            // AUTO DOWNLOAD
            window.location.href =
                `/api/qozoq/download/${encodeURIComponent(found)}`;

            loading.classList.remove('show');

            btn.disabled = false;
        }

    } catch (err) {
        console.error(err);
    }

}, 5000);

    } catch (error) {
        console.error(error);
        status.innerHTML =
            '❌ Xatolik yuz berdi';
        loading.classList.remove('show');
        btn.disabled = false;
    }
});
document.addEventListener('DOMContentLoaded', async () => {
    const regionSelect = document.getElementById('region');

    try {
        const response = await fetch('/api/qozoq/files');
        const data = await response.json();

        if (data.status) {
            data.files.forEach(file => {
                const option = document.createElement('option');
                option.value = file;
                option.textContent = file;

                regionSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error(error);
    }
});

document.getElementById('belarusSelect').addEventListener('submit', async function(e) {
    e.preventDefault();

    const file = document.getElementById('region').value;
    const status = document.getElementById('downloadStatus');

    if (!file) {
        alert('Fayl tanlang!');
        return;
    }

    status.innerHTML = '⏳ Yuklanmoqda...';

    try {
        const response = await fetch(`/api/qozoq/download/${encodeURIComponent(file)}`);

        if (!response.ok) {
            throw new Error('Fayl topilmadi');
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = file;

        document.body.appendChild(a);
        a.click();
        a.remove();

        window.URL.revokeObjectURL(url);

        status.innerHTML = '✅ Fayl yuklab olindi';
    } catch (error) {
        console.error(error);
        status.innerHTML = '❌ Xatolik yuz berdi';
    }
});
</script>
@endpush
