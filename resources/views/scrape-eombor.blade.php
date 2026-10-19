@extends('layouts.app')

@section('title', 'E-Ombor Scraping')

@section('content')
    <div class="page-header">
        <div>
            <h1>E-Ombor Scraping</h1>
            <p>E-Ombor (tranzit ID) va Mintrans (transport raqami) ma'lumotlarini avtomatik olish.</p>
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
            <div class="card action-card h-100">
                <div class="card-body">
                    <div class="action-head">
                        <span class="icon-tile"><i class="fas fa-rocket"></i></span>
                        <div>
                            <h2>Scraping parametrlari</h2>
                            <p>Komanda turini, boshlang'ich ID va nechta ma'lumot olinishini kiriting.</p>
                        </div>
                    </div>

                    <form action="{{ route('scrape.eombor.process') }}" method="POST" id="eomborForm">
                        @csrf
                        <div class="mb-3">
                            <label for="command_type" class="form-label">Komanda turi</label>
                            <select id="command_type" name="command_type" class="form-select" required>
                                <option value="" disabled selected>Komanda turini tanlang</option>
                                <option value="scrape_eombor">
                                    🏭 E-Ombor - Tranzit ID bo'yicha qidirish
                                </option>
                                <option value="scrape_mintrans">
                                    🚛 Mintrans - Transport raqami bo'yicha litsenziya
                                </option>
                            </select>
                            <div class="invalid-feedback" id="command_type_error">
                                Iltimos, komanda turini tanlang
                            </div>
                            @error('command_type')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="start_id" class="form-label">Boshlang'ich ID</label>
                                <input type="text"
                                       id="start_id"
                                       name="start_id"
                                       class="form-control"
                                       required
                                       placeholder="AT2025XXXXXXX"
                                       pattern="^[A-Z]{2}\d{11}$"
                                       value="{{ old('start_id') }}">
                                <div class="form-text">2 ta harf + 11 ta raqam (masalan: AT20250346677)</div>
                                <div class="invalid-feedback" id="start_id_error">
                                    Iltimos, to'g'ri formatda ID kiriting
                                </div>
                                @error('start_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="count" class="form-label">Soni</label>
                                <input type="number"
                                       id="count"
                                       name="count"
                                       class="form-control"
                                       required
                                       min="1"
                                       max="4000"
                                       placeholder="Nechta ma'lumot olish kerak?"
                                       value="{{ old('count') }}">
                                <div class="form-text">Maksimal: 4000 ta</div>
                                <div class="invalid-feedback" id="count_error">
                                    Iltimos, 1 dan 4000 gacha son kiriting
                                </div>
                                @error('count')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-play me-1"></i>Scrapingni boshlash
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-warehouse me-2 text-muted"></i>E-Ombor</div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-0 d-grid gap-2">
                        <li><i class="fas fa-check text-success me-2"></i>Tranzit ID bo'yicha qidirish</li>
                        <li><i class="fas fa-check text-success me-2"></i>Bojxona, transport va yuk ma'lumotlari</li>
                        <li><i class="fas fa-check text-success me-2"></i>INN va korxona ma'lumotlari</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-truck me-2 text-muted"></i>Mintrans</div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-0 d-grid gap-2">
                        <li><i class="fas fa-check text-success me-2"></i>Transport raqami bo'yicha litsenziya</li>
                        <li><i class="fas fa-check text-success me-2"></i>Korxona, faoliyat turi va hududiy boshqarma</li>
                        <li><i class="fas fa-clock text-muted me-2"></i>Excel fayl avtomatik yuklab olinadi, jarayon vaqt talab qiladi</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
        document.addEventListener('DOMContentLoaded', function() {
            const eomborForm = document.getElementById('eomborForm');
            
            if (eomborForm) {
                eomborForm.addEventListener('submit', function(e) {
                    const commandType = document.getElementById('command_type').value;
                    const startId = document.getElementById('start_id').value;
                    const count = document.getElementById('count').value;
                    
                    // Clear previous errors
                    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

                    let hasError = false;

                    // Command type validation
                    if (!commandType) {
                        document.getElementById('command_type').classList.add('is-invalid');
                        hasError = true;
                    }

                    // Start ID validation
                    if (!/^[A-Z]{2}\d{11}$/.test(startId)) {
                        document.getElementById('start_id').classList.add('is-invalid');
                        hasError = true;
                    }

                    // Count validation
                    if (count <= 0 || count > 4000 || !Number.isInteger(Number(count))) {
                        document.getElementById('count').classList.add('is-invalid');
                        hasError = true;
                    }

                    if (hasError) {
                        e.preventDefault();
                        // Scroll to first error
                        const firstError = document.querySelector('.is-invalid');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                    } else {
                        // Show loading state
                        const submitBtn = e.target.querySelector('button[type="submit"]');
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Iltimos kuting...';
                        submitBtn.disabled = true;
                    }
                });
            }

            // Real-time validation
            const startIdInput = document.getElementById('start_id');
            const countInput = document.getElementById('count');

            if (startIdInput) {
                startIdInput.addEventListener('input', function() {
                    if (/^[A-Z]{2}\d{11}$/.test(this.value)) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                    }
                });
            }

            if (countInput) {
                countInput.addEventListener('input', function() {
                    const value = parseInt(this.value);
                    if (value >= 1 && value <= 4000) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                    }
                });
            }
        });
    </script>
@endpush
