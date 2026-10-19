@extends('layouts.admin')

@section('title', 'Rollar')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h3 class="mb-0"><i class="fas fa-user-shield me-2"></i>Rollar</h3>
        <a href="{{ route('admin.roles.create') }}" class="btn btn-gradient">
            <i class="fas fa-plus me-1"></i>Yangi rol
        </a>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nomi</th>
                            <th>Ruxsatlar</th>
                            <th>Foydalanuvchilar</th>
                            <th class="text-end">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td>
                                    <span class="badge {{ $role->name === 'super_admin' ? 'bg-danger' : 'bg-primary' }}">{{ $role->name }}</span>
                                </td>
                                <td>
                                    @if ($role->name === 'super_admin')
                                        <span class="text-muted small">barchasi (avtomatik)</span>
                                    @else
                                        {{ $role->permissions_count }} ta
                                    @endif
                                </td>
                                <td>{{ $role->users_count }} ta</td>
                                <td class="text-end text-nowrap">
                                    @if ($role->name === 'super_admin')
                                        <span class="text-muted small"><i class="fas fa-lock me-1"></i>tizim roli</span>
                                    @else
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline"
                                              data-confirm="&quot;{{ $role->name }}&quot; roli o'chirilsinmi?" onsubmit="return confirm(this.dataset.confirm);">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Hali rollar yo'q.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
