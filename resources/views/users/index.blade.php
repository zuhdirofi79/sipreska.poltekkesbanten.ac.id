@extends('layouts.app')

@section('title', 'Manajemen Pengguna')
@section('page_title', 'Manajemen Pengguna')
@section('page_description', 'Kelola akun administrator dan hak akses unit SIPRESKA.')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama atau email" class="sipreska-input w-56">
            <select name="role" class="sipreska-select w-40">
                <option value="">Semua Role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <select name="unit_id" class="sipreska-select w-48">
                <option value="">Semua Unit</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((string) request('unit_id') === (string) $unit->id)>{{ $unit->short_name }}</option>
                @endforeach
            </select>
            <select name="is_active" class="sipreska-select w-36">
                <option value="">Semua Status</option>
                <option value="1" @selected(request('is_active') === '1')>Aktif</option>
                <option value="0" @selected(request('is_active') === '0')>Nonaktif</option>
            </select>
            <button type="submit" class="sipreska-btn-secondary">Tampilkan</button>
        </form>

        <a href="{{ route('users.create') }}" class="sipreska-btn-primary">+ Tambah Pengguna</a>
    </div>

    <div class="sipreska-card overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-gray-600">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Login Terakhir</th>
                    <th class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="sipreska-badge {{ $user->isSuperadmin() ? 'sipreska-badge-purple' : 'sipreska-badge-blue' }}">
                                {{ $user->role?->label() ?? '-' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ $user->unit?->short_name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="sipreska-badge {{ $user->is_active ? 'sipreska-badge-green' : 'sipreska-badge-gray' }}">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            {{ $user->last_login_at ? \App\Support\DateTimeFormatter::display($user->last_login_at) : 'Belum pernah login' }}
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('users.edit', $user) }}" class="text-blue-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada pengguna.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
