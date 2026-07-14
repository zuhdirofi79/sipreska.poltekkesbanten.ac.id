<div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    <p class="font-medium">Fitur {{ $feature }} sementara tidak dapat dimuat.</p>
    <p class="mt-1 text-amber-800">Route aplikasi belum sinkron. Administrator server perlu menjalankan:</p>
    <pre class="mt-2 overflow-x-auto rounded bg-white/70 p-2 text-xs text-gray-700">php artisan optimize:clear
php artisan route:cache
php artisan view:cache</pre>
</div>
