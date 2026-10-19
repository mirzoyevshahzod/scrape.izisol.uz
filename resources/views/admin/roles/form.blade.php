@extends('layouts.admin')

@section('title', $role->exists ? 'Rolni tahrirlash' : 'Yangi rol')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.roles.index') }}" class="text-decoration-none small">
            <i class="fas fa-arrow-left me-1"></i>Rollar
        </a>
        <h3 class="mt-2 mb-0">{{ $role->exists ? $role->name : 'Yangi rol' }}</h3>
    </div>

    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif

        <div class="card page-card mb-4" style="max-width: 720px;">
            <div class="card-body p-4">
                <label for="name" class="form-label">Rol nomi</label>
                <input id="name" name="name" value="{{ old('name', $role->name) }}"
                       class="form-control @error('name') is-invalid @enderror" required maxlength="50" placeholder="masalan: operator">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        @php $checked = old('permissions', $selected); @endphp
        @error('permissions.*') <div class="alert alert-danger">{{ $message }}</div> @enderror

        <div class="row g-4 mb-4">
            @foreach ($groups as $group => $permissions)
                <div class="col-lg-4 col-md-6">
                    <div class="card page-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0 fw-bold">{{ $group }}</h6>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-toggle-group="{{ $loop->index }}">
                                    hammasi
                                </button>
                            </div>
                            @foreach ($permissions as $name => $label)
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $name }}"
                                           id="perm-{{ $name }}" data-group="{{ $loop->parent->index }}" @checked(in_array($name, $checked, true))>
                                    <label class="form-check-label" for="perm-{{ $name }}">
                                        {{ $label }}
                                        <span class="text-muted small d-block">{{ $name }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-gradient">
                <i class="fas fa-save me-1"></i>Saqlash
            </button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-light">Bekor qilish</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        // "hammasi" — guruhdagi barcha belgilarni yoqadi; hammasi yoqilgan bo'lsa o'chiradi
        document.querySelectorAll('[data-toggle-group]').forEach(function (button) {
            button.addEventListener('click', function () {
                const boxes = document.querySelectorAll('input[data-group="' + button.dataset.toggleGroup + '"]');
                const allChecked = Array.from(boxes).every(function (box) { return box.checked; });
                boxes.forEach(function (box) { box.checked = !allChecked; });
            });
        });
    </script>
@endpush
