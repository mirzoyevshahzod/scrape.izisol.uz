<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turkiya Files</title>
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
                        Declarant fayllarini Formatlash
                    </h1>

                    <div class="page-card">
                        <div class="container-fluid">
                            <div class="row justify-content-center">
                                <div class="col-md-10">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-search" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                                        <h2 class="page-title" style="font-size: 2.5rem;"> Declarant fayllarini Formatlash</h2>
                                        <p class="page-subtitle">Declarant fayllarini zanjeerga yuklash uchun tayyorlash</p>
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
                                                 <div class="mb-4">
                                                <label for="region" class="form-label fw-bold">
                                                    <i class="fas fa-globe me-2 text-primary"></i>Yuklab olish uchun fayl tanlang:
                                                </label>
                                                <select name="region" id="region" class="form-select form-select-lg" required>
                                                    <option value="">-- Fayllar --</option>
                                                </select>
                                                <div class="form-text">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Yuklab olish uchun fayl tanlang
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

const select = document.getElementById('region');
const uploadBtn = document.getElementById('uploadBtn');
const btnText = document.getElementById('btnText');


// Fayl tanlanganda
select.addEventListener('change', () => {

    if (select.value) {

        uploadBtn.disabled = false;
        btnText.innerText = 'Formatlash';

    } else {

        uploadBtn.disabled = true;
        btnText.innerText = 'Avval fayl tanlang';

    }

});


// Backendga yuborish
document.getElementById('uploadForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    const selectedFile = select.value;

    if (!selectedFile) {

        Swal.fire(
            'Xatolik',
            'Avval Excel fayl tanlang!',
            'warning'
        );

        return;
    }

    console.log('Declarant fayl:', selectedFile);

    uploadBtn.disabled = true;

    btnText.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Formatlanmoqda...';


    try {

        const response = await fetch('/api/declarant/convert', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .content
            },

            body: JSON.stringify({
                file: selectedFile
            })

        });


        console.log('Response status:', response.status);


        if (!response.ok) {

            const errorText = await response.text();

            console.error(errorText);

            throw new Error(
                'Konvertatsiya amalga oshmadi: ' + response.status
            );

        }


        // Backenddan kelgan fayl nomini olish
        const disposition =
            response.headers.get('Content-Disposition');

        let filename = 'converted.xlsx';

        if (disposition) {

            const match =
                disposition.match(/filename="?([^"]+)"?/);

            if (match) {
                filename = match[1];
            }

        }


        // Excelni olish
        const blob = await response.blob();

        const url =
            window.URL.createObjectURL(blob);

        const a =
            document.createElement('a');

        a.href = url;

        a.download = filename;

        document.body.appendChild(a);

        a.click();

        a.remove();

        window.URL.revokeObjectURL(url);


        Swal.fire(
            'Tayyor!',
            'Fayl muvaffaqiyatli formatlandi.',
            'success'
        );


    } catch (error) {

        console.error(error);

        Swal.fire(
            'Xatolik',
            error.message,
            'error'
        );

    } finally {

        uploadBtn.disabled = false;

        btnText.innerText = 'Formatlash';

    }

});


// Declarant fayllarni olish
async function DeclarantFiles()
{
    try {

        const response =
            await fetch('/api/scrape/files');

        const data =
            await response.json();

        console.log('Declarant files:', data);


        select.innerHTML =
            '<option value="">-- Declarant fayllari --</option>';


       if (data.success && data.files.length) {

            data.files.forEach(file => {

                const option = document.createElement('option');

                option.value = file;
                option.textContent = file;

                select.appendChild(option);

            });

        }

    } catch (error) {

        console.error(
            'Declarant fayllarni olishda xato:',
            error
        );

    }
}


document.addEventListener(
    'DOMContentLoaded',
    DeclarantFiles
);

</script>
</body>
</html>