<?php

use BiztechEG\EasyPdfWord\Http\AuthorizePreview;
use BiztechEG\EasyPdfWord\Http\PreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware([...(array) config('easy-pdf-word.preview.middleware', ['web']), AuthorizePreview::class])
    ->prefix(trim((string) config('easy-pdf-word.preview.path', 'doc-preview'), '/'))
    ->name('easy-pdf-word.preview.')
    ->group(function () {
        Route::get('/', [PreviewController::class, 'index'])->name('index');
        Route::get('{template}', [PreviewController::class, 'show'])->name('show');
    });
