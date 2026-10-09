# Fonts

Choose the font of a document, add your own fonts, fix font files that mPDF cannot read, and pick the font Word files use.

## Bundled fonts {#bundled}

Three Arabic fonts come with the package, each with a regular and a bold weight, under the SIL Open Font License:

| Name in code | Font | Character |
| --- | --- | --- |
| `cairo` | Cairo | Modern sans-serif, the default for every document |
| `tajawal` | Tajawal | Light, rounded sans-serif |
| `naskh` | Noto Naskh Arabic | Traditional Naskh, for letters and contracts |

All three include Latin letters, so English text and mixed text need no other font.

## Choose a font {#choose}

For one document:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::template('letter', $data)->locale('ar')->font('naskh')->pdf();
```

For the whole app, in `config/easy-pdf-word.php`:

```php
'fonts' => [
    'default' => 'naskh',       // right-to-left documents
    'default_ltr' => 'cairo',   // all other documents
    'custom' => [],
],
```

`->font()` takes the name of a bundled or registered font, in any case (`naskh`, `Naskh`). A name with other characters than letters, digits, spaces, `-` and `_` throws `Invalid font name [...]`. A valid name that is not registered does not throw: mPDF quietly uses its own DejaVu Sans instead, so check the spelling when a document comes out in the wrong font.

### In your own CSS {#css}

In your own views and HTML, use the same names in `font-family`:

```blade
<h1 style="font-family: 'tajawal'">عرض سعر</h1>
<p style="font-family: 'naskh'">نص العرض بخط النسخ.</p>
```

Every bundled or registered font can be used this way, with every engine. mPDF reads the font files itself; for Chromium and Gotenberg the package embeds each registered font your page's CSS names as an `@font-face` rule. Embedding adds the whole font files to the HTML (a few hundred KB each), so name only the fonts you use. Views and the layout component are covered in [Blade views and HTML](/guide/views-and-html).

## Your own fonts {#custom-fonts}

Put the `.ttf` files in your app, for example in `resources/fonts`, and register them under `fonts.custom`:

```php
// config/easy-pdf-word.php
'fonts' => [
    'default' => 'almarai',
    'default_ltr' => 'cairo',
    'custom' => [
        'almarai' => [
            'regular' => resource_path('fonts/Almarai-Regular.ttf'),
            'bold' => resource_path('fonts/Almarai-Bold.ttf'),
        ],
        'ibm-plex' => [
            'regular' => resource_path('fonts/IBMPlexSansArabic-Regular.ttf'),
            'bold' => resource_path('fonts/IBMPlexSansArabic-Bold.ttf'),
            'italic' => resource_path('fonts/IBMPlexSansArabic-Italic.ttf'),
            'bold_italic' => resource_path('fonts/IBMPlexSansArabic-BoldItalic.ttf'),
            'arabic_separators' => true,
        ],
    ],
],
```

| Key | Required | Meaning |
| --- | --- | --- |
| `regular` | Yes | The regular font file. Without it: `Font [name] needs at least a "regular" file.` |
| `bold`, `italic`, `bold_italic` | No | The other styles. Without a bold file, mPDF prints bold text in the regular weight. |
| `arabic_separators` | No | `true` when the font draws `٫` and `٬` clearly, so documents with Arabic digits use them (١٢٬٥٠٠٫٧٥). See [Arabic support](/guide/arabic#separators). |
| `arabic` | No | `false` for a font without Arabic letters. With `auto_lang_to_font` on, Arabic text then falls back to Cairo. Defaults to `true`. |

Names are matched without case. A custom font with the name of a bundled one (`cairo`) replaces it.

mPDF caches the data it reads from font files in a `fonts-<hash>` folder inside its [`temp_dir`](/guide/configuration#mpdf), one folder per version of the registered font files. When you add a font, or replace or edit a font file, the next document uses a new folder and reads the fonts again, so there is no cache to clear. Older `fonts-...` folders are no longer used and can be deleted.

You can also register a font in code, in a service provider's `boot()`:

```php
use BiztechEG\EasyPdfWord\Facades\Doc;

Doc::fonts()->register('almarai', [
    'regular' => resource_path('fonts/Almarai-Regular.ttf'),
    'bold' => resource_path('fonts/Almarai-Bold.ttf'),
]);
```

mPDF finds font files by their file name, so two fonts cannot use files with the same name from different folders (`fonts/a/Regular.ttf` and `fonts/b/Regular.ttf`). That fails with `The font files [...] and [...] have the same name. mPDF finds fonts by file name, so rename one of them.`

mPDF reads TrueType outlines only. A font with PostScript outlines (most `.otf` files) fails with `Fonts with postscript outlines are not supported`; use the `.ttf` version of the font.

## Fix fonts that mPDF cannot read {#mpdf-font-fix}

mPDF cannot read some features of recent fonts, including many Google Fonts. The render stops with one of these errors:

```text
Font "almarai" contains MarkGlyphSets which is not supported
This font [almarai] contains MarkGlyphSets - Not tested yet
GPOS Lookup Type 5, Format 3 not supported (ttfontsuni.php).
```

The package includes a script that fixes the font files once. It needs Python 3 and fontTools:

```bash
python3 -m pip install fonttools
python3 vendor/biztecheg/laravel-easy-pdf-word/bin/mpdf-font-fix.py resources/fonts/Almarai-*.ttf
```

```text
resources/fonts/Almarai-Bold.ttf: fixed
resources/fonts/Almarai-Regular.ttf: fixed
```

The script changes the files in place, so keep a copy of the originals. What it does:

- **MarkGlyphSets**: removes the optional mark glyph sets and goes back to the older font table version. Shaping and the placing of harakat keep working.
- **Lookup Type 5, Format 3**: rewrites these contextual substitutions as the equivalent chained form that mPDF supports. The letters come out the same.

A file that needs no change prints `nothing to change`. The bundled fonts are already fixed this way. Chromium and Gotenberg read fonts without these limits, so they need no fixing.

## Fonts in Word files {#word}

Word files do not embed fonts: Word shows the text with a font installed on the reader's computer. So the fonts above apply to PDFs only, and Word files use the font from `DOC_WORD_FONT`, Arial by default:

```dotenv
DOC_WORD_FONT="Sakkal Majalla"
```

Pick a font that your readers have and that covers Arabic: Arial, Tahoma, Times New Roman, Simplified Arabic, Traditional Arabic or Sakkal Majalla on Windows. The size is set by `word.font_size` in the config (11 by default). More about Word output in [Word files](/guide/word).

## Fonts with Chromium and Gotenberg {#chromium}

Chromium and Gotenberg get the document font embedded in the HTML as an `@font-face` rule with the font file inside it. They need no fonts installed on the server, and headers and footers use the same font. Any other registered font that the page's CSS names is embedded the same way; see [In your own CSS](#css).

## Documents in several scripts {#auto-lang-to-font}

A document that mixes Arabic with a script that its font does not have, such as Chinese or Devanagari, shows empty boxes for those letters. With mPDF, turn on `auto_lang_to_font` in the config:

```php
// config/easy-pdf-word.php
'pdf' => [
    // ...
    'drivers' => [
        'mpdf' => [
            // ...
            'auto_lang_to_font' => true,
        ],
        // ...
    ],
],
```

mPDF then picks a font per script from its own fonts, while Arabic text keeps the document font. It ignores `font-family` in your CSS, so leave it off for other documents.
