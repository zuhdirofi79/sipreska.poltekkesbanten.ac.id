<aside
    class="sipreska-sidebar fixed inset-y-0 left-0 z-40 w-72 -translate-x-full transform text-white transition-transform lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
>
    <div class="flex h-full flex-col px-4 py-6">
        <div class="mb-8 px-2">
            <div class="mb-4 flex justify-center lg:justify-start">
                <x-sipreska-logo variant="sidebar" />
            </div>
            <div class="text-lg font-bold tracking-wide">SIPRESKA</div>
            <div class="mt-1 text-xs text-white/70">Sistem Informasi Presensi dan Kegiatan</div>
            <div class="text-xs text-white/70">Poltekkes Kemenkes Banten</div>
        </div>

        <nav class="space-y-6 text-sm">
            <div>
                <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wider text-white/50">Menu Utama</div>
                <div class="space-y-1">
                    <a href="{{ route('activities.index') }}" class="sipreska-sidebar-link {{ request()->routeIs('activities.*') ? 'is-active' : '' }}">
                        Agenda Kegiatan
                    </a>
                    <a href="{{ route('calendar.index') }}" class="sipreska-sidebar-link {{ request()->routeIs('calendar.*') ? 'is-active' : '' }}">
                        Kalender Kegiatan
                    </a>
                    <a href="{{ route('dashboard') }}" class="sipreska-sidebar-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                        Dashboard - Statistik
                    </a>
                </div>
            </div>

            @if (auth()->user()?->isSuperadmin())
                <div>
                    <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wider text-white/50">Administrasi</div>
                    <div class="space-y-1">
                        <a href="{{ route('users.index') }}" class="sipreska-sidebar-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}">
                            Manajemen Pengguna
                        </a>
                        <a href="{{ route('master.categories.index') }}" class="sipreska-sidebar-link {{ request()->routeIs('master.categories.*') ? 'is-active' : '' }}">
                            Master Kategori
                        </a>
                        <a href="{{ route('master.units.index') }}" class="sipreska-sidebar-link {{ request()->routeIs('master.units.*') ? 'is-active' : '' }}">
                            Master Unit
                        </a>
                        <a href="{{ route('activity-logs.index') }}" class="sipreska-sidebar-link {{ request()->routeIs('activity-logs.*') ? 'is-active' : '' }}">
                            Log Aktivitas
                        </a>
                    </div>
                </div>
            @endif

            <div>
                <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wider text-white/50">Akun</div>
                <div class="space-y-1">
                    <a href="{{ route('profile.edit') }}" class="sipreska-sidebar-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                        Profil
                    </a>
                    <a href="{{ route('password.change') }}" class="sipreska-sidebar-link {{ request()->routeIs('password.*') ? 'is-active' : '' }}">
                        Ganti Password
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sipreska-sidebar-link w-full text-left">Logout</button>
                    </form>
                </div>
            </div>
        </nav>
    </div>
</aside>

<div
    class="fixed inset-0 z-30 bg-black/40 lg:hidden"
    x-show="sidebarOpen"
    x-transition
    @click="sidebarOpen = false"
></div>
