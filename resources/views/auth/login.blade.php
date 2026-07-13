<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SIPRESKA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sipreska-body flex min-h-screen items-center justify-center p-4">
    <div class="sipreska-card w-full max-w-md p-8">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-gray-900">SIPRESKA</h1>
            <p class="mt-1 text-sm text-gray-500">Sistem Informasi Presensi dan Kegiatan</p>
            <p class="text-sm text-gray-500">Poltekkes Kemenkes Banten</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="sipreska-input">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                <input id="password" name="password" type="password" required class="sipreska-input">
            </div>
            <button type="submit" class="sipreska-btn-primary w-full">Masuk</button>
        </form>
    </div>
</body>
</html>
