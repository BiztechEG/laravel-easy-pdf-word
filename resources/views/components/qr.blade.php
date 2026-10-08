@props(['value', 'size' => '28mm'])
<img src="{{ \BiztechEG\EasyPdfWord\Support\Qr::dataUri((string) $value) }}" alt="QR" style="width: {{ $size }}; height: {{ $size }};" {{ $attributes }}>
