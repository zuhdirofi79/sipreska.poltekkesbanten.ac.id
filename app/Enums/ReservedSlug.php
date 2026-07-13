<?php

namespace App\Enums;

enum ReservedSlug: string
{
    case Login = 'login';
    case Logout = 'logout';
    case Dashboard = 'dashboard';
    case Kegiatan = 'kegiatan';
    case Kalender = 'kalender';
    case Absensi = 'absensi';
    case Form = 'form';
    case Admin = 'admin';
    case Api = 'api';
    case Storage = 'storage';
    case Profil = 'profil';
    case GantiPassword = 'ganti-password';
    case ManajemenPengguna = 'manajemen-pengguna';
    case MasterUnit = 'master-unit';
    case MasterKategori = 'master-kategori';
    case LogAktivitas = 'log-aktivitas';
    case Up = 'up';
    case Livewire = 'livewire';
    case Filament = 'filament';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::values(), true);
    }
}
