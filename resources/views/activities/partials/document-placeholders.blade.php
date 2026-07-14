@php
    $sections = [
        ['key' => 'invitation', 'label' => 'Surat Undangan', 'desc' => 'Unggah surat undangan resmi kegiatan.'],
        ['key' => 'attendance', 'label' => 'Daftar Hadir', 'desc' => 'Form presensi publik akan tersedia setelah kegiatan disimpan.'],
        ['key' => 'minutes', 'label' => 'Notulensi', 'desc' => 'Unggah berita acara / notulensi rapat.'],
        ['key' => 'documentation', 'label' => 'Dokumentasi', 'desc' => 'Foto, banner, poster, dan materi visual kegiatan.'],
        ['key' => 'publication', 'label' => 'Publikasi', 'desc' => 'Tautan publikasi di media sosial atau website.'],
    ];
@endphp

<div class="mt-8 space-y-4">
    <h3 class="text-base font-semibold text-gray-900">Kelengkapan Dokumen</h3>
    <p class="text-sm text-gray-500">Setelah kegiatan dibuat, Anda dapat melengkapi dokumen pada halaman edit.</p>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($sections as $section)
            <div class="sipreska-card border-dashed p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium text-gray-900">{{ $section['label'] }}</div>
                        <p class="mt-1 text-xs text-gray-500">{{ $section['desc'] }}</p>
                    </div>
                    <x-badge variant="gray">Dapat dilengkapi setelah kegiatan disimpan</x-badge>
                </div>
            </div>
        @endforeach
    </div>
</div>
