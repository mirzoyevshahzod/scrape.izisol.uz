@extends('layouts.app')

@section('title', 'Turkiya Converter')

@section('content')
    <div class="page-header">
        <div>
            <h1>Turkiya Converter</h1>
            <p>Turkiya fayllarini Zanjeer CRM'ga yuklash uchun kerakli formatga o'tkazish.</p>
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
                            <p>Turkiya scraping fayllaridan birini tanlang — formatlangan Excel fayl avtomatik yuklab olinadi.</p>
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

document.getElementById('uploadForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    const selectedFile = select.value;

    if (!selectedFile) {
        Swal.fire('Xatolik', 'Avval fayl tanlang.', 'error');
        return;
    }

    console.log('Backendga yuborilmoqda:', selectedFile);

    uploadBtn.disabled = true;
    btnText.innerHTML = 'Yuklanmoqda...';

    try {

        const response = await fetch('/api/border-convert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                file: selectedFile
            })
        });

        console.log('Status:', response.status);

        if (!response.ok) {
            throw new Error('Server xatosi: ' + response.status);
        }

        const disposition = response.headers.get('Content-Disposition');

        let filename = 'converted.xlsx';

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

        Swal.fire({
            icon: 'success',
            title: 'Muvaffaqiyatli',
            text: 'Fayl tayyorlandi.'
        });

    } catch (e) {

        console.error(e);

        Swal.fire({
            icon: 'error',
            title: 'Xatolik',
            text: e.message
        });

    } finally {

        uploadBtn.disabled = false;
        btnText.innerHTML = 'Convert qilish';

    }

});

select.addEventListener('change', () => {

    if (select.value) {
        uploadBtn.disabled = false;
        btnText.innerText = 'Convert qilish';
    } else {
        uploadBtn.disabled = true;
        btnText.innerText = 'Avval fayl tanlang';
    }

});
document.addEventListener('DOMContentLoaded', async () => 
{

    try {

        const response =
            await fetch('/api/turkey/files');

        const data =
            await response.json();
            console.log(data);

      

        if (data.status && data.files.length) {


            data.files.forEach(file => {
                const option = document.createElement('option');

                option.value = file;
                option.textContent = file;

                select.appendChild(option);
            });
        }

    } catch (error) {

        console.error(error);
    }
});




</script>
@endpush
