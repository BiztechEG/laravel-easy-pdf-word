<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default locale
    |--------------------------------------------------------------------------
    |
    | The locale used when a document does not call ->locale(). RTL locales
    | (ar, fa, ur, he, ...) switch the whole document to right-to-left.
    | null means "use the application locale".
    |
    */

    'locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Numerals
    |--------------------------------------------------------------------------
    |
    | "latin" prints 0123456789, "arabic" prints ٠١٢٣٤٥٦٧٨٩ in the document
    | text. Only text is converted: tags, attributes and CSS are left alone.
    |
    */

    'numerals' => 'latin',

    'pdf' => [

        /*
        |----------------------------------------------------------------------
        | PDF engine
        |----------------------------------------------------------------------
        |
        | Supported: "mpdf" (pure PHP, works on shared hosting), "chromium"
        | (alias of "browsershot"), "gotenberg". Switch per document with
        | ->driver('chromium').
        |
        */

        'driver' => env('DOC_PDF_DRIVER', 'mpdf'),

        /*
        | When the selected engine is not installed or fails, retry with this
        | one. Set to null to disable the fallback.
        */

        'fallback' => env('DOC_PDF_FALLBACK', 'mpdf'),

        'paper' => 'A4',

        'orientation' => 'portrait',

        // Millimetres: top, right, bottom, left.
        'margins' => [15, 15, 15, 15],

        'drivers' => [

            'mpdf' => [
                'temp_dir' => null, // null = system temp dir
                'use_kashida' => 75,
                // Pick fonts per script automatically, for documents that mix
                // Arabic with Chinese, Hindi, etc. Ignores font-family in CSS.
                'auto_lang_to_font' => false,
            ],

            'browsershot' => [
                'node_binary' => env('DOC_NODE_BINARY'),
                'npm_binary' => env('DOC_NPM_BINARY'),
                'chrome_path' => env('DOC_CHROME_PATH'),
                'no_sandbox' => env('DOC_CHROME_NO_SANDBOX', false),
                // Templates need no JavaScript; turn it on only for documents that draw with it (charts).
                'javascript' => env('DOC_CHROME_JAVASCRIPT', false),
                'timeout' => 60,
            ],

            'gotenberg' => [
                'url' => env('DOC_GOTENBERG_URL', 'http://localhost:3000'),
                'timeout' => 60,
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | Cairo, Tajawal and Noto Naskh Arabic ("naskh") ship with the package (SIL Open Font License).
    | "default" is used for RTL documents and "default_ltr" for the rest.
    | Register your own fonts under "custom":
    |
    |   'my-font' => [
    |       'regular' => resource_path('fonts/MyFont-Regular.ttf'),
    |       'bold'    => resource_path('fonts/MyFont-Bold.ttf'),
    |   ],
    |
    */

    'fonts' => [
        'default' => 'cairo',
        'default_ltr' => 'cairo',
        'custom' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Local images (logo, signature, stamp, ...) are read only from these
    | folders, so a file path that arrives in user data cannot embed other
    | files from the server. Set "paths" to null to allow any folder.
    |
    | Image URLs are downloaded by the server (the PDF engine or PhpWord), so
    | they are off by default. "remote" is true for any URL, or the hosts to
    | allow: ['cdn.example.com', '*.amazonaws.com'], or in .env
    | DOC_REMOTE_IMAGES=cdn.example.com,*.amazonaws.com
    |
    */

    'images' => [
        'paths' => [
            public_path(),
            storage_path('app'),
            resource_path(),
        ],
        'remote' => env('DOC_REMOTE_IMAGES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Word
    |--------------------------------------------------------------------------
    |
    | Word files (needs phpoffice/phpword) do not embed fonts, so use a font
    | that is installed on your readers' machines and covers Arabic: Arial,
    | Tahoma, Times New Roman, Simplified Arabic, Sakkal Majalla ...
    |
    */

    'word' => [
        'font' => env('DOC_WORD_FONT', 'Arial'),
        'font_size' => 11,
    ],

    /*
    |--------------------------------------------------------------------------
    | Preview page
    |--------------------------------------------------------------------------
    |
    | A page at /doc-preview that shows every template with its sample data,
    | in any language, digits and engine, with PDF and Word downloads.
    | null turns it on in the "local" environment only. Outside "local" it
    | also needs the "viewDocPreview" gate, e.g. in AppServiceProvider:
    |
    |   Gate::define('viewDocPreview', fn ($user) => $user->isAdmin());
    |
    */

    'preview' => [
        'enabled' => env('DOC_PREVIEW'),
        'path' => 'doc-preview',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | Folders searched for templates, first match wins. The project folder
    | comes before the templates shipped with the package, so a copied
    | template with the same name overrides the original.
    |
    */

    'templates' => [
        'paths' => [
            resource_path('doc-templates'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | Default look for the ready-made templates. Override per document with
    | ->theme([...]).
    |
    */

    'theme' => [
        'primary' => '#0F766E',
        'text' => '#1F2937',
        'muted' => '#6B7280',
        'border' => '#E5E7EB',
        'logo' => null,
        'company' => [
            'name' => env('APP_NAME'),
            'address' => null,
            'phone' => null,
            'email' => null,
            'tax_number' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currencies for tafqeet (amounts in words)
    |--------------------------------------------------------------------------
    |
    | Add or override currencies. Each unit lists its Arabic forms:
    | [singular, dual, plural (3-10), accusative (11-99)] and its gender.
    |
    */

    'currencies' => [],

];
