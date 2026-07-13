@extends('layouts.app')

@section('title', 'Tambah Pengguna')
@section('page_title', 'Tambah Pengguna')
@section('page_description', 'Buat akun administrator SIPRESKA baru.')

@section('content')
    <div class="sipreska-card max-w-3xl p-6">
        <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
            @csrf
            @include('users._form')
            <div class="flex gap-3">
                <a href="{{ route('users.index') }}" class="sipreska-btn-secondary">Batal</a>
                <button type="submit" class="sipreska-btn-primary">Simpan Pengguna</button>
            </div>
        </form>
    </div>
@endsection
