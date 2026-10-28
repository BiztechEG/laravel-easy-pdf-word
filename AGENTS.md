# AGENTS.md — biztecheg/laravel-easy-pdf-word

Guide for coding agents working in this repository. Fuller project history and
the roadmap live with the maintainer (BizTech); the rules below are the ones
that matter when changing code here.

## What this repo is

The Laravel package of Easy PDF & Word (PDF and Word in any language, first-class
Arabic, 12 ready-made templates) **and** the documentation site.

- Packagist: `biztecheg/laravel-easy-pdf-word` (latest v1.5.0). License MIT.
  PHP 8.2+, Laravel 12 and 13 (Laravel 11 dropped: Composer blocks it over
  security advisories).
- Built on the core [BiztechEG/easy-pdf-word](https://github.com/BiztechEG/easy-pdf-word)
  (`biztecheg/easy-pdf-word ^1.2` from Packagist, same namespace
  `BiztechEG\EasyPdfWord\`). Templates, engines, the Arabic layer and language
  files live in the core; change them there, release the core, then bump the
  constraint here.
- Docs site: https://easypdf.biztech-eg.com (source in `docs/`).

## Layout

- `src/EasyPdfWordServiceProvider.php`, `src/Facades/Doc.php`, `src/DocFactory.php`,
  `src/PendingDocument.php`, `src/PdfDocument.php`, `src/WordDocument.php`,
  `src/RenderedFile.php`, `src/ZipFile.php` — the `Doc` API (storage, download,
  mail attachments, `Doc::zip()`, `->queue()`).
- `src/Laravel/` — Blade view renderer, validator and HTTP client adapters for the core contracts.
- `src/Console/` — `doc:templates`, `doc:template` (copy), `doc:make-template`
  (plain PHP, `--blade` option), `doc:sample`.
- `src/Http/` — `/doc-preview` page (local only; elsewhere needs Gate `viewDocPreview`).
- `src/Jobs/SaveDocument.php` — encrypted queued save. `src/Testing/` — `Doc::fake()`.
- `config/easy-pdf-word.php`, `resources/stubs/`, `resources/views/`, `routes/`.
- `docs/` — VitePress 1.6.4 site, English at root, Arabic (MSA, RTL) in `docs/ar/`.

## Running tests

```bash
composer install          # in a cloud container use --prefer-source if zip downloads fail
vendor/bin/phpunit
# Chromium tests (need Puppeteer: PUPPETEER_SKIP_DOWNLOAD=1 npm i -g puppeteer)
DOC_CHROME_PATH=/path/to/chrome vendor/bin/phpunit tests/Feature/ChromiumTest.php
composer preview          # testbench serve on :8000, then open /doc-preview
```

Testing against an unreleased core: create `composer.local.json` with a path
repository pointing at `../easy-pdf-word`, then
`COMPOSER=composer.local.json composer update --prefer-dist`. Don't commit it.

CI (`tests.yml`): PHP 8.2–8.5 × Laravel 12/13, prefer-lowest, a Chromium job,
`composer validate --strict`, `composer audit`.

Tests that hit `/doc-preview` need `app.key`; a stray
`vendor/orchestra/testbench-core/laravel/.env` locally can hide that (CI has none).

## Docs site

```bash
cd docs && npm ci && npm run build   # or npm run dev
```

- Every page exists in English and Arabic; update both.
- Inline code gets `v-pre` via a markdown-it hook so Blade `{{ }}` is safe.
- Arabic RTL comes from `postcss-rtlcss` (`:where([dir])` prefixes); code blocks are `rtl:ignore`.
- `vitepress preview` caches the file list; restart it after a rebuild.
- `docs.yml` builds on PRs and, on push to `main`, deploys changed files to
  BizTech's server over FTP (vars `DOCS_FTP_SERVER`, `DOCS_FTP_DIR` with a
  trailing `/`, `DOCS_FTP_PROTOCOL=ftp`; secrets `DOCS_FTP_USERNAME`,
  `DOCS_FTP_PASSWORD`). Merging docs to `main` publishes them.
- Bump the version shown in the docs menu (`docs/.vitepress/config.mts`) in the release PR.

## Rules from the maintainer

- **Commits are authored as BizTech**: before committing run
  `git config user.name "BizTech"` and
  `git config user.email "28879302+amrsoft13@users.noreply.github.com"`.
  No `Co-Authored-By: Claude` / `Claude-Session` trailers in commit messages.
  Amending must keep BizTech as committer too.
- **Many small commits**, one per logical change; never one big commit.
- **Branch every PR from `main`**; no stacked PRs (they get merged into their base as-is).
- **Review after each phase** before starting the next.
- **Releases**: agents can't create tags/releases. Open a PR that dates the
  `CHANGELOG.md` entry and bumps the docs menu version, write release notes for
  BizTech, who publishes the GitHub release (Packagist updates itself).
- Repo settings (description, topics, About website) are changed by BizTech.

## Security defaults (keep them)

- Never `{!! !!}` user data in Blade; use `$doc->image()`, `Color::css()`, `e()`.
- Preview page: whitelisted query values, nonce CSP, sandboxed HTML view.
- CI keeps `permissions: contents: read`.
- Add a test to `tests/Feature/SecurityTest.php` for any new input path.

## Deliberately unchanged (don't "fix" without the maintainer)

- Singletons keep config in queue workers (scoped would drop `Doc::extend` engines).
- `images.paths` allows `storage_path('app')` (signatures are documented there).
- The VAT row shows at 0% on invoices; `->font()` accepts unregistered names;
  Word page numbers ignore the numerals setting; `mergeConfigFrom` is top-level only.
