<?php

namespace BiztechEG\EasyPdfWord\Tests\Feature;

use BiztechEG\EasyPdfWord\Exceptions\ValidationFailed;
use BiztechEG\EasyPdfWord\Templates\TemplateRegistry;
use BiztechEG\EasyPdfWord\Tests\TestCase;
use BiztechEG\EasyPdfWord\Validation\RuleValidator;
use Illuminate\Support\Facades\Validator;

/**
 * The plain PHP validator must agree with Laravel's on every bundled
 * template: the sample data, and the sample with each field broken in
 * many ways, give the same fields and the same messages.
 */
class RuleValidatorParityTest extends TestCase
{
    private const BAD_VALUES = [
        'missing', null, '', '   ', 'abc', 'T21', 'T7', 12345, -5, 0, 1.5, '7', true, false, [], ['x'], [['y' => 1]],
        '2026-13-45', '2026-02-30', '2026-10', '2026/10/08', 'cheque', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    ];

    public function test_bundled_templates_validate_like_laravel(): void
    {
        $registry = $this->app->make(TemplateRegistry::class);
        $cases = 0;

        foreach ($registry->all() as $name => $template) {
            $rules = $template->rules();
            $sample = array_replace_recursive($template->defaults(), $template->sample());

            $this->assertSame([], $this->laravel($sample, $rules), "{$name} sample fails Laravel's rules.");
            $this->assertSame([], $this->ours($sample, $rules), "{$name} sample fails the plain PHP rules.");

            foreach ($this->paths($sample, $rules) as $path) {
                foreach (self::BAD_VALUES as $bad) {
                    $data = $this->with($sample, $path, $bad);
                    $expected = $this->laravel($data, $rules);

                    // Laravel itself crashes on a few of these (an array where after_or_equal looks for a date).
                    if ($expected === null) {
                        $this->ours($data, $rules);

                        continue;
                    }

                    $this->assertSame(
                        $expected,
                        $this->ours($data, $rules),
                        "{$name}: {$path} = ".var_export($bad, true),
                    );
                    $cases++;
                }
            }
        }

        $this->assertGreaterThan(1000, $cases);
    }

    /** @return list<string> every field a rule names, with wildcards filled from the sample, and their parents */
    private function paths(array $sample, array $rules): array
    {
        $paths = [];

        foreach (array_keys($rules) as $pattern) {
            foreach ($this->expand($sample, explode('.', $pattern)) as $path) {
                $parts = explode('.', $path);

                for ($i = 1; $i <= count($parts); $i++) {
                    $paths[implode('.', array_slice($parts, 0, $i))] = true;
                }
            }
        }

        return array_keys($paths);
    }

    private function expand(array $data, array $segments, array $prefix = []): array
    {
        if ($segments === []) {
            return [implode('.', $prefix)];
        }

        $segment = array_shift($segments);

        if ($segment !== '*') {
            return $this->expand($data, $segments, [...$prefix, $segment]);
        }

        $list = data_get($data, implode('.', $prefix));

        return is_array($list) && $list !== []
            ? array_merge(...array_map(fn ($key) => $this->expand($data, $segments, [...$prefix, (string) $key]), array_keys($list)))
            : [implode('.', [...$prefix, '0'])];
    }

    private function with(array $data, string $path, mixed $value): array
    {
        if ($value === 'missing') {
            $parts = explode('.', $path);
            $last = array_pop($parts);
            $parent = &$data;

            foreach ($parts as $part) {
                if (! is_array($parent) || ! array_key_exists($part, $parent)) {
                    return $data;
                }

                $parent = &$parent[$part];
            }

            if (is_array($parent)) {
                unset($parent[$last]);
            }

            return $data;
        }

        data_set($data, $path, $value);

        return $data;
    }

    private function laravel(array $data, array $rules): ?array
    {
        try {
            return Validator::make($data, $rules)->errors()->toArray();
        } catch (\TypeError) {
            return null;
        }
    }

    private function ours(array $data, array $rules): array
    {
        try {
            (new RuleValidator)->validate($data, $rules);

            return [];
        } catch (ValidationFailed $e) {
            return $e->errors();
        }
    }
}
