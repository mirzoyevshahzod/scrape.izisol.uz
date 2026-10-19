@extends('layouts.app')

@section('title', $user->exists ? 'Foydalanuvchini tahrirlash' : 'Yangi foydalanuvchi')

@section('content')
    <div class="page-header">
        <div>
            <a href="{{ route('admin.users.index') }}" class="small text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i>Foydalanuvchilar
            </a>
            <h1 class="mt-1">{{ $user->exists ? $user->name : 'Yangi foydalanuvchi' }}</h1>
            @if ($user->exists)
                <p>{{ $user->email }}</p>
            @endif
        </div>
    </div>

    <div class="card" style="max-width: 720px;">
        <div class="card-body p-4">
            <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
                @csrf
                @if ($user->exists)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label for="name" class="form-label">Ism</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}"
                           class="form-control @error('name') is-invalid @enderror" required maxlength="255">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}"
                           class="form-control @error('email') is-invalid @enderror" required maxlength="255" autocomplete="off">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Parol</label>
                        <input id="password" type="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               @unless($user->exists) required @endunless autocomplete="new-password">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">
                            Kamida 8 belgi.@if ($user->exists) Bo'sh qoldirilsa parol o'zgarmaydi.@endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Parolni takrorlang</label>
                        <input id="password_confirmation" type="password" name="password_confirmation"
                               class="form-control" @unless($user->exists) required @endunless autocomplete="new-password">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label d-block">Rollar</label>
                    @php $checked = old('roles', $selected); @endphp
                    @forelse ($roles as $role)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}"
                                   id="role-{{ $role->id }}" @checked(in_array($role->name, $checked, true))>
                            <label class="form-check-label" for="role-{{ $role->id }}">
                                {{ $role->name }}
                                @if ($role->name === 'super_admin')
                                    <span class="badge bg-danger-subtle text-danger-emphasis">to'liq ruxsat</span>
                                @endif
                            </label>
                        </div>
                    @empty
                        <div class="text-muted small">Hali rollar yo'q.</div>
                    @endforelse
                    @error('roles') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @error('roles.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Saqlash
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-light">Bekor qilish</a>
                </div>
            </form>
        </div>
    </div>
@endsection
