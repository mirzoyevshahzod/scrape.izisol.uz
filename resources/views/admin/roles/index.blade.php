@extends('layouts.app')

@section('title', 'Rollar')

@section('content')
    <div class="page-header">
        <div>
            <h1>Rollar</h1>
            <p>Har bir rol qaysi sahifa va amallarga ruxsat berishini belgilaydi.</p>
        </div>
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Yangi rol
        </a>
    </div>

    <div class="card">
        <div>
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
                                    <span class="badge {{ $role->name === 'super_admin' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-primary-subtle text-primary-emphasis' }}">{{ $role->name }}</span>
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
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-light" title="Tahrirlash">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline"
                                              data-confirm="&quot;{{ $role->name }}&quot; roli o'chirilsinmi?" onsubmit="return confirm(this.dataset.confirm);">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-light text-danger" title="O'chirish"><i class="fas fa-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"><div class="empty-state"><div><i class="fas fa-user-shield"></i></div>Hali rollar yo'q.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
