@extends('layouts.app')

@section('title', 'Master Kategori')
@section('page_title', 'Master Kategori')
@section('page_description', 'Kelola kategori kegiatan.')

@section('content')
    <div x-data="{ editingId: null }">
        <x-page-header title="Daftar Kategori">
            <x-slot:actions>
                <button type="button" class="sipreska-btn-primary" @click="editingId = 'new'">+ Tambah Kategori</button>
            </x-slot:actions>
        </x-page-header>

        <div x-show="editingId === 'new'" x-cloak class="mb-4 sipreska-card p-4">
            <form method="POST" action="{{ route('master.categories.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-48 flex-1">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Nama Kategori</label>
                    <input type="text" name="name" required class="sipreska-input" placeholder="Nama kategori">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300">
                    Aktif
                </label>
                <button type="submit" class="sipreska-btn-primary">Simpan</button>
                <button type="button" class="sipreska-btn-secondary" @click="editingId = null">Batal</button>
            </form>
        </div>

        <x-data-table>
            <x-slot:head>
                <tr>
                    <x-data-table-th>Nama</x-data-table-th>
                    <x-data-table-th>Slug</x-data-table-th>
                    <x-data-table-th>Status</x-data-table-th>
                    <x-data-table-th align="right">Aksi</x-data-table-th>
                </tr>
            </x-slot:head>
            @forelse ($categories as $category)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-3" colspan="4" x-show="editingId === {{ $category->id }}" x-cloak>
                        <form method="POST" action="{{ route('master.categories.update', $category) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            @method('PUT')
                            <div class="min-w-48 flex-1">
                                <input type="text" name="name" value="{{ $category->name }}" required class="sipreska-input">
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="rounded border-gray-300">
                                Aktif
                            </label>
                            <button type="submit" class="sipreska-btn-primary">Simpan</button>
                            <button type="button" class="sipreska-btn-secondary" @click="editingId = null">Batal</button>
                        </form>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900" x-show="editingId !== {{ $category->id }}">{{ $category->name }}</td>
                    <td class="px-4 py-3 text-gray-500" x-show="editingId !== {{ $category->id }}">{{ $category->slug }}</td>
                    <td class="px-4 py-3" x-show="editingId !== {{ $category->id }}">
                        <x-badge :variant="$category->is_active ? 'green' : 'gray'">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap" x-show="editingId !== {{ $category->id }}">
                        <button type="button" class="text-blue-600 hover:underline" @click="editingId = {{ $category->id }}">Edit</button>
                        <span class="mx-1 text-gray-300">|</span>
                        <form id="delete-category-{{ $category->id }}" method="POST" action="{{ route('master.categories.destroy', $category) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="text-red-600 hover:underline" @click="$dispatch('open-confirm', { id: 'delete-category-{{ $category->id }}', form: $el.closest('form') })">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4"><x-empty-state title="Belum ada kategori" /></td>
                </tr>
            @endforelse
        </x-data-table>
    </div>

    @foreach ($categories as $category)
        <x-confirm-dialog id="delete-category-{{ $category->id }}" title="Hapus Kategori?" message="Kategori {{ $category->name }} akan dihapus." confirm-label="Ya, hapus" confirm-class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" />
    @endforeach
@endsection
