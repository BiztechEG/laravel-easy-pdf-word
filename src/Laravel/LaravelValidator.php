<?php

namespace BiztechEG\EasyPdfWord\Laravel;

use BiztechEG\EasyPdfWord\Contracts\Validator;
use Closure;
use Illuminate\Contracts\Validation\Factory;

/**
 * Laravel's validator, so templates may use any Laravel rule and failures
 * are Laravel's ValidationException, with the app's messages and language.
 */
class LaravelValidator implements Validator
{
    /** @param  Closure(): Factory  $validator  resolved per check */
    public function __construct(private Closure $validator) {}

    public function validate(array $data, array $rules): void
    {
        ($this->validator)()->make($data, $rules)->validate();
    }
}
