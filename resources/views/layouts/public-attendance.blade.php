<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIPRESKA')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sipreska-body min-h-screen">
    <div class="public-attendance-page">
        @if (session('success'))
            <div class="public-attendance-form-wrapper">
                <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="public-attendance-form-wrapper">
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
