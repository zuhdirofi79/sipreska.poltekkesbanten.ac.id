@extends('layouts.app')

@section('title', 'Profil')
@section('page_title', 'Profil Saya')
@section('page_description', 'Perbarui informasi akun Anda.')

@section('content')
    <x-card>
        <form method="POST" action="{{ route('profile.update') }}" class="max-w-xl space-y-4">
            @csrf
            @method('PUT')

            <x-form-input label="Nama Lengkap" name="name" :value="$user->name" required />
            <x-form-input label="Email" name="email" type="email" :value="$user->email" required />

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Role</label>
                <input type="text" value="{{ $user->role?->label() ?? '-' }}" disabled class="sipreska-input bg-gray-50 text-gray-500">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Unit</label>
                <input type="text" value="{{ $user->unit?->name ?? '-' }}" disabled class="sipreska-input bg-gray-50 text-gray-500">
            </div>

            <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                <button type="submit" class="sipreska-btn-primary">Simpan Perubahan</button>
                <a href="{{ route('password.change') }}" class="sipreska-btn-secondary">Ganti Password</a>
            </div>
        </form>
    </x-card>
@endsection
