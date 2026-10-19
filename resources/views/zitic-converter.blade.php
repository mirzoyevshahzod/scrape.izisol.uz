@extends('layouts.app')

@section('title', 'Zitic Converter')

@section('content')
    <div class="page-header">
        <div>
            <h1>Zitic Converter</h1>
            <p>Zitic fayllarini Zanjeer CRM'ga yuklash uchun kerakli formatga o'tkazish.</p>
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
                            <p>Zitic scraping fayllaridan birini tanlang — formatlangan Excel fayl avtomatik yuklab olinadi.</p>
                        </div>
                    </div>

                    <form id="scrapeForm" enctype="multipart/form-data" class="mt-auto">
                        <label for="merge5" class="form-label">Fayl</label>
                        <select name="merge5" id="merge5" class="form-select mb-3">
                            <option value="">-- Zitic Filelar --</option>
                        </select>
                        <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn">
                            <i class="fas fa-wand-magic-sparkles me-1"></i>Formatlash va yuklab olish
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
const mergeSelect5 =
    document.getElementById('merge5');

    

async function ZiticFiles()
{
    try {

        const response =
            await fetch('/api/zitic/files');

        const data =
            await response.json();

            console.log(data);

        mergeSelect5.innerHTML =
            '<option value="">-- Zitic Scraping Fayllar --</option>';

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

document.getElementById('scrapeForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const file = document.getElementById('merge5').value;

    if (!file) {
        Swal.fire('Xatolik', 'Faylni tanlang!', 'warning');
        return;
    }

    const response = await fetch('/api/zitic/convert', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            file: file
        })
    });

    if (!response.ok) {
        Swal.fire('Xatolik', 'Konvertatsiya amalga oshmadi.', 'error');
        return;
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

});


document.addEventListener(
    'DOMContentLoaded',
    ZiticFiles
);




 
</script>
@endpush
