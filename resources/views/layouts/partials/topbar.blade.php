<header class="sticky top-0 z-20 border-b border-gray-200 bg-white/90 backdrop-blur">
    <div class="flex items-center justify-between px-4 py-4 lg:px-8">
        <div class="flex items-center gap-3">
            <button type="button" class="rounded-lg border border-gray-200 px-3 py-2 text-sm lg:hidden" @click="sidebarOpen = !sidebarOpen">
                Menu
            </button>
            <x-sipreska-logo variant="header" class="hidden sm:block" />
            <div>
                <h1 class="text-lg font-semibold text-gray-900">@yield('page_title', 'SIPRESKA')</h1>
                @hasSection('page_description')
                    <p class="text-sm text-gray-500">@yield('page_description')</p>
                @endif
            </div>
        </div>
        <div class="text-right text-sm">
            <div class="font-medium text-gray-900">{{ auth()->user()->name }}</div>
            <div class="text-gray-500">{{ auth()->user()->email }}</div>
        </div>
    </div>
</header>
