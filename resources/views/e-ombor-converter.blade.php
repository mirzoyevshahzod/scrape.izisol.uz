<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E Ombor Files</title>
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
        .form-control:focus, .form-select:focus {
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
                        <i class="fas fa-search me-2"></i>
                        E Ombor fayllarini Formatlash
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-search" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;"> E Ombor fayllarini Formatlash</h2>
                                        <p class="page-subtitle">E ombor fayllarini zanjeerga yuklash uchun tayyorlash</p>
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
                                                        <i class="fas fa-upload" style="font-size: 2rem; color: #667eea;"></i>
                                                        <h3 class="text-lg font-semibold text-gray-800 mt-2">Excel faylni bu yerga tashlang</h3>
                                                        <p class="text-gray-600">yoki tanlash uchun bosing</p>
                                                        <div class="text-sm text-gray-500 mt-2">
                                                            Qo'llab-quvvatlanadigan formatlar: .xlsx, .xls, .csv
                                                        </div>
                                                        <input type="file" id="fileInput" name="excel_file" accept=".xlsx,.xls,.csv" class="d-none">
                                                    </div>
                                                </div>

                                                <div id="fileInfo" class="mt-4 p-4 bg-gray-100 rounded-lg hidden">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="w-10 h-10 bg-green-500 rounded-lg d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-check text-white"></i>
                                                            </div>
                                                            <div>
                                                                <p id="fileName" class="text-gray-800 font-medium"></p>
                                                                <p id="fileSize" class="text-gray-600 text-sm"></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="d-grid gap-2 mt-4">
                                                    <button id="uploadBtn" type="submit" class="btn btn-primary btn-lg py-3" disabled>
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
        </div>
    </div>
<script>
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const uploadBtn = document.getElementById('uploadBtn');
const btnText = document.getElementById('btnText');

uploadArea.addEventListener('click', () => fileInput.click());

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadArea.classList.add('drag-over');
});

uploadArea.addEventListener('dragleave', function() {
    uploadArea.classList.remove('drag-over');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();

    uploadArea.classList.remove('drag-over');

    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showFile();
    }
});

fileInput.addEventListener('change', showFile);

function showFile() {

    if (!fileInput.files.length) return;

    const file = fileInput.files[0];

    fileInfo.classList.remove('hidden');
    uploadArea.classList.add('file-uploaded');

    fileName.innerHTML = file.name;
    fileSize.innerHTML = (file.size / 1024 / 1024).toFixed(2) + " MB";

    uploadBtn.disabled = false;
    btnText.innerHTML = "Formatlash va yuklab olish";
}

document.getElementById('uploadForm').addEventListener('submit', async function(e){

    e.preventDefault();

    uploadBtn.disabled = true;
    btnText.innerHTML = "Kutilmoqda...";

    const formData = new FormData();
    formData.append('excel_file', fileInput.files[0]);

    try {

        const response = await fetch('/api/convert-e-ombor-excel', {
            method: 'POST',
            headers:{
                'X-CSRF-TOKEN':'{{ csrf_token() }}'
            },
            body:formData
        });

        if(!response.ok){

            Swal.fire({
                icon:'error',
                title:'Xatolik',
                text:'Excelni qayta ishlashda xatolik yuz berdi'
            });

            uploadBtn.disabled = false;
            btnText.innerHTML = "Formatlash va yuklab olish";

            return;
        }

        const blob = await response.blob();

        const url = window.URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        const disposition = response.headers.get('Content-Disposition');

        let filename = 'formatted.xlsx';

        if (disposition) {
            const match = disposition.match(/filename="?([^"]+)"?/);

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
            icon:'success',
            title:'Tayyor',
            text:'Fayl muvaffaqiyatli yuklab olindi'
        });

    } catch (e){

        Swal.fire({
            icon:'error',
            title:'Server xatosi',
            text:e.message
        });

    } finally{

        uploadBtn.disabled = false;
        btnText.innerHTML = "Formatlash va yuklab olish";

    }

});
</script>

</body>
</html>