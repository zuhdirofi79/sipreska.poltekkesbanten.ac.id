@extends('layouts.app')

@section('title', 'Master Unit')
@section('page_title', 'Master Unit')
@section('page_description', 'Kelola unit kerja Poltekkes Kemenkes Banten.')

@section('content')
    <div x-data="{ editingId: null }">
        <x-page-header title="Daftar Unit">
            <x-slot:actions>
                <button type="button" class="sipreska-btn-primary" @click="editingId = 'new'">+ Tambah Unit</button>
            </x-slot:actions>
        </x-page-header>

        <div x-show="editingId === 'new'" x-cloak class="mb-4 sipreska-card p-4">
            <form method="POST" action="{{ route('master.units.store') }}" class="grid gap-3 md:grid-cols-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nama Unit</label>
                    <input type="text" name="name" required class="sipreska-input">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Singkatan</label>
                    <input type="text" name="short_name" required class="sipreska-input">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300">
                        Aktif
                    </label>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="sipreska-btn-primary">Simpan</button>
                    <button type="button" class="sipreska-btn-secondary" @click="editingId = null">Batal</button>
                </div>
            </form>
        </div>

        <x-data-table>
            <x-slot:head>
                <tr>
                    <x-data-table-th>Nama</x-data-table-th>
                    <x-data-table-th>Singkatan</x-data-table-th>
                    <x-data-table-th>Slug</x-data-table-th>
                    <x-data-table-th>Status</x-data-table-th>
                    <x-data-table-th align="right">Aksi</x-data-table-th>
                </tr>
            </x-slot:head>
            @forelse ($units as $unit)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-3" colspan="5" x-show="editingId === {{ $unit->id }}" x-cloak>
                        <form method="POST" action="{{ route('master.units.update', $unit) }}" class="grid gap-3 md:grid-cols-4">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $unit->name }}" required class="sipreska-input">
                            <input type="text" name="short_name" value="{{ $unit->short_name }}" required class="sipreska-input">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_active" value="1" @checked($unit->is_active) class="rounded border-gray-300">
                                Aktif
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" class="sipreska-btn-primary">Simpan</button>
                                <button type="button" class="sipreska-btn-secondary" @click="editingId = null">Batal</button>
                            </div>
                        </form>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900" x-show="editingId !== {{ $unit->id }}">{{ $unit->name }}</td>
                    <td class="px-4 py-3" x-show="editingId !== {{ $unit->id }}">{{ $unit->short_name }}</td>
                    <td class="px-4 py-3 text-gray-500" x-show="editingId !== {{ $unit->id }}">{{ $unit->slug }}</td>
                    <td class="px-4 py-3" x-show="editingId !== {{ $unit->id }}">
                        <x-badge :variant="$unit->is_active ? 'green' : 'gray'">{{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap" x-show="editingId !== {{ $unit->id }}">
                        <button type="button" class="text-blue-600 hover:underline" @click="editingId = {{ $unit->id }}">Edit</button>
                        <span class="mx-1 text-gray-300">|</span>
                        <form method="POST" action="{{ route('master.units.destroy', $unit) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="text-red-600 hover:underline" @click="$dispatch('open-confirm', { id: 'delete-unit-{{ $unit->id }}', form: $el.closest('form') })">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5"><x-empty-state title="Belum ada unit" /></td>
                </tr>
            @endforelse
        </x-data-table>
    </div>

    @foreach ($units as $unit)
        <x-confirm-dialog id="delete-unit-{{ $unit->id }}" title="Hapus Unit?" message="Unit {{ $unit->name }} akan dihapus." confirm-label="Ya, hapus" confirm-class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" />
    @endforeach
@endsection
