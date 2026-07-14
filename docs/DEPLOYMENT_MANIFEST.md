# SIPRESKA Deployment Manifest — Fase 2 (Full Custom Modules)

**Generated:** 2026-07-13  
**Target production:** `https://sipreska.poltekkesbanten.ac.id`  
**Application path:** `/home/u708283844/domains/sipreska.poltekkesbanten.ac.id/sipreska-app`

---

## 1. Ringkasan Perubahan

Implementasi end-to-end modul SIPRESKA custom: kegiatan, dokumen (5 komponen), absensi publik, export Excel/PDF, kalender, dashboard statistik, master kategori/unit, log aktivitas, profil, middleware `EnsureCustomAppAccess`, dan file serving tanpa `storage:link`.

**Filament `/admin` masih aktif** sebagai emergency rollback (belum dinonaktifkan).

---

## 2. Migration Baru (additive, non-destructive)

| Migration | Tabel |
|-----------|-------|
| `2026_07_13_200000_create_sipreska_activity_module_tables.php` | `activities`, `invitation_documents`, `minutes_documents`, `documentations`, `publications`, `attendance_forms`, `attendance_responses` |

**Production command:**
```bash
cd /home/u708283844/domains/sipreska.poltekkesbanten.ac.id/sipreska-app
php artisan migrate --force
```

**Rollback (gunakan batch aktual):**
```bash
php artisan migrate:status
php artisan migrate:rollback --batch=N
```

---

## 3. Dependency Composer Baru

| Package | Versi | Fungsi |
|---------|-------|--------|
| `barryvdh/laravel-dompdf` | ^3.1 | Export PDF absensi |
| `openspout/openspout` | ^4.32 | Export Excel (PHP 8.3) |
| `chillerlan/php-qrcode` | ^5.0 | QR Code form absensi |

**Production command:**
```bash
composer install --no-dev --optimize-autoloader
```

---

## 4. Asset Build

```bash
npm ci
npm run build
```

Output: `public/build/*`

---

## 5. Konfigurasi (.env — manual, jangan otomatis)

Tambahkan/verifikasi (opsional):
```env
SIPRESKA_PUBLICATION_OPTIONAL=true
SIPRESKA_ATTENDANCE_POLL_SECONDS=12
SIPRESKA_UPLOAD_MAX_KB=5120
SIPRESKA_LEGACY_FILAMENT_EMAIL=auloraqowiyboyqowboy@gmail.com
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
```

---

## 6. Storage Strategy (Hostinger)

- **JANGAN** jalankan `php artisan storage:link` (exec disabled)
- File privat: `storage/app/sipreska/`
- Akses file: route terautentikasi `/files/{type}/{id}`
- Pastikan folder writable:
```bash
mkdir -p storage/app/sipreska
chmod -R 775 storage/app/sipreska storage/logs storage/framework
```

---

## 7. Cache & Optimize

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 8. Smoke Test Production

```bash
php artisan test --exclude-group=local   # optional if test DB available
php artisan route:list --path=kegiatan
php artisan migrate:status
curl -I https://sipreska.poltekkesbanten.ac.id/login
curl -I https://sipreska.poltekkesbanten.ac.id/kegiatan   # expect 302 login
curl -I https://sipreska.poltekkesbanten.ac.id/admin      # Filament rollback still up
```

**Manual checks:**
1. Login superadmin custom → dashboard, kegiatan, kalender
2. Login admin unit → hanya data unit sendiri
3. Login legacy Filament → `/admin` OK, `/dashboard` ditolak
4. Buat kegiatan → upload PDF undangan → download via `/files/...`
5. Buka form absensi publik → submit → export Excel/PDF
6. Legacy form `/form/{slug}` masih berfungsi

---

## 9. Rollback Plan

1. `php artisan migrate:rollback --batch=N` (batch migration Fase 2)
2. Restore file backup sebelum deploy
3. `composer install` dari `composer.lock` sebelumnya
4. `npm run build` dari commit sebelumnya
5. `php artisan config:cache && php artisan route:cache`
6. Filament `/admin` tetap tersedia jika custom app bermasalah

---

## 10. Yang TIDAK BOLEH dilakukan di production

- ❌ Menghapus data / akun legacy
- ❌ Menjalankan seeder production otomatis
- ❌ Mengubah `.env` production otomatis
- ❌ `storage:link` / exec symlink
- ❌ Menghapus tabel legacy (`event_forms`, `submissions`, dll.)
- ❌ Nonaktifkan Filament sebelum verifikasi penuh

---

## 11. File Penting Baru/Diubah

> **KRITIS — deploy bersamaan:** `routes/web.php` WAJIB di-upload bersamaan dengan controller/view absensi publik. Tanpa ini, halaman admin tab Absensi akan HTTP 500 (`RouteNotFoundException: attendance.public.show`). Setelah upload, jalankan `php artisan optimize:clear` sebelum `route:cache`.

### Backend
- `app/Http/Middleware/EnsureCustomAppAccess.php`
- `app/Http/Controllers/Activity*.php`, `CalendarController`, `DashboardController`, `CategoryController`, `UnitController`, `ActivityLogController`, `ProfileController`, `FileController`, `PublicAttendanceController`
- `app/Services/StatisticsService.php`, `AttendanceService.php`, `AttendanceExportService.php`, `FileStorageService.php`, `ActivityCompletenessService.php`
- `app/Policies/ActivityPolicy.php`, `CategoryPolicy.php`, `UnitPolicy.php`, `ActivityLogPolicy.php`
- `app/Enums/ActivityStatus.php`, `DocumentationType.php`, `PublicationPlatform.php`, `AttendanceWorkUnit.php`
- `app/Models/Activity.php` + dokumen/absensi models
- `routes/web.php` (full route map)
- `config/filesystems.php` (disk `sipreska`)
- `config/sipreska.php` (publication_optional, upload limits)

### Frontend
- `resources/views/activities/*`, `calendar/*`, `dashboard/*`, `master/*`, `activity-logs/*`, `profile/*`, `attendance/*`
- `resources/views/components/*` (reusable UI)
- `resources/views/layouts/partials/sidebar.blade.php` (semua menu aktif)
- `resources/js/app.js` (signature pad, calendar Alpine)

### Tests
- `tests/Feature/Sipreska/*` (39 test cases baru)
- Total: **64 tests, 151 assertions — ALL PASS**

---

## 12. Technical Debt Tersisa

1. Nonaktifkan `AdminPanelProvider` setelah verifikasi production Fase 2
2. Hapus dependency Filament setelah rollback tidak diperlukan
3. `activities/attendance/edit-response` view baru ditambahkan (koreksi respons)
4. Jalankan `vendor/bin/pint` di CI/server jika lokal hang

---

## 13. Post-Deploy: Nonaktifkan Filament (setelah verifikasi)

1. Comment/remove `App\Providers\Filament\AdminPanelProvider::class` dari `bootstrap/providers.php`
2. `php artisan route:cache`
3. Verifikasi `/admin` → 404
4. Baru `composer remove filament/filament` (opsional, batch terpisah)
