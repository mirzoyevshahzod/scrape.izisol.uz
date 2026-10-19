@extends('layouts.app')

@section('title', "O'zbek raqam filtri")

@section('content')
    <div class="page-header">
        <div>
            <h1>O'zbek raqam filtri</h1>
            <p>Excel fayldan faqat O'zbekiston davlat raqamlarini ajratib, Zanjeer'ga yuklash uchun tayyorlash.</p>
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
        <div class="col-lg-7 col-xl-6">
            <div class="card action-card h-100">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-filter"></i></span>
                        <div>
                            <h2>Faylni filterlash</h2>
                            <p>Excel faylni yuklang — faqat O'zbekiston raqamlari qolgan fayl avtomatik yuklab olinadi.</p>
                        </div>
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data">
                        @csrf
                        <div class="dropzone upload-area" id="uploadArea">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <div class="dropzone-file">Excel faylni tanlang</div>
                            <div class="small">tanlash uchun bosing · .xlsx, .xls, .csv</div>
                            <input type="file" id="fileInput" name="excel_file" accept=".xlsx,.xls,.csv" class="d-none">
                        </div>

                        <div id="fileInfo" class="file-chip mt-3 hidden">
                            <span class="icon-tile file-chip-icon"><i class="fas fa-file-excel"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <p id="fileName" class="fw-medium mb-0 text-truncate"></p>
                                <p id="fileSize" class="text-muted small mb-0"></p>
                            </div>
                        </div>

                        <button id="uploadBtn" type="submit" class="btn btn-primary w-100 py-2 mt-3" disabled>
                            <i class="fas fa-filter me-1"></i>
                            <span id="btnText">Avval fayl tanlang</span>
                        </button>
                    </form>
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
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const uploadBtn = document.getElementById('uploadBtn');
const btnText = document.getElementById('btnText');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');

uploadArea.onclick = () => fileInput.click();

fileInput.onchange = () => {

    if (!fileInput.files.length) return;

    const file = fileInput.files[0];

    fileName.innerText = file.name;
    fileSize.innerText = (file.size / 1024).toFixed(2) + " KB";

    fileInfo.classList.remove('hidden');

    uploadBtn.disabled = false;
    btnText.innerText = "Filterlash";
};

document.getElementById('uploadForm').addEventListener('submit', async function(e){

    e.preventDefault();

    if (!fileInput.files.length) {

        Swal.fire(
            'Xatolik',
            'Excel fayl tanlang!',
            'warning'
        );

        return;
    }

    uploadBtn.disabled = true;

    btnText.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Yuklanmoqda...';

    const formData = new FormData();

    formData.append(
        'excel_file',
        fileInput.files[0]
    );

    const response = await fetch('/api/filter-uz-plates', {
        method: 'POST',
        body: formData
    });

    if (!response.ok) {

        console.log(await response.text());

        Swal.fire(
            'Xatolik',
            'Filterlash amalga oshmadi',
            'error'
        );

        return;
    }

    const disposition = response.headers.get('Content-Disposition');

    let filename = 'uzbek_plates.xlsx';

    if (disposition) {

        const match = disposition.match(/filename="?([^"]+)"?/);

        if (match) {
            filename = match[1];
        }
    }

    const blob = await response.blob();

    const url = window.URL.createObjectURL(blob);

    const a = document.createElement('a');

    a.href = url;
    a.download = filename;

    document.body.appendChild(a);

    a.click();

    a.remove();

    window.URL.revokeObjectURL(url);

    uploadBtn.disabled = false;

    btnText.innerText = 'Filterlash';

    Swal.fire(
        'Tayyor!',
        'O\'zbekiston davlat raqamlari muvaffaqiyatli ajratildi.',
        'success'
    );
});
</script>
@endpush
