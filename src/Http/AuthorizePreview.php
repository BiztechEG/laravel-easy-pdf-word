<?php

namespace BiztechEG\EasyPdfWord\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The preview page is open in the "local" environment, unless
 * preview.local_open is false. Anywhere else it needs the "viewDocPreview"
 * gate, defined in a service provider:
 *
 *   Gate::define('viewDocPreview', fn ($user) => $user->isAdmin());
 */
class AuthorizePreview
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($this->openInLocal() || Gate::allows('viewDocPreview'), 403);

        return $next($request);
    }

    private function openInLocal(): bool
    {
        // Unset or empty (DOC_PREVIEW_LOCAL_OPEN=): open, as before.
        $open = config('easy-pdf-word.preview.local_open');

        return app()->environment('local') && ($open === null || $open === '' || filter_var($open, FILTER_VALIDATE_BOOLEAN));
    }
}
