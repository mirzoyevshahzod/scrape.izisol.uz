@extends('layouts.app')

@section('title', 'Belarus Litva')

@section('content')
    <div class="page-header">
        <div>
            <h1>Belarus Litva</h1>
            <p>Declarant chegara navbatlarini yig'ish va Excel fayllarni yuklab olish.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="https://mon.declarant.by/zone" target="_blank" rel="noopener" class="btn btn-light">
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
                            <p>Chegara hududini tanlang — ma'lumotlar yig'ilib, Excel fayl tayyorlanadi.</p>
                        </div>
                    </div>

                    <form id="belarusForm" class="mt-auto">
                        <label for="region" class="form-label">Chegara hududi</label>
                        <select name="region" id="region" class="form-select mb-3" required>
                            <option value="">-- Chegara hududini tanlang --</option>
                            <option value="benyakoni">Бенякони</option>
                            <option value="brest-bts">Брест</option>
                            <option value="grigorovschina">Григоровщина</option>
                            <option value="kamennii-log">Каменный Лог</option>
                            <option value="kozlovichi">Козловичи</option>
                        </select>
                        <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn">
                            <i class="fas fa-play me-1"></i>Ma'lumotlarni yig'ishni boshlash
                        </button>
                    </form>
                    <div id="status" class="status-line"></div>

                    <div class="loading" id="loadingIndicator">
                        <div class="spinner"></div>
                        <div class="loading-text">Ma'lumotlar yig'ilmoqda va Excel fayl tayyorlanmoqda...</div>
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
                            <p>Avval yig'ilgan Belarus fayllaridan birini tanlang.</p>
                        </div>
                    </div>

                    <form id="belarusSelect" class="mt-auto">
                        @csrf
                        <label for="region1" class="form-label">Fayl</label>
                        <select name="region" id="region1" class="form-select mb-3" required>
                            <option value="">-- Fayllar --</option>
                        </select>
                        <button type="submit" class="btn btn-outline-primary w-100 py-2" id="Download">
                            <i class="fas fa-download me-1" aria-hidden="true"></i>Yuklab olish
                        </button>
                    </form>
                    <div id="status1" class="status-line"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
<script>
                                                function copyLink() {
                                                    navigator.clipboard.writeText('https://mon.declarant.by/zone');

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

document
.getElementById('belarusForm')
.addEventListener('submit', async function(e) {

    e.preventDefault();

    const region =
        document.getElementById('region').value;

    const submitBtn =
        document.getElementById('submitBtn');

    const loading =
        document.getElementById('loadingIndicator');

    const status =
        document.getElementById('status');

    if (!region) {

        status.innerHTML =
            '❌ Chegara tanlanmadi';

        return;
    }

    submitBtn.disabled = true;

    loading.classList.add('show');

    status.innerHTML =
        '⏳ Scraping boshlandi...';

    try {

        const response = await fetch(
            `/api/scrape/${region}`,
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

        // backend filename
        const disposition =
            response.headers.get(
                'Content-Disposition'
            );

        let fileName =
            'belarus.xlsx';

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

        status.innerHTML =
            '✅ Excel yuklandi';

        // file list refresh
        loadFiles();

    } catch (error) {

        console.error(error);

        status.innerHTML =
            '❌ Xatolik yuz berdi';
    }

    submitBtn.disabled = false;

    loading.classList.remove('show');

});


// ================= FILE LIST =================

async function loadFiles() {

    const select =
        document.getElementById('region1');

    try {

        const response =
            await fetch('/api/scrape/files');

        const data =
            await response.json();

        select.innerHTML =
            '<option value="">-- Fayllar --</option>';

        if (
            data.success &&
            Array.isArray(data.files)
        ) {

            data.files.forEach(file => {

                const option =
                    document.createElement('option');

                option.value = file;

                option.textContent = file;

                select.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
}


// ================= DOWNLOAD SELECTED FILE =================

document
.getElementById('belarusSelect')
.addEventListener('submit', async function(e) {

    e.preventDefault();

    const file =
        document.getElementById('region1').value;

    const status =
        document.getElementById('status1');

    if (!file) {

        status.innerHTML =
            '❌ Fayl tanlanmadi';

        return;
    }

    status.innerHTML =
        '⏳ Yuklanmoqda...';

    try {

        const response = await fetch(
            `/api/scrape/download?file=${encodeURIComponent(file)}`
        );

        if (!response.ok) {

            throw new Error(
                'Fayl topilmadi'
            );
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

        status.innerHTML =
            '✅ Fayl yuklandi';

    } catch (error) {

        console.error(error);

        status.innerHTML =
            '❌ Xatolik yuz berdi';
    }

});


// ================= PAGE LOAD =================

document.addEventListener(
    'DOMContentLoaded',
    loadFiles
);



</script>
@endpush
