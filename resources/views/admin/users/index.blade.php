@extends('layouts.admin')

@section('title', 'Foydalanuvchilar')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h3 class="mb-0"><i class="fas fa-users me-2"></i>Foydalanuvchilar</h3>
        <a href="{{ route('admin.users.create') }}" class="btn btn-gradient">
            <i class="fas fa-plus me-1"></i>Yangi foydalanuvchi
        </a>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <form method="GET" class="mb-3" style="max-width: 360px;">
                <div class="input-group">
                    <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Ism yoki email bo'yicha qidirish">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Ism</th>
                            <th>Email</th>
                            <th>Rollar</th>
                            <th>Yaratilgan</th>
                            <th class="text-end">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php
                                $locked = $user->hasRole('super_admin') && ! auth()->user()->hasRole('super_admin');
                            @endphp
                            <tr>
                                <td>
                                    {{ $user->name }}
                                    @if ($user->is(auth()->user()))
                                        <span class="badge bg-light text-dark border ms-1">siz</span>
                                    @endif
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @forelse ($user->roles as $role)
                                        <span class="badge {{ $role->name === 'super_admin' ? 'bg-danger' : 'bg-primary' }}">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-muted small">rol yo'q</span>
                                    @endforelse
                                </td>
                                <td class="text-muted small">{{ $user->created_at?->format('Y-m-d') }}</td>
                                <td class="text-end text-nowrap">
                                    @unless ($locked)
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @unless ($user->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                                                  data-confirm="{{ $user->email }} o'chirilsinmi?" onsubmit="return confirm(this.dataset.confirm);">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        @endunless
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Foydalanuvchi topilmadi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
