@extends('layouts.app')

@section('title', 'Orginfo')

@section('content')
    <div class="page-header">
        <div>
            <h1>Orginfo</h1>
            <p>Zanjeer fayllaridagi kompaniyalarga orginfo.uz'dan INN qo'shish va tayyor fayllarni yuklab olish.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light" onclick="window.open('https://orginfo.uz/', '_blank')">
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
                        <span class="icon-tile"><i class="fas fa-building"></i></span>
                        <div>
                            <h2>INN'larni olish</h2>
                            <p>Zanjeer faylini tanlang — kompaniyalar INN'i orginfo.uz'dan qo'shiladi va tayyor Excel avtomatik yuklanadi.</p>
                        </div>
                    </div>

                    <form id="scrapeForm" class="mt-auto">
                        <label for="inn" class="form-label">Zanjeer fayli</label>
                        <select name="region" id="inn" class="form-select mb-3" required>
                            <option value="">--Zanjeer Fayllar --</option>
                        </select>
                        <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn">
                            <i class="fas fa-play me-1"></i>INN'larni olish
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
                            <h2>Tayyor fayllarni yuklab olish</h2>
                            <p>INN qo'shilgan fayllardan birini tanlang.</p>
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
@endpush
