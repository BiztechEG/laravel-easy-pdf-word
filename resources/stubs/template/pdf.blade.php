<x-doc::layout :doc="$doc" :title="$title">
    <h1 style="color: {{ $doc->theme('primary') }};">{{ $title }}</h1>

    <p>{{ $doc->t('intro') }}</p>
</x-doc::layout>
