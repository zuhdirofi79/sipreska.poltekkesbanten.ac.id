<?php

namespace App\Console\Commands;

use App\Services\FileStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckDocStorageCommand extends Command
{
    protected $signature = 'sipreska:check-doc-storage {--count-files : Hitung jumlah file per folder}';

    protected $description = 'Read-only health check for SIPRESKA document storage under public/docs.';

    public function handle(FileStorageService $fileStorage): int
    {
        $this->line('================================================================================');
        $this->line(' SIPRESKA DOCUMENT STORAGE CHECK (READ-ONLY)');
        $this->line(' Generated at: '.now()->timezone('Asia/Jakarta')->format('d F Y H:i:s').' WIB');
        $this->line('================================================================================');
        $this->newLine();

        $documentRoot = $fileStorage->documentRoot();
        $diskRoot = dirname($documentRoot);
        $prefix = trim((string) config('sipreska.document_path_prefix', 'docs'), '/');

        $this->info('Configuration');
        $this->line("  Document root : {$documentRoot}");
        $this->line('  Disk          : '.config('sipreska.document_disk', 'sipreska'));
        $this->line("  Public root   : {$diskRoot}");
        $this->line("  Path prefix   : {$prefix}");
        $this->newLine();

        $healthy = true;

        if (! File::isDirectory($documentRoot)) {
            $this->error("  [FAIL] Document root tidak ditemukan: {$documentRoot}");
            $healthy = false;
        } elseif (! is_writable($documentRoot)) {
            $this->error("  [FAIL] Document root tidak writable: {$documentRoot}");
            $healthy = false;
        } else {
            $this->line("  [OK] Document root exists & writable");
        }

        $htaccess = $documentRoot.DIRECTORY_SEPARATOR.'.htaccess';

        if (! File::exists($htaccess)) {
            $this->warn('  [WARN] .htaccess tidak ditemukan di document root');
        } elseif (! str_contains(File::get($htaccess), 'Options -Indexes')) {
            $this->warn('  [WARN] .htaccess tidak memblokir directory listing');
        } else {
            $this->line('  [OK] .htaccess directory listing protection');
        }

        $this->newLine();
        $this->info('Subfolders');

        foreach (config('sipreska.document_folders', []) as $type => $folder) {
            $path = $documentRoot.DIRECTORY_SEPARATOR.$folder;
            $exists = File::isDirectory($path);
            $writable = $exists && is_writable($path);

            if (! $exists) {
                $this->warn("  [WARN] {$folder} — belum ada (akan dibuat saat upload)");
            } elseif (! $writable) {
                $this->error("  [FAIL] {$folder} — tidak writable");
                $healthy = false;
            } else {
                $this->line("  [OK] {$folder}");
            }

            if ($this->option('count-files') && $exists) {
                $files = File::allFiles($path);
                $this->line('       files: '.count($files));
            }
        }

        $this->newLine();
        $this->info('Security scan (executable extensions)');

        $blocked = config('sipreska.blocked_extensions', []);
        $foundExecutable = 0;

        if (File::isDirectory($documentRoot)) {
            foreach (File::allFiles($documentRoot) as $file) {
                $extension = strtolower($file->getExtension());

                if (in_array($extension, $blocked, true)) {
                    $this->error('  [FAIL] Executable/blocklisted file: '.$file->getRelativePathname());
                    $foundExecutable++;
                    $healthy = false;
                }
            }
        }

        if ($foundExecutable === 0) {
            $this->line('  [OK] Tidak ada file executable/blocklisted');
        }

        $this->newLine();
        $this->line($healthy ? 'STATUS: HEALTHY' : 'STATUS: ISSUES FOUND');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
