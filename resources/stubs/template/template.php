<?php

return [
    'title' => '{{ name }}',
    'description' => '',
    'locales' => ['ar', 'en'],

    // Paper settings; remove to use the config defaults.
    'paper' => 'A4',
    'orientation' => 'portrait',

    // Laravel validation rules for the data passed with ->data([...]).
    'fields' => [
        'title' => ['required', 'string'],
    ],

    // Values used when the developer does not pass them.
    'defaults' => [],

    // Example data for previews and tests.
    'sample' => [
        'title' => 'مستند جديد',
    ],
];
