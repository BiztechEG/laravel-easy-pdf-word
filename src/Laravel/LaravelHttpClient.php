<?php

namespace BiztechEG\EasyPdfWord\Laravel;

use BiztechEG\EasyPdfWord\Contracts\HttpClient;
use BiztechEG\EasyPdfWord\Support\HttpResponse;
use Closure;
use Illuminate\Http\Client\Factory;

/**
 * Laravel's Http client for Gotenberg, so Http::fake() and its assertions
 * see the requests.
 */
class LaravelHttpClient implements HttpClient
{
    /** @param  Closure(): Factory  $http  resolved per request, so a fake set later is used */
    public function __construct(private Closure $http) {}

    public function postMultipart(string $url, array $fields, array $files, int $timeout): HttpResponse
    {
        $request = ($this->http)()->timeout($timeout);

        foreach ($files as $file) {
            $request->attach($file['name'], $file['contents'], $file['filename']);
        }

        $response = $request->post($url, $fields);

        return new HttpResponse($response->status(), $response->body());
    }
}
