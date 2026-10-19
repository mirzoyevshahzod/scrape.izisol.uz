@extends('layouts.app')

@section('title', 'Zanjeer')

@section('content')
    <div class="page-header">
        <div>
            <h1>Zanjeer</h1>
            <p>Birlashtirilgan fayllarga Zanjeer CRM operatorlarini qo'shish va scraping fayllarini birlashtirish.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="https://crm.zanjeer.uz" target="_blank" rel="noopener" class="btn btn-light">
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
                        <span class="icon-tile"><i class="fas fa-user-tie"></i></span>
                        <div>
                            <h2>Operatorlarni qo'shish</h2>
                            <p>Birlashtirilgan faylni tanlang — har bir kompaniya uchun Zanjeer operatori fonda qidiriladi, tugagach natija avtomatik yuklab olinadi.</p>
                        </div>
                    </div>

                    <form id="scrapeForm" enctype="multipart/form-data" class="mt-auto">
                        <label for="merge5" class="form-label">Birlashtirilgan fayl</label>
                        <select name="merge5" id="merge5" class="form-select mb-3">
                            <option value="">-- Birlashtirilgan Fayllar --</option>
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
                            <h2>Natijani yuklab olish</h2>
                            <p>Operatorlar qo'shilgan fayllardan birini tanlang.</p>
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

        <div class="col-12">
            <div class="card action-card">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-object-group"></i></span>
                        <div>
                            <h2>Fayllarni birlashtirish</h2>
                            <p>Har bir manbadan bir yoki bir nechta faylni tanlang (Ctrl yoki ⌘ bosib turib) — ular bitta umumiy faylga birlashtiriladi.</p>
                        </div>
                    </div>

                    <form>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="merge1" class="form-label">Qozoq tekshirilgan</label>
                                <select name="merge1" id="merge1" class="form-select" multiple size="7">
                                    <option value="">-- Qozoq Scraping Fayllar --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="merge2" class="form-label">Belarus</label>
                                <select name="merge2" id="merge2" class="form-select" multiple size="7">
                                    <option value="">-- Belarus Scraping Faylllar --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="merge3" class="form-label">Zitic</label>
                                <select name="merge3" id="merge3" class="form-select" multiple size="7">
                                    <option value="">-- Zitic Scraping Fayllar --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="merge4" class="form-label">Arhavi (Turkiya)</label>
                                <select name="merge4" id="merge4" class="form-select" multiple size="7">
                                    <option value="">-- Arhavi Scraping Fayllar --</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary py-2 px-4" id="Merge">
                            <i class="fas fa-download me-2" aria-hidden="true"></i>Birlashtirish
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
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
@endpush
