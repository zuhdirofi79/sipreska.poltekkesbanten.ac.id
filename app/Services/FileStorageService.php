<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileStorageService
{
    public function disk(): string
    {
        return (string) config('sipreska.document_disk', 'sipreska');
    }

    public function documentRoot(): string
    {
        return (string) config('sipreska.document_root', public_path('docs'));
    }

    public function folderForType(string $type): string
    {
        $folders = config('sipreska.document_folders', []);

        if (! isset($folders[$type])) {
            throw new RuntimeException("Tipe dokumen tidak dikenal: {$type}");
        }

        return (string) $folders[$type];
    }

    public function activityDirectory(string $type, int $activityId): string
    {
        $prefix = trim((string) config('sipreska.document_path_prefix', 'docs'), '/');
        $folder = $this->folderForType($type);

        return "{$prefix}/{$folder}/{$activityId}";
    }

    public function ensureActivityDirectory(string $type, int $activityId): string
    {
        $directory = $this->activityDirectory($type, $activityId);
        $absolute = $this->absolutePath($directory);

        if (! File::isDirectory($absolute)) {
            File::makeDirectory($absolute, 0755, true);
        }

        if (! is_writable($absolute)) {
            throw new RuntimeException("Folder dokumen tidak dapat ditulis: {$directory}");
        }

        return $directory;
    }

    public function ensureDocumentStructure(): void
    {
        $root = $this->documentRoot();

        if (! File::isDirectory($root)) {
            File::makeDirectory($root, 0755, true);
        }

        foreach (config('sipreska.document_folders', []) as $folder) {
            $path = $root.DIRECTORY_SEPARATOR.$folder;

            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }

        $htaccess = $root.DIRECTORY_SEPARATOR.'.htaccess';

        if (! File::exists($htaccess)) {
            File::put($htaccess, $this->defaultHtaccessContents());
        }
    }

    public function storeUploadedFile(UploadedFile $file, string $type, int $activityId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $maxKb = (int) config('sipreska.upload_max_kb', 5120);

        $this->assertAllowedFile($type, $extension, $mime, $file->getSize(), $maxKb);

        $directory = $this->ensureActivityDirectory($type, $activityId);
        $safeName = Str::uuid()->toString().'.'.$extension;
        $path = $directory.'/'.$safeName;

        Storage::disk($this->disk())->putFileAs($directory, $file, $safeName);

        return [
            'disk' => $this->disk(),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $file->getSize(),
        ];
    }

    public function storeBinary(string $binary, string $type, int $activityId, string $extension, string $mimeType): array
    {
        $extension = strtolower($extension);
        $maxKb = (int) config('sipreska.upload_max_kb', 5120);

        $this->assertAllowedFile($type, $extension, $mimeType, strlen($binary), $maxKb);

        $directory = $this->ensureActivityDirectory($type, $activityId);
        $safeName = Str::uuid()->toString().'.'.$extension;
        $path = $directory.'/'.$safeName;

        Storage::disk($this->disk())->put($path, $binary);

        return [
            'disk' => $this->disk(),
            'path' => $path,
            'original_name' => $safeName,
            'mime_type' => $mimeType,
            'size' => strlen($binary),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $safePath = $this->resolveSafePath($path);

        if (Storage::disk($this->disk())->exists($safePath)) {
            Storage::disk($this->disk())->delete($safePath);
        }
    }

    public function purgeActivityFiles(int $activityId): void
    {
        foreach (config('sipreska.document_folders', []) as $type => $folder) {
            $directory = $this->activityDirectory($type, $activityId);
            $absolute = $this->absolutePath($directory);

            if (File::isDirectory($absolute)) {
                File::deleteDirectory($absolute);
            }
        }
    }

    public function resolveSafePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if ($normalized === '' || str_contains($normalized, '..') || str_contains($normalized, "\0")) {
            throw new RuntimeException('Path tidak valid.');
        }

        return ltrim($normalized, '/');
    }

    public function absolutePath(string $relativePath): string
    {
        $safePath = $this->resolveSafePath($relativePath);
        $publicRoot = dirname($this->documentRoot());

        return $publicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $safePath);
    }

    public function validateReadablePath(string $path): string
    {
        $safePath = $this->resolveSafePath($path);
        $prefix = trim((string) config('sipreska.document_path_prefix', 'docs'), '/');

        if (! str_starts_with($safePath, $prefix.'/')) {
            throw new RuntimeException('Path tidak valid.');
        }

        $relativeFromDocs = substr($safePath, strlen($prefix) + 1);
        $allowedFolders = array_values(config('sipreska.document_folders', []));
        $topFolder = explode('/', $relativeFromDocs)[0] ?? '';

        if (! in_array($topFolder, $allowedFolders, true)) {
            throw new RuntimeException('Path tidak valid.');
        }

        if (! app()->environment('testing')) {
            $absolute = $this->absolutePath($safePath);
            $realFile = realpath($absolute);
            $realRoot = realpath($this->documentRoot());

            if ($realFile === false || $realRoot === false) {
                throw new RuntimeException('File tidak ditemukan.');
            }

            $rootPrefix = rtrim(str_replace('\\', '/', $realRoot), '/').'/';
            $normalizedFile = str_replace('\\', '/', $realFile);

            if (! str_starts_with($normalizedFile.'/', $rootPrefix) && $normalizedFile !== rtrim($rootPrefix, '/')) {
                throw new RuntimeException('Path tidak valid.');
            }
        }

        return $safePath;
    }

    public function stream(
        string $path,
        string $mimeType,
        string $filename,
        bool $inline = false,
        ?string $disk = null,
    ): StreamedResponse {
        try {
            $safePath = $this->validateReadablePath($path);
        } catch (RuntimeException) {
            abort(404);
        }

        $disk ??= $this->disk();

        if (! Storage::disk($disk)->exists($safePath)) {
            abort(404);
        }

        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.addslashes($filename).'"';

        return Storage::disk($disk)->response($safePath, $filename, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assertAllowedFile(string $type, string $extension, string $mime, int $size, int $maxKb): void
    {
        $blocked = config('sipreska.blocked_extensions', []);

        if (in_array($extension, $blocked, true)) {
            throw new RuntimeException('Ekstensi file tidak diizinkan.');
        }

        $allowedExtensions = config("sipreska.allowed_extensions.{$type}", []);

        if (! in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Ekstensi file tidak diizinkan.');
        }

        $allowedMimes = config("sipreska.allowed_mimes.{$type}", []);

        if (! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Jenis file tidak diizinkan.');
        }

        if ($size > ($maxKb * 1024)) {
            throw new RuntimeException('Ukuran file melebihi batas.');
        }
    }

    private function defaultHtaccessContents(): string
    {
        return <<<'HTACCESS'
Options -Indexes

<FilesMatch "\.(php|phtml|phar|cgi|pl|py|sh|htaccess)$">
    Require all denied
</FilesMatch>
HTACCESS;
    }
}
