@props(['doc', 'title' => null])
<!DOCTYPE html>
<html lang="{{ $doc->locale }}" dir="{{ $doc->direction }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @if ($doc->usesCssFonts())
        {!! $doc->fontCss !!}
        @endif
        body {
            font-family: '{{ $doc->font }}', sans-serif;
            direction: {{ $doc->direction }};
            color: {{ $doc->theme('text', '#1F2937') }};
            font-size: 10.5pt;
            line-height: 1.5;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        th, td { vertical-align: top; }
        h1, h2, h3 { margin: 0; line-height: 1.3; }
        p { margin: 0 0 6pt 0; }
        .text-start { text-align: {{ $doc->start() }}; }
        .text-end { text-align: {{ $doc->end() }}; }
        .text-center { text-align: center; }
        .muted { color: {{ $doc->theme('muted', '#6B7280') }}; }
        .ltr { direction: ltr; unicode-bidi: embed; }
        .nowrap { white-space: nowrap; }
    </style>
    {{ $styles ?? '' }}
</head>
<body>
{{ $slot }}
</body>
</html>
