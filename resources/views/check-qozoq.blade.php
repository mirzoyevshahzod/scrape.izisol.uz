@extends('layouts.app')

@section('title', 'Qozoq Tekshirilgan')

@section('content')
    <div class="page-header">
        <div>
            <h1>Qozoq Tekshirilgan</h1>
            <p>Warehouse tekshirgan qozoq fayllarini yuklash va yuklab olish.</p>
        </div>
        <div class="d-flex gap-2">
            <form id="uploadForm" enctype="multipart/form-data">
                @csrf
                <input
                    type="file"
                    id="excelFile"
                    name="excel"
                    accept=".xlsx,.xls"
                    hidden
                >
                <button type="button" class="btn btn-primary" id="uploadBtn">
                    <i class="fas fa-upload me-1"></i>Fayl yuklash
                </button>
            </form>
            <button type="button" class="btn btn-light" onclick="refreshFiles()">
                <i class="fas fa-rotate-right me-1"></i>Yangilash
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
                        <span class="icon-tile"><i class="fas fa-file-arrow-down"></i></span>
                        <div>
                            <h2>Fayllarni yuklab olish</h2>
                            <p>Tekshirilgan qozoq fayllaridan birini tanlang. Yangi fayl yuklash uchun yuqoridagi tugmadan foydalaning (.xlsx, .xls).</p>
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
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
<script>

const uploadBtn = document.getElementById('uploadBtn');
const excelFile = document.getElementById('excelFile');

uploadBtn.addEventListener('click', () => {
    excelFile.click();
});

excelFile.addEventListener('change', async function () {

    if (!this.files.length) {
        return;
    }

    const formData = new FormData();
    formData.append('excel', this.files[0]);

    try {

        Swal.fire({
            title: 'Yuklanmoqda...',
            text: 'Iltimos kuting',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('/api/upload-qozoq', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content')
            },
            body: formData
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Xatolik');
        }

        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: data.message
        });

    } catch (error) {

        Swal.fire({
            icon: 'error',
            title: 'Xatolik',
            text: error.message
        });

    }

});


async function loadFiles() {

    const fileSelect =
        document.getElementById('region');

    try {

        const response = await fetch(
            '/api/qozoq/import/files'
        );

        const data =
            await response.json();

        fileSelect.innerHTML =
            '<option value="">-- Fayllar --</option>';

        if (
            data.status &&
            Array.isArray(data.files)
        ) {

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

document
.getElementById('belarusSelect')
.addEventListener('submit', function(e) {

    e.preventDefault();

    const select =
        document.getElementById('region');

    if (!select.value) {

        Swal.fire({
            icon: 'error',
            title: 'Xatolik',
            text: 'Fayl tanlanmadi'
        });

        return;
    }

    window.location.href =
        `/api/qozoq/import/download/${
            encodeURIComponent(select.value)
        }`;

});

async function refreshFiles() {

    await loadFiles();

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Fayllar yangilandi',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
}


loadFiles();
</script>
@endpush
