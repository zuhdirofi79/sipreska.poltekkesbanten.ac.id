@props([
    'context' => 'web',
])

@php
    use App\Support\AttendanceLetterhead;

    $letterheadExists = AttendanceLetterhead::exists();
    $imageSrc = $context === 'pdf'
        ? AttendanceLetterhead::pdfSrc()
        : AttendanceLetterhead::assetUrl();
@endphp

<header {{ $attributes->class(['public-attendance-letterhead-wrapper']) }}>
    @if ($letterheadExists && $imageSrc)
        <img
            src="{{ $imageSrc }}"
            alt="Kop Kementerian Kesehatan Poltekkes Banten"
            class="attendance-letterhead"
        >
    @else
        <div class="attendance-letterhead-fallback" role="img" aria-label="Kop Kementerian Kesehatan Poltekkes Banten">
            <p class="attendance-letterhead-fallback-line">KEMENTERIAN KESEHATAN</p>
            <p class="attendance-letterhead-fallback-line">POLTEKKES KEMENKES BANTEN</p>
        </div>
    @endif
</header>
