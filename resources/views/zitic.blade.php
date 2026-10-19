@extends('layouts.app')

@section('title', 'Zitic')

@section('content')
    <div class="page-header">
        <div>
            <h1>Zitic</h1>
            <p>Zitic.ru navbat ma'lumotlarini avtomatik yig'ish va Excel fayllarni yuklab olish.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="https://zitic.ru/eo/vl/?utm_source=chatgpt.com" target="_blank" rel="noopener" class="btn btn-light">
                <i class="fas fa-arrow-up-right-from-square me-1"></i>Saytga o'tish
            </a>
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
                            <p>Zitic.ru'dan joriy navbatni yig'ib, tayyor Excel faylni darhol yuklab beradi.</p>
                        </div>
                    </div>

                    <form id="scrapeForm" class="mt-auto">
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
                            <p>Avval yig'ilgan Zitic fayllaridan birini tanlang.</p>
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
                    <div id="status" class="status-line"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
<script>
    function copyLink() {
        navigator.clipboard.writeText('https://zitic.ru/eo/vl/?utm_source=chatgpt.com');

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

document.getElementById('scrapeForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    const button = document.getElementById('submitBtn');
    const loading = document.getElementById('loadingIndicator');
    const status = document.getElementById('scrapeStatus');

    button.disabled = true;

    loading.classList.add('show');

    status.innerHTML = '⏳ Scraping boshlandi...';

    try {

        const response = await fetch('/api/scrape-zitic', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            }
        });

        if (!response.ok) {
            throw new Error('Excel yaratilmadi');
        }

        // ===== FILE DOWNLOAD =====
        const blob = await response.blob();

        const url = window.URL.createObjectURL(blob);

        const a = document.createElement('a');

        a.href = url;

        // backenddagi filename
        const disposition = response.headers.get('Content-Disposition');

        let fileName = 'zitic.xlsx';

        if (disposition && disposition.includes('filename=')) {
            fileName = disposition
                .split('filename=')[1]
                .replace(/"/g, '');
        }

        a.download = fileName;

        document.body.appendChild(a);

        a.click();

        a.remove();

        window.URL.revokeObjectURL(url);

        status.innerHTML = '✅ Excel muvaffaqiyatli yuklandi';

    } catch (error) {

        console.error(error);

        status.innerHTML = '❌ Xatolik yuz berdi';

    } finally {

        button.disabled = false;

        loading.classList.remove('show');
    }

});


// ================= FILE LIST =================

document.addEventListener('DOMContentLoaded', async () => {

    const select = document.getElementById('region');

    try {

        const response = await fetch('/api/zitic/files');

        const data = await response.json();

        if (data.status) {

            data.files.forEach(file => {

                const option = document.createElement('option');

                option.value = file;
                option.textContent = file;

                select.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }

});

// ================= DOWNLOAD =================

document.getElementById('belarusSelect').addEventListener('submit', async function (e) {

    e.preventDefault();

    const file = document.getElementById('region').value;

    const status = document.getElementById('status');

    if (!file) {

        alert('Fayl tanlang!');

        return;
    }

    status.innerHTML = '⏳ Yuklanmoqda...';

    try {

        const response = await fetch(`/api/zitic/download/${encodeURIComponent(file)}`);

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
