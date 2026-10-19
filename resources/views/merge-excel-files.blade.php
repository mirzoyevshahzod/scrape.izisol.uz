@extends('layouts.app')

@section('title', 'Excel birlashtirish')

@push('styles')
    <style>
        /* JS ro'yxatga qo'shadigan tanlangan fayllar */
        #fileList .list-group-item {
            background: var(--ui-surface);
            border-color: var(--ui-border);
            color: var(--ui-text);
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Excel birlashtirish</h1>
            <p>Bir xil formatdagi bir nechta Excel faylni bitta faylga birlashtirish.</p>
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
        <div class="col-lg-8">
            <div class="card action-card">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-object-group"></i></span>
                        <div>
                            <h2>Fayllarni birlashtirish</h2>
                            <p>Bir xil formatdagi fayllarni tanlang (masalan, 10 ta) — ular bitta Excel faylga birlashtirilib, yuklab olinadi.</p>
                        </div>
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data">
                        @csrf

                        <div class="upload-area dropzone" id="uploadArea">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <div class="dropzone-file">Excel fayllarni bu yerga tashlang</div>
                            <div class="small">yoki tanlash uchun bosing · .xlsx, .xls, .csv</div>
                            <div class="small">Diqqat: barcha fayllar formati bir xil bo'lishi kerak</div>
                            <input type="file" id="fileInput" name="excel_files[]"
                                   accept=".xlsx,.xls,.csv" class="d-none" multiple>
                        </div>

                        <div id="fileInfo" class="mt-3 p-3 bg-body-tertiary rounded-3 d-none">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="icon-tile" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                    <i class="fas fa-check"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold">Tanlangan fayllar: <span id="fileCount">0</span> ta</div>
                                    <div class="text-muted small">Barcha fayllar bitta Excel faylga birlashtiriladi</div>
                                </div>
                            </div>
                            <div id="fileList" class="list-group"></div>
                        </div>

                        <button id="uploadBtn" type="submit" class="btn btn-primary w-100 py-2 mt-3" disabled>
                            <i class="fas fa-download me-1"></i>
                            <span id="btnText">Avval fayl tanlang</span>
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
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileList = document.getElementById('fileList');
        const fileCount = document.getElementById('fileCount');
        const uploadBtn = document.getElementById('uploadBtn');
        const btnText = document.getElementById('btnText');


        // Upload area bosilganda file tanlash
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });


        // Drag over
        uploadArea.addEventListener('dragover', function (e) {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });


        // Drag leave
        uploadArea.addEventListener('dragleave', function () {
            uploadArea.classList.remove('drag-over');
        });


        // Drop
        uploadArea.addEventListener('drop', function (e) {
            e.preventDefault();

            uploadArea.classList.remove('drag-over');

            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;

                showFiles();
            }
        });


        // File tanlanganda
        fileInput.addEventListener('change', showFiles);


        // Fayllarni ko'rsatish
        function showFiles() {

            const files = fileInput.files;

            if (!files.length) {
                fileInfo.classList.add('d-none');
                uploadBtn.disabled = true;
                btnText.innerHTML = "Avval fayl tanlang";

                return;
            }

            fileInfo.classList.remove('d-none');
            uploadArea.classList.add('file-uploaded');

            fileList.innerHTML = '';

            fileCount.innerText = files.length;


            Array.from(files).forEach((file, index) => {

                const size = (file.size / 1024 / 1024).toFixed(2);

                const item = document.createElement('div');

                item.className =
                    'list-group-item d-flex align-items-center justify-content-between';

                item.innerHTML = `
            <div class="d-flex align-items-center">

                <div class="me-3">
                    <i class="fas fa-file-excel text-success fa-lg"></i>
                </div>

                <div>
                    <div class="fw-semibold">
                        ${index + 1}. ${file.name}
                    </div>

                    <div class="text-muted small">
                        ${size} MB
                    </div>
                </div>

            </div>

            <i class="fas fa-check text-success"></i>
        `;

                fileList.appendChild(item);
            });


            uploadBtn.disabled = false;

            btnText.innerHTML =
                `${files.length} ta faylni birlashtirish va yuklab olish`;
        }


        // Form submit
        document
            .getElementById('uploadForm')
            .addEventListener('submit', async function (e) {

                e.preventDefault();

                if (!fileInput.files.length) {
                    return;
                }

                uploadBtn.disabled = true;

                btnText.innerHTML =
                    '<i class="fas fa-spinner fa-spin me-2"></i> Birlashtirilmoqda...';


                const formData = new FormData();


                // Barcha fayllarni qo'shamiz
                Array.from(fileInput.files).forEach(file => {

                    formData.append(
                        'excel_files[]',
                        file
                    );

                });


                try {

                    const response = await fetch(
                        '/api/merge-excel-files',
                        {
                            method: 'POST',

                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },

                            body: formData
                        }
                    );


                    if (!response.ok) {

                        const text = await response.text();

                        console.error('SERVER ERROR:', text);

                        Swal.fire({
                            icon: 'error',
                            title: 'Server xatosi',
                            html: `
                                <pre style="
                                    text-align:left;
                                    white-space:pre-wrap;
                                    max-height:400px;
                                    overflow:auto;
                                ">${text}</pre>
                            `
                        });

                        return;
                    }


                    // Excel faylni olish
                    const blob = await response.blob();

                    const url =
                        window.URL.createObjectURL(blob);


                    const a =
                        document.createElement('a');

                    a.href = url;


                    // Serverdan filename olish
                    const disposition =
                        response.headers.get('Content-Disposition');


                    let filename =
                        'merged.xlsx';


                    if (disposition) {

                        const match =
                            disposition.match(
                                /filename="?([^"]+)"?/
                            );

                        if (match) {
                            filename = match[1];
                        }
                    }


                    a.download = filename;

                    document.body.appendChild(a);

                    a.click();

                    a.remove();


                    window.URL.revokeObjectURL(url);


                    Swal.fire({
                        icon: 'success',
                        title: 'Tayyor',
                        text:
                            `${fileInput.files.length} ta Excel fayl muvaffaqiyatli birlashtirildi`
                    });


                } catch (e) {

                    Swal.fire({
                        icon: 'error',
                        title: 'Server xatosi',
                        text: e.message
                    });


                } finally {

                    uploadBtn.disabled = false;

                    btnText.innerHTML =
                        'Birlashtirish va yuklab olish';
                }

            });
    </script>
@endpush
