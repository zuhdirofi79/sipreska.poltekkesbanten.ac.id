@extends('layouts.app')

@section('title', 'Ganti Password')
@section('page_title', 'Ganti Password')
@section('page_description', 'Anda wajib memperbarui password sebelum melanjutkan.')

@section('content')
    <div class="sipreska-card max-w-xl p-6">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="mb-1 block text-sm font-medium text-gray-700">Password Saat Ini</label>
                <input id="current_password" name="current_password" type="password" required class="sipreska-input">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password Baru</label>
                <input id="password" name="password" type="password" required class="sipreska-input">
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="sipreska-input">
            </div>

            <button type="submit" class="sipreska-btn-primary">Simpan Password</button>
        </form>
    </div>
@endsection
