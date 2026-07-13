@extends('layouts.app')

@section('title', 'Edit Pengguna')
@section('page_title', 'Edit Pengguna')
@section('page_description', 'Perbarui data akun dan hak akses.')

@section('content')
    <div class="sipreska-card max-w-3xl p-6">
        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
            @csrf
            @method('PUT')
            @include('users._form', ['user' => $user])

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('users.index') }}" class="sipreska-btn-secondary">Kembali</a>
                <button type="submit" class="sipreska-btn-primary">Simpan Perubahan</button>
            </div>
        </form>

        <div class="mt-6 flex flex-wrap gap-3 border-t border-gray-200 pt-6">
            <form method="POST" action="{{ route('users.toggle-status', $user) }}">
                @csrf
                <button type="submit" class="sipreska-btn-secondary">
                    {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
            </form>

            <form method="POST" action="{{ route('users.reset-password', $user) }}">
                @csrf
                <button type="submit" class="sipreska-btn-secondary">Reset Password</button>
            </form>
        </div>
    </div>
@endsection
