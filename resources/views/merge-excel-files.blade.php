<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merge Excel Files</title>
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
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

        .form-control:focus,
        .form-select:focus {
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

        .upload-area {
            border: 2px dashed rgba(102, 126, 234, 0.3);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .drag-over {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        .file-uploaded {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
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
                        <i class="fas fa-object-group me-2"></i>
                        Excel Fayllarni Birlashtirish
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-object-group"
                                            style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;">Excel Fayllarni Birlashtirish
                                        </h2>
                                        <p class="page-subtitle">Bir xil formatdagi bir nechta excel faylni bitta
                                            excelga birlashtirish</p>
                                    </div>

                                    @if(session('error'))
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
                                                Fayl Yuklash Parametrlari
                                            </h5>
                                        </div>
                                        <div class="card-body p-4">
                                            <form id="uploadForm" enctype="multipart/form-data">
                                                @csrf
                                                <div class="upload-area" id="uploadArea">
                                                    <div class="mb-4">
                                                        <i class="fas fa-upload"
                                                            style="font-size: 2rem; color: #667eea;"></i>
                                                        <h3 class="text-lg font-semibold text-gray-800 mt-2">
                                                            Excel fayllarni bu yerga tashlang
                                                        </h3>

                                                        <p class="text-gray-600">
                                                            yoki tanlash uchun bosing
                                                        </p>

                                                        <div class="text-sm text-gray-500 mt-2">
                                                            Bir vaqtning o'zida bir nechta fayl tanlashingiz mumkin
                                                            (masalan, 10 ta)
                                                        </div>

                                                        <div class="text-sm text-gray-500 mt-2">
                                                            Diqqat: barcha fayllar formati bir xil bo'lishi kerak
                                                        </div>

                                                        <div class="text-sm text-gray-500 mt-2">
                                                            Qo'llab-quvvatlanadigan formatlar:
                                                            .xlsx, .xls, .csv
                                                        </div>
                                                        <input type="file" id="fileInput" name="excel_files[]"
                                                            accept=".xlsx,.xls,.csv" class="d-none" multiple>
                                                    </div>
                                                </div>

                                                <div id="fileInfo" class="mt-4 p-4 bg-light rounded-lg d-none">

                                                    <div class="d-flex align-items-center mb-3">

                                                        <div class="bg-success rounded-lg d-flex align-items-center justify-content-center"
                                                            style="width: 40px; height: 40px;">
                                                            <i class="fas fa-check text-white"></i>
                                                        </div>

                                                        <div class="ms-3">
                                                            <div class="fw-bold">
                                                                Tanlangan fayllar:
                                                                <span id="fileCount">0</span> ta
                                                            </div>

                                                            <div class="text-muted small">
                                                                Barcha fayllar bitta Excel faylga birlashtiriladi
                                                            </div>
                                                        </div>

                                                    </div>

                                                    <div id="fileList" class="list-group"></div>

                                                </div>

                                                <div class="d-grid gap-2 mt-4">
                                                    <button id="uploadBtn" type="submit"
                                                        class="btn btn-primary btn-lg py-3" disabled>
                                                        <i class="fas fa-upload me-2"></i>
                                                        <span id="btnText">Avval fayl tanlang</span>
                                                    </button>
                                                </div>
                                            </form>
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
</body>

</html>
