<?php

namespace BiztechEG\EasyPdfWord\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The preview page is open in the "local" environment. Anywhere else it
 * needs the "viewDocPreview" gate, defined in a service provider:
 *
 *   Gate::define('viewDocPreview', fn ($user) => $user->isAdmin());
 */
class AuthorizePreview
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless(app()->environment('local') || Gate::allows('viewDocPreview'), 403);

        return $next($request);
    }
}
