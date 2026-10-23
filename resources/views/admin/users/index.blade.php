@extends('layouts.app')

@section('title', 'Foydalanuvchilar')

@section('content')
    <div class="page-header">
        <div>
            <h1>Foydalanuvchilar</h1>
            <p>Tizimga kira oladigan foydalanuvchilar va ularning rollari.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Yangi foydalanuvchi
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" class="d-flex" style="max-width: 360px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent"><i class="fas fa-magnifying-glass text-muted"></i></span>
                    <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Ism yoki email bo'yicha qidirish">
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Foydalanuvchi</th>
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
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    <div>
                                        <div class="fw-medium">
                                            {{ $user->name }}
                                            @if ($user->is(auth()->user()))
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">siz</span>
                                            @endif
                                        </div>
                                        <div class="text-muted small">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @forelse ($user->roles as $role)
                                    <span class="badge {{ $role->name === 'super_admin' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-primary-subtle text-primary-emphasis' }}">{{ $role->name }}</span>
                                @empty
                                    <span class="text-muted small">rol yo'q</span>
                                @endforelse
                            </td>
                            <td class="text-muted small">{{ $user->created_at?->format('Y-m-d') }}</td>
                            <td class="text-end text-nowrap">
                                @unless ($locked)
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-light" title="Tahrirlash">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                                              data-confirm="{{ $user->email }} o'chirilsinmi?" data-confirm-title="O'chirishni tasdiqlang" data-confirm-variant="danger" data-confirm-button="O'chirish">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-light text-danger" title="O'chirish"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endunless
                                @else
                                    <span class="text-muted small"><i class="fas fa-lock me-1"></i>himoyalangan</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <div><i class="fas fa-user-slash"></i></div>
                                    Foydalanuvchi topilmadi.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-body border-top">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
