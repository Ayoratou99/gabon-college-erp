<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;

/**
 * Compile every .blade.php file in the repo and assert the compiled PHP is
 * syntactically valid. Catches the class of bug where a multi-line PHP array
 * literal inside a Blade directive (e.g. @json([...])) trips the compiler's
 * regex and produces malformed PHP — the failure mode that gave us
 * "Unclosed [ does not match )" on grid.blade.php / wizard.blade.php.
 */

it('compiles every blade file to syntactically valid PHP', function (): void {
    $roots = [
        base_path('resources/views'),
        base_path('Modules'),
    ];

    $compiler = Blade::getFacadeRoot()->getEngineResolver()->resolve('blade')->getCompiler();

    $broken = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $finder = (new Finder())->files()->in($root)->name('*.blade.php');
        foreach ($finder as $file) {
            $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            try {
                $source   = (string) file_get_contents($file->getRealPath());
                $compiled = $compiler->compileString($source);
                // php -l equivalent via token_get_all in error mode.
                $tokens = @token_get_all('<?php ' . $compiled, TOKEN_PARSE);
                if ($tokens === false || ! is_array($tokens) || count($tokens) === 0) {
                    $broken[] = $relative . ' (token_get_all failed)';
                }
            } catch (\ParseError $e) {
                $broken[] = $relative . ' :: ParseError: ' . $e->getMessage();
            } catch (\Throwable $e) {
                $broken[] = $relative . ' :: ' . get_class($e) . ': ' . $e->getMessage();
            }
        }
    }

    expect($broken)->toBe([], "Broken Blade files:\n" . implode("\n", $broken));
});
