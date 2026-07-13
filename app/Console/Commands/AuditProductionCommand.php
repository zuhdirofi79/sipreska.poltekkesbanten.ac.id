<?php

namespace App\Console\Commands;

use App\Models\EventForm;
use App\Models\FormField;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditProductionCommand extends Command
{
    protected $signature = 'sipreska:audit-production';

    protected $description = 'Read-only production audit for SIPRESKA (environment, database, routes, filesystem, legacy forms).';

    /** @var list<string> */
    private const RESERVED_SLUGS = [
        'login',
        'logout',
        'dashboard',
        'kegiatan',
        'kalender',
        'absensi',
        'manajemen-pengguna',
        'master-kategori',
        'master-unit',
        'log-aktivitas',
        'profil',
        'ganti-password',
        'storage',
        'admin',
        'api',
        'up',
        'form',
    ];

    /** @var list<string> */
    private const INITIAL_ADMIN_EMAILS = [
        'qowiyrofi@poltekkesbanten.ac.id',
        'poltekkesadakpoltekkes@gmail.com',
        'd3.keperawatan@poltekkesbanten.ac.id',
        'adak.tlm@poltekkesbanten.ac.id',
        'strners@poltekkesbanten.ac.id',
    ];

    private bool $databaseAvailable = false;

    public function handle(): int
    {
        $this->line('================================================================================');
        $this->line(' SIPRESKA PRODUCTION AUDIT (READ-ONLY)');
        $this->line(' Generated at: '.now()->timezone('Asia/Jakarta')->format('d F Y H:i:s').' WIB');
        $this->line('================================================================================');
        $this->newLine();

        $this->auditEnvironment();
        $this->auditFilesystem();
        $this->databaseAvailable = $this->auditDatabase();
        $this->auditRoutes();

        if ($this->databaseAvailable) {
            $this->auditLegacyForms();
        } else {
            $this->section('E. DATA FORM LAMA (event_forms)');
            $this->warn('  Skipped — database connection unavailable.');
        }

        $this->auditFilamentDependencies();

        $this->newLine();
        $this->line('================================================================================');
        $this->line(' AUDIT COMPLETE — No data was modified.');
        $this->line('================================================================================');

        return self::SUCCESS;
    }

    private function auditEnvironment(): void
    {
        $this->section('A. LINGKUNGAN APLIKASI');

        $this->kv('APP_NAME', (string) config('app.name'));
        $this->kv('APP_ENV', (string) config('app.env'));
        $this->kv('APP_DEBUG', config('app.debug') ? 'true' : 'false');
        $this->kv('APP_URL', (string) config('app.url'));
        $this->kv('APP_LOCALE', (string) config('app.locale'));
        $this->kv('Timezone', (string) config('app.timezone'));
        $this->kv('Laravel Version', app()->version());
        $this->kv('PHP Version', PHP_VERSION);
        $this->kv('PHP SAPI', PHP_SAPI);
        $this->kv('Base Path', base_path());
        $this->kv('Public Path', public_path());
        $this->kv('Database Driver', (string) config('database.default'));
        $this->kv('Database Host', (string) config('database.connections.'.config('database.default').'.host', 'n/a'));
        $this->kv('Database Name', (string) config('database.connections.'.config('database.default').'.database', 'n/a'));
        $this->kv('Session Driver', (string) config('session.driver'));
        $this->kv('Cache Store', (string) config('cache.default'));
        $this->kv('Queue Connection', (string) config('queue.default'));
        $this->kv('Filesystem Disk', (string) config('filesystems.default'));
        $this->kv('Mail Mailer', (string) config('mail.default'));
    }

    private function auditFilesystem(): void
    {
        $this->section('D. FILE SYSTEM');

        $this->kv('Application Path', base_path());

        $publicHtmlPath = $this->detectPublicHtmlPath();
        $this->kv('Detected public_html Path', $publicHtmlPath ?? 'not found near application');

        if ($publicHtmlPath !== null) {
            $this->auditSymlink('public_html', $publicHtmlPath, public_path());
        }

        $storagePublic = storage_path('app/public');
        $publicStorage = public_path('storage');

        $this->kv('storage/app/public exists', File::isDirectory($storagePublic) ? 'yes' : 'no');
        $this->kv('storage/app/public writable', is_writable($storagePublic) ? 'yes' : 'no');
        $this->kv('public/storage exists', file_exists($publicStorage) ? 'yes' : 'no');
        $this->auditSymlink('public/storage', $publicStorage, $storagePublic);

        $pathsToCheck = [
            'storage' => storage_path(),
            'storage/app' => storage_path('app'),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $this->line('');
        $this->line('  Permissions:');
        foreach ($pathsToCheck as $label => $path) {
            $exists = file_exists($path);
            $writable = $exists && is_writable($path);
            $this->line(sprintf(
                '    - %-22s exists=%s writable=%s path=%s',
                $label,
                $exists ? 'yes' : 'no',
                $writable ? 'yes' : 'no',
                $path
            ));
        }

        $logoPath = public_path('images/logo-poltekkes-banten.png');
        $this->kv('Logo file exists', file_exists($logoPath) ? 'yes' : 'no');
        if (file_exists($logoPath)) {
            $this->kv('Logo file size', (string) filesize($logoPath).' bytes');
        }
    }

    private function auditDatabase(): bool
    {
        $this->section('B. DATABASE');

        try {
            DB::connection()->getPdo();
            $this->kv('Database Connection', 'OK');
        } catch (Throwable $exception) {
            $this->kv('Database Connection', 'FAILED');
            $this->warn('  Error: '.$exception->getMessage());
            $this->warn('  Database sections skipped.');

            return false;
        }

        $this->auditMigrationStatus();
        $this->auditTableCounts();
        $this->auditUsers();
        $this->auditDuplicateEmails();
        $this->auditDuplicateSlugs();

        return true;
    }

    private function auditMigrationStatus(): void
    {
        $this->line('');
        $this->line('  Migration Status:');

        if (! Schema::hasTable('migrations')) {
            $this->warn('    migrations table not found.');

            return;
        }

        $migrations = DB::table('migrations')->orderBy('id')->get();

        if ($migrations->isEmpty()) {
            $this->warn('    No migrations recorded.');

            return;
        }

        foreach ($migrations as $migration) {
            $this->line('    [ran] '.$migration->migration.' (batch '.$migration->batch.')');
        }

        $migrationFiles = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->sort()
            ->values();

        $ran = $migrations->pluck('migration');
        $pending = $migrationFiles->diff($ran);

        if ($pending->isNotEmpty()) {
            $this->line('');
            $this->line('  Pending Migrations:');
            foreach ($pending as $file) {
                $this->line('    [pending] '.$file);
            }
        } else {
            $this->kv('Pending Migrations', 'none');
        }
    }

    private function auditTableCounts(): void
    {
        $this->line('');
        $this->line('  Table Row Counts:');

        $tables = [
            'users',
            'event_forms',
            'form_fields',
            'submissions',
            'submission_answers',
            'units',
            'categories',
            'activities',
            'sessions',
            'cache',
            'jobs',
            'failed_jobs',
            'password_reset_tokens',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->line(sprintf('    - %-24s (table not found)', $table));

                continue;
            }

            $count = DB::table($table)->count();
            $this->line(sprintf('    - %-24s %d', $table, $count));
        }
    }

    private function auditUsers(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $this->line('');
        $this->line('  Users Overview:');

        $columns = Schema::getColumnListing('users');
        $this->kv('users columns', implode(', ', $columns));

        $hasRole = in_array('role', $columns, true);
        $hasUnitId = in_array('unit_id', $columns, true);
        $hasIsActive = in_array('is_active', $columns, true);
        $hasMustChangePassword = in_array('must_change_password', $columns, true);
        $hasLastLoginAt = in_array('last_login_at', $columns, true);

        $users = DB::table('users')
            ->select(array_values(array_unique(array_merge(
                ['id', 'name', 'email', 'created_at'],
                $hasRole ? ['role'] : [],
                $hasUnitId ? ['unit_id'] : [],
                $hasIsActive ? ['is_active'] : [],
                $hasMustChangePassword ? ['must_change_password'] : [],
                $hasLastLoginAt ? ['last_login_at'] : [],
            ))))
            ->orderBy('id')
            ->get();

        $this->kv('Total users', (string) $users->count());

        foreach ($users as $user) {
            $parts = [
                'id='.$user->id,
                'email='.$user->email,
                'name='.$user->name,
            ];

            if ($hasRole) {
                $parts[] = 'role='.($user->role ?? 'null');
            }

            if ($hasUnitId) {
                $parts[] = 'unit_id='.($user->unit_id ?? 'null');
            }

            if ($hasIsActive) {
                $parts[] = 'is_active='.(isset($user->is_active) ? ($user->is_active ? 'true' : 'false') : 'n/a');
            }

            if ($hasMustChangePassword) {
                $parts[] = 'must_change_password='.(isset($user->must_change_password) ? ($user->must_change_password ? 'true' : 'false') : 'n/a');
            }

            if ($hasLastLoginAt) {
                $parts[] = 'last_login_at='.($user->last_login_at ?? 'never');
            }

            $this->line('    - '.implode(' | ', $parts));
        }

        $this->line('');
        $this->line('  Initial Admin Accounts (expected by SIPRESKA spec):');

        foreach (self::INITIAL_ADMIN_EMAILS as $email) {
            $user = $users->firstWhere('email', $email);
            if ($user === null) {
                $this->line("    - {$email} => NOT FOUND");

                continue;
            }

            $statusParts = ['FOUND'];
            if ($hasRole) {
                $statusParts[] = 'role='.($user->role ?? 'null');
            }
            if ($hasUnitId) {
                $statusParts[] = 'unit_id='.($user->unit_id ?? 'null');
            }
            if ($hasIsActive) {
                $statusParts[] = 'is_active='.(isset($user->is_active) ? ($user->is_active ? 'true' : 'false') : 'n/a');
            }

            $this->line('    - '.$email.' => '.implode(' | ', $statusParts));
        }

        $legacyGateEmail = 'auloraqowiyboyqowboy@gmail.com';
        $legacyUser = $users->firstWhere('email', $legacyGateEmail);
        $this->line('');
        $this->kv(
            'Legacy Filament gate email ('.$legacyGateEmail.')',
            $legacyUser ? 'FOUND (id='.$legacyUser->id.')' : 'NOT FOUND'
        );
    }

    private function auditDuplicateEmails(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $duplicates = DB::table('users')
            ->select('email', DB::raw('COUNT(*) as total'))
            ->groupBy('email')
            ->having('total', '>', 1)
            ->get();

        $this->line('');
        if ($duplicates->isEmpty()) {
            $this->kv('Duplicate emails in users', 'none');
        } else {
            $this->warn('  Duplicate emails in users:');
            foreach ($duplicates as $row) {
                $this->line("    - {$row->email} ({$row->total} rows)");
            }
        }
    }

    private function auditDuplicateSlugs(): void
    {
        if (! Schema::hasTable('event_forms')) {
            return;
        }

        $duplicates = DB::table('event_forms')
            ->select('slug', DB::raw('COUNT(*) as total'))
            ->groupBy('slug')
            ->having('total', '>', 1)
            ->get();

        $this->line('');
        if ($duplicates->isEmpty()) {
            $this->kv('Duplicate slugs in event_forms', 'none');
        } else {
            $this->warn('  Duplicate slugs in event_forms:');
            foreach ($duplicates as $row) {
                $this->line("    - {$row->slug} ({$row->total} rows)");
            }
        }
    }

    private function auditRoutes(): void
    {
        $this->section('C. ROUTES');

        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'methods' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName() ?? '-',
                'action' => $route->getActionName(),
            ];
        });

        $this->line('  All Registered Routes ('.$routes->count().'):');
        foreach ($routes->sortBy('uri') as $route) {
            $this->line(sprintf(
                '    %-7s %-45s %-35s %s',
                $route['methods'],
                $route['uri'],
                $route['name'],
                $route['action']
            ));
        }

        $this->line('');
        $this->line('  Public Legacy Form Routes:');
        foreach ($routes->filter(fn (array $route) => in_array($route['name'], ['public-form.show', 'public-form.submit'], true)) as $route) {
            $this->line('    '.$route['methods'].' '.$route['uri'].' ('.$route['name'].')');
        }

        $catchAll = $routes->filter(fn (array $route) => $route['uri'] === '{slug}');
        $this->line('');
        $this->kv('Catch-all {slug} routes found', (string) $catchAll->count());

        $this->line('');
        $this->line('  Filament Routes:');
        foreach ($routes->filter(fn (array $route) => str_starts_with($route['uri'], 'admin') || str_contains($route['action'], 'Filament')) as $route) {
            $this->line('    '.$route['methods'].' '.$route['uri'].' ('.$route['name'].')');
        }

        $this->line('');
        $this->line('  Reserved SIPRESKA Target Routes (planned custom):');
        foreach ([
            'login', 'logout', 'dashboard', 'kegiatan', 'kalender',
            'manajemen-pengguna', 'master-kategori', 'master-unit',
            'log-aktivitas', 'absensi/{public_token}',
        ] as $planned) {
            $exists = $routes->contains(fn (array $route) => $route['uri'] === $planned);
            $this->line('    - /'.$planned.' => '.($exists ? 'ALREADY REGISTERED' : 'not yet registered'));
        }
    }

    private function auditLegacyForms(): void
    {
        $this->section('E. DATA FORM LAMA (event_forms)');

        if (! Schema::hasTable('event_forms')) {
            $this->warn('  event_forms table not found.');

            return;
        }

        $totalForms = EventForm::query()->count();
        $this->kv('Total event_forms', (string) $totalForms);

        if ($totalForms === 0) {
            $this->line('  No legacy forms found.');

            return;
        }

        $forms = EventForm::query()
            ->withCount(['fields', 'submissions'])
            ->orderBy('created_at')
            ->get();

        $this->line('');
        $this->line('  Detail per Form:');
        $this->line('  '.str_repeat('-', 100));

        foreach ($forms as $form) {
            $lastSubmission = Submission::query()
                ->where('event_form_id', $form->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('created_at')
                ->first();

            $lastSubmissionAt = $lastSubmission
                ? ($lastSubmission->submitted_at ?? $lastSubmission->created_at)?->timezone('Asia/Jakarta')->format('d F Y H:i:s').' WIB'
                : 'never';

            $collision = in_array(strtolower($form->slug), self::RESERVED_SLUGS, true) ? 'YES' : 'no';

            $this->line('  Slug           : '.$form->slug);
            $this->line('  Title          : '.$form->title);
            $this->line('  Active         : '.($form->is_active ? 'yes' : 'no'));
            $this->line('  Created At     : '.$form->created_at?->timezone('Asia/Jakarta')->format('d F Y H:i:s').' WIB');
            $this->line('  Updated At     : '.$form->updated_at?->timezone('Asia/Jakarta')->format('d F Y H:i:s').' WIB');
            $this->line('  Starts At      : '.($form->starts_at?->timezone('Asia/Jakarta')->format('d F Y H:i:s').' WIB' ?? '-'));
            $this->line('  Ends At        : '.($form->ends_at?->timezone('Asia/Jakarta')->format('d F Y H:i:s').' WIB' ?? '-'));
            $this->line('  Fields Count   : '.$form->fields_count);
            $this->line('  Submissions    : '.$form->submissions_count);
            $this->line('  Last Submission: '.$lastSubmissionAt);
            $this->line('  Reserved Collision: '.$collision);
            $this->line('  Public URL     : '.rtrim((string) config('app.url'), '/').'/'.$form->slug);
            $this->line('  '.str_repeat('-', 100));
        }

        $this->line('');
        $this->line('  Slug Collision Summary vs Reserved Routes:');
        $colliding = $forms->filter(fn (EventForm $form) => in_array(strtolower($form->slug), self::RESERVED_SLUGS, true));

        if ($colliding->isEmpty()) {
            $this->kv('Colliding slugs', 'none detected');
        } else {
            foreach ($colliding as $form) {
                $this->warn("    - {$form->slug} collides with reserved route name");
            }
        }
    }

    private function auditFilamentDependencies(): void
    {
        $this->section('F. FILAMENT DEPENDENCIES (inventory only)');

        $this->kv('Filament in composer.json', $this->composerRequiresFilament() ? 'yes' : 'no');
        $this->kv('AdminPanelProvider registered', $this->isFilamentProviderRegistered() ? 'yes' : 'no');

        $filamentFiles = collect(File::allFiles(app_path('Filament')))
            ->map(fn ($file) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()))
            ->sort()
            ->values();

        $this->kv('Custom Filament files in app/', (string) $filamentFiles->count());

        foreach ($filamentFiles as $file) {
            $this->line('    - '.$file);
        }

        $publicFilamentAssets = 0;
        if (File::isDirectory(public_path('js/filament'))) {
            $publicFilamentAssets += count(File::allFiles(public_path('js/filament')));
        }
        if (File::isDirectory(public_path('css/filament'))) {
            $publicFilamentAssets += count(File::allFiles(public_path('css/filament')));
        }

        $this->kv('Published Filament assets in public/', (string) $publicFilamentAssets);
    }

    private function auditSymlink(string $label, string $linkPath, string $expectedTarget): void
    {
        if (! file_exists($linkPath)) {
            $this->kv($label.' symlink', 'missing');

            return;
        }

        if (is_link($linkPath)) {
            $target = readlink($linkPath) ?: '';
            $resolved = realpath($linkPath) ?: $linkPath;
            $expectedResolved = realpath($expectedTarget) ?: $expectedTarget;
            $ok = $resolved === $expectedResolved || str_ends_with($resolved, str_replace('\\', '/', $expectedTarget));

            $this->kv($label.' is symlink', 'yes');
            $this->kv($label.' points to', $target);
            $this->kv($label.' resolves to', $resolved);
            $this->kv($label.' expected target', $expectedResolved);
            $this->kv($label.' symlink valid', $ok ? 'yes' : 'no');
        } else {
            $this->kv($label.' is symlink', 'no (regular file/directory)');
            $this->kv($label.' path', $linkPath);
        }
    }

    private function detectPublicHtmlPath(): ?string
    {
        $configured = env('SIPRESKA_PUBLIC_HTML_PATH');
        if (is_string($configured) && $configured !== '') {
            $resolved = realpath($configured);

            return $resolved !== false ? $resolved : $configured;
        }

        $candidates = [
            dirname(base_path()).'/public_html',
            base_path('../public_html'),
            '/home/'.get_current_user().'/domains/sipreska.poltekkesbanten.ac.id/public_html',
            '/home/'.get_current_user().'/public_html',
        ];

        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false) {
                return $resolved;
            }
        }

        return null;
    }

    private function composerRequiresFilament(): bool
    {
        $composerPath = base_path('composer.json');
        if (! file_exists($composerPath)) {
            return false;
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);

        return isset($composer['require']['filament/filament']);
    }

    private function isFilamentProviderRegistered(): bool
    {
        $providersPath = base_path('bootstrap/providers.php');
        if (! file_exists($providersPath)) {
            return false;
        }

        $contents = (string) file_get_contents($providersPath);

        return str_contains($contents, 'AdminPanelProvider');
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line('--------------------------------------------------------------------------------');
        $this->line(' '.$title);
        $this->line('--------------------------------------------------------------------------------');
    }

    private function kv(string $key, string $value): void
    {
        $this->line(sprintf('  %-28s: %s', $key, $value));
    }
}
