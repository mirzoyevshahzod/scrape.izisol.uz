@extends('layouts.app')

@section('title', 'Turkiya Gruziya')

@section('content')
    <div class="page-header">
        <div>
            <h1>Turkiya Gruziya</h1>
            <p>Hopa TIR parki navbat ma'lumotlarini avtomatik yig'ish va Excel fayllarni yuklab olish.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="https://www.hopatirparki.com/tirparki/arhavilimansiragumruklu.asp" target="_blank" rel="noopener" class="btn btn-light">
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
                            <p>Turkiya–Gruziya chegarasidagi joriy navbatni yig'ib, tayyor Excel faylni yuklab beradi.</p>
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
                            <p>Avval yig'ilgan Turkiya fayllaridan birini tanlang.</p>
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
                                                    navigator.clipboard.writeText('https://www.hopatirparki.com/tirparki/arhavilimansiragumruklu.asp');

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

// ================= SCRAPE + AUTO DOWNLOAD =================

document
.getElementById('scrapeForm')
.addEventListener('submit', async function(e) {

    e.preventDefault();

    const submitBtn =
        document.getElementById('submitBtn');

    const loading =
        document.getElementById('loadingIndicator');

    const statusDiv =
        document.getElementById('scrapeStatus');

    submitBtn.disabled = true;

    loading.classList.add('show');

    statusDiv.innerHTML =
        '⏳ Scraping boshlandi...';

    try {

        const response = await fetch(
            '{{ route("turkey.scrape") }}',
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content
                }
            }
        );

        if (!response.ok) {

            throw new Error(
                'Excel yaratilmadi'
            );
        }

        // ===== FILE DOWNLOAD =====

        const blob =
            await response.blob();

        const url =
            window.URL.createObjectURL(blob);

        const a =
            document.createElement('a');

        a.href = url;

        // backenddagi filename
        const disposition =
            response.headers.get(
                'Content-Disposition'
            );

        let fileName =
            'turkey.xlsx';

        if (
            disposition &&
            disposition.includes('filename=')
        ) {

            fileName =
                disposition
                    .split('filename=')[1]
                    .replace(/"/g, '');
        }

        a.download = fileName;

        document.body.appendChild(a);

        a.click();

        a.remove();

        window.URL.revokeObjectURL(url);

        statusDiv.innerHTML =
            '✅ Excel muvaffaqiyatli yuklandi';

        // 🔄 FILE LIST REFRESH
        loadFiles();

    } catch (error) {

        console.error(error);

        statusDiv.innerHTML =
            '❌ Xatolik yuz berdi';
    }

    submitBtn.disabled = false;

    loading.classList.remove('show');

});

// ================= FILE LIST =================

async function loadFiles() {

    const fileSelect =
        document.getElementById('region');

    try {

        const response = await fetch(
            '{{ route("turkey.files") }}'
        );

        const data =
            await response.json();

        fileSelect.innerHTML =
            '<option value="">-- Fayllar --</option>';

        if (
            data.status &&
            Array.isArray(data.files)
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

// ================= MANUAL DOWNLOAD =================

document
.getElementById('belarusSelect')
.addEventListener('submit', function(e) {

    e.preventDefault();

    const select =
        document.getElementById('region');

    const status =
        document.getElementById('status');

    if (!select.value) {

        status.innerHTML =
            '❌ Fayl tanlanmadi';

        return;
    }

    status.innerHTML =
        '📦 Yuklab olinmoqda...';

    window.location.href =
        `/api/turkey/download/${
            encodeURIComponent(select.value)
        }`;

});

// PAGE LOAD
document.addEventListener(
    'DOMContentLoaded',
    loadFiles
);

</script>
@endpush
