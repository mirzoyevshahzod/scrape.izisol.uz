@extends('layouts.app')

@section('title', 'Qozoq → Zanjeer')

@section('content')
    <div class="page-header">
        <div>
            <h1>Qozoq → Zanjeer</h1>
            <p>Tekshirilgan qozoq faylidan Zanjeer CRM'ga yuklanadigan fayllar arxivini tayyorlash.</p>
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
        <div class="col-lg-7">
            <div class="card action-card">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-file-export"></i></span>
                        <div>
                            <h2>Zanjeer uchun fayllar</h2>
                            <p>Tekshirilgan qozoq faylini tanlang — chegaralar bo'yicha ajratilgan fayllar ZIP arxiv sifatida yuklab olinadi.</p>
                        </div>
                    </div>

                    <form id="scrapeForm" enctype="multipart/form-data">
                        <label for="merge5" class="form-label">Qozoq tekshirilgan fayl</label>
                        <select name="merge5" id="merge5" class="form-select mb-3">
                            <option value="">-- Qozoq Tekshirilgan Filelar --</option>
                        </select>

                        <button type="submit" class="btn btn-primary w-100 py-2" id="submitBtn">
                            <i class="fas fa-file-zipper me-1"></i>Arxivni tayyorlash va yuklab olish
                        </button>
                        <div id="scrapeStatus" class="status-line"></div>
                    </form>

                    <div class="loading" id="loadingIndicator">
                        <div class="spinner"></div>
                        <div class="loading-text">Ma'lumotlar qayta ishlanmoqda...</div>
                    </div>
                    <div id="status" class="status-line"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
const mergeSelect5 =
    document.getElementById('merge5');

    

async function QozoqFiles()
{
    try {

        const response =
            await fetch('/api/qozoq/import/files');

        const data =
            await response.json();

            console.log(data);

        mergeSelect5.innerHTML =
            '<option value="">-- Qozoq Scraping Fayllar --</option>';

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

async function downloadZip() {

    const formData = new FormData();
    formData.append('file', document.getElementById('merge5').value);

    const response = await fetch('/api/zanjeer-upload-files', {
        method: 'POST',
        body: formData
    });


    const blob = await response.blob();

    const disposition = response.headers.get('Content-Disposition');

    let filename = 'download.zip';

    if (disposition) {
        const match = disposition.match(/filename="?([^"]+)"?/);
        if (match) {
            filename = match[1];
        }
    }

    const url = window.URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();

    window.URL.revokeObjectURL(url);
}

document.getElementById('scrapeForm').addEventListener('submit', function (e) {
    e.preventDefault();

    console.log('submit');

    downloadZip();
});

document.addEventListener(
    'DOMContentLoaded',
    QozoqFiles
);




 
</script>
@endpush
