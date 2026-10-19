@extends('layouts.app')

@section('title', 'Autos')

@push('styles')
<style>
    #autosTable td,
    #autosTable th {
        white-space: nowrap;
        font-size: 0.8125rem;
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>Autos</h1>
            <p>Bazadagi avtomobillar ro'yxati.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-light" id="uploadBtn">
                <i class="fas fa-upload me-1"></i>Upload
            </button>
            <button class="btn btn-light" id="downloadBtn">
                <i class="fas fa-download me-1"></i>Download
            </button>
            <button class="btn btn-primary" onclick="loadAutos(currentPage)">
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

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="fas fa-car text-muted"></i>Avtomobillar
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="autosTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Model</th>
                                    <th>Volume</th>
                                    <th>License</th>
                                    <th>Trailer License</th>
                                    <th>Trailer Type</th>
                                    <th>Car Number</th>
                                    <th>Company</th>
                                    <th>Tin</th>
                                    <th>Phone</th>
                                    <th>New Phone</th>
                                    <th>Type Of Activity</th>
                                    <th>Transport Type</th>
                                    <th>Cargo Type</th>
                                    <th>Given At</th>
                                    <th>Expired At</th>
                                    <th>Status</th>
                                    <th>Regional Administrator</th>
                                    <th>Created By</th>
                                    <th>Driver Fio</th>
                                    <th>Driver Phones</th>
                                    <th>Deleted At</th>
                                    <th>Created At</th>
                                    <th>Updated At</th>
                                </tr>
                            </thead>

                            <tbody id="autosTableBody">
                                <tr>
                                    <td colspan="24" class="text-center">
                                        Yuklanmoqda...
                                    </td>
                                </tr>
                            </tbody>
            </table>
        </div>
        <div class="card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div id="paginationInfo" class="text-muted small"></div>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-sm btn-light" id="prevPageBtn">
                    <i class="fas fa-angle-left me-1"></i>Oldingi
                </button>
                <span class="small text-muted px-2" id="currentPage">1</span>
                <button class="btn btn-sm btn-light" id="nextPageBtn">
                    Keyingi<i class="fas fa-angle-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
<script>

        let currentPage = 1;
let lastPage = 1;

// Bazadagi qiymatlar jadvalga HTML sifatida emas, oddiy matn sifatida
// chiqishi uchun (XSS): null/undefined bo'lsa '-'.
function esc(value) {
    if (value === null || value === undefined) return '-';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
async function loadAutos(page = 1) {
    const tbody = document.getElementById('autosTableBody');

    tbody.innerHTML = `
        <tr>
            <td colspan="24" class="text-center">
                Yuklanmoqda...
            </td>
        </tr>
    `;

    try {
        const response = await fetch(`/api/autos?page=${page}`);
        const data = await response.json();

        tbody.innerHTML = '';

        data.data.forEach(auto => {
            tbody.innerHTML += `
                <tr>
                    <td>${esc(auto.id)}</td>
                    <td>${esc(auto.model)}</td>
                    <td>${esc(auto.volume)}</td>
                    <td>${esc(auto.license)}</td>
                    <td>${esc(auto.trailer_license)}</td>
                    <td>${esc(auto.trailer_type)}</td>
                    <td>${esc(auto.car_number)}</td>
                    <td>${esc(auto.company_name)}</td>
                    <td>${esc(auto.tin)}</td>
                    <td>${esc(auto.phone)}</td>
                    <td>${esc(auto.new_phone)}</td>
                    <td>${esc(auto.type_of_activity)}</td>
                    <td>${esc(auto.transport_type)}</td>
                    <td>${esc(auto.cargo_type)}</td>
                    <td>${esc(auto.given_date)}</td>
                    <td>${esc(auto.expried_at)}</td>
                    <td>
                        <span class="badge bg-success">
                            ${esc(auto.status)}
                        </span>
                    </td>
                    <td>${esc(auto.regional_administration)}</td>
                    <td>${esc(auto.created_by)}</td>
                    <td>${esc(auto.driver_fio)}</td>
                    <td>${esc(auto.driver_phones)}</td>
                    <td>${esc(auto.deleted_at)}</td>
                    <td>${esc(auto.created_at)}</td>
                    <td>${esc(auto.updated_at)}</td>
                </tr>
            `;
        });

        currentPage = data.current_page;
        lastPage = data.last_page;
        document.getElementById('currentPage').textContent =
        `${currentPage} / ${lastPage}`;
        document.getElementById('paginationInfo').textContent = `Total: ${data.total} records`;
        document.getElementById('prevPageBtn').disabled = currentPage === 1;
        document.getElementById('nextPageBtn').disabled = currentPage === lastPage;


    } catch (error) {
        console.error(error);

        tbody.innerHTML = `
            <tr>
                <td colspan="24" class="text-danger text-center">
                    Xatolik yuz berdi
                </td>
            </tr>
        `;
    }
}

loadAutos();

document.getElementById('prevPageBtn')
.addEventListener('click', () => {

    if (currentPage > 1) {
        loadAutos(currentPage - 1);
    }
});

document.getElementById('nextPageBtn')
.addEventListener('click', () => {

    if (currentPage < lastPage) {
        loadAutos(currentPage + 1);
    }
});


</script>
@endpush
