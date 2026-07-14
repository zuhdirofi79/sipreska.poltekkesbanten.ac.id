<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIPRESKA')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sipreska-body min-h-screen">
    <div class="flex min-h-screen" x-data="{ sidebarOpen: false }">
        @include('layouts.partials.sidebar')

        <div class="flex min-h-screen flex-1 flex-col lg:pl-72">
            @include('layouts.partials.topbar')

            <main class="flex-1 p-4 lg:p-8">
                @if (session('success'))
                    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('warning'))
                    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {{ session('warning') }}
                    </div>
                @endif

                @if (session('temporary_password'))
                    <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        Password sementara (tampil sekali): <strong>{{ session('temporary_password') }}</strong>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
