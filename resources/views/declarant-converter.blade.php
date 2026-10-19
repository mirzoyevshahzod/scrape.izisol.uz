@extends('layouts.app')

@section('title', 'Declarant Converter')

@section('content')
    <div class="page-header">
        <div>
            <h1>Declarant Converter</h1>
            <p>Belarus declarant fayllarini Zanjeer CRM'ga yuklash uchun kerakli formatga o'tkazish.</p>
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
                        <span class="icon-tile"><i class="fas fa-file-excel"></i></span>
                        <div>
                            <h2>Faylni formatlash</h2>
                            <p>Declarant (Belarus) scraping fayllaridan birini tanlang — formatlangan Excel fayl avtomatik yuklab olinadi.</p>
                        </div>
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data" class="mt-auto">
                        @csrf
                        <label for="region" class="form-label">Fayl</label>
                        <select name="region" id="region" class="form-select mb-3" required>
                            <option value="">-- Fayllar --</option>
                        </select>
                        <button id="uploadBtn" type="submit" class="btn btn-primary w-100 py-2" disabled>
                            <i class="fas fa-wand-magic-sparkles me-1"></i>
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
@endpush
