<x-doc::layout :doc="$doc" :title="$title">
    <x-slot:styles>
        <style>
            h1 { color: {{ $doc->theme('primary', '#0F766E') }}; }
        </style>
    </x-slot:styles>

    <h1>{{ $title }}</h1>

    <p>{{ $doc->t('intro') }}</p>
</x-doc::layout>
