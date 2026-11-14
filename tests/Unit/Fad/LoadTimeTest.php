<?php

/**
 * What each batch file under fad/ does as it loads, which no in-process test can reach.
 *
 * A batch file opens with a guard that dies unless ABSPATH is defined, defines its class only
 * when WP_Batch exists, and registers its init function on `wp_batch_processing_init` at
 * priority 15 with one argument. The Unit bootstrap has already loaded every file, so each
 * case here runs a fresh PHP process and reads what it printed. The lines these cases pin carry
 * `@pest-mutate-ignore` in the plugin source: a mutation run mutates the files inside the test
 * process only, so a subprocess cannot kill a mutant, and the reason is recorded here instead.
 *
 * @see tests/Unit/LoadTimeTest.php for the same technique on includes/.
 */

declare(strict_types=1);

/**
 * Runs PHP code in a fresh process and returns what it printed.
 */
function fad_subprocess(string $code): string
{
    $process = open_process(
        [PHP_BINARY, '-d', 'error_reporting=1', '-d', 'display_errors=1', '-r', $code],
        $stdout,
        $stderr
    );

    $output = (string) stream_get_contents($stdout);
    fclose($stdout);
    fclose($stderr);
    proc_close($process);

    return $output;
}

/**
 * PHP source that loads the base classes a batch file extends, with add_action() recording its calls.
 */
function fad_base_classes(): string
{
    $includes = dirname(__DIR__, 3).'/includes/';

    return "define('ABSPATH', '/tmp/'); \$GLOBALS['hooks'] = [];"
        .'function add_action($name, $callback, $priority = 10, $args = 1) { $GLOBALS["hooks"][] = $name.":".$callback.":".$priority.":".$args; }'
        .'foreach (["class-bp-singleton", "class-batch-item", "class-batch", "class-batch-processor"] as $file) {'
        .' include '.var_export($includes, true).'.$file.".php"; }';
}

dataset('fad files', function () {
    foreach (glob(dirname(__DIR__, 3).'/fad/class-*.php') ?: throw new RuntimeException('No batch files found under fad/.') as $path) {
        $found = preg_match("/add_action\(\s*'wp_batch_processing_init',\s*'(\w+)',\s*15,\s*1\s*\);/", (string) file_get_contents($path), $matches);
        if ($found !== 1) {
            throw new RuntimeException($path.' does not register its init function.');
        }

        yield basename($path) => [$path, $matches[1]];
    }
});

it('dies with a message when the file is loaded outside WordPress', function (string $path): void {
    expect(fad_subprocess('include '.var_export($path, true).'; echo "NOT REACHED";'))->toBe('Direct access is not allowed.');
})->with('fad files');

it('registers its init function on wp_batch_processing_init at priority 15 with one argument', function (string $path, string $init): void {
    $code = fad_base_classes().' include '.var_export($path, true).'; echo implode(",", $GLOBALS["hooks"]);';

    expect(fad_subprocess($code))->toBe("wp_batch_processing_init:{$init}:15:1");
})->with('fad files');

it('defines nothing and registers nothing when WP_Batch is not loaded', function (string $path, string $init): void {
    $code = "define('ABSPATH', '/tmp/'); \$GLOBALS['hooks'] = [];"
        .'function add_action($name, $callback) { $GLOBALS["hooks"][] = $name; }'
        .'include '.var_export($path, true).';'
        .'echo count($GLOBALS["hooks"]), ":", function_exists('.var_export($init, true).') ? "defined" : "absent";';

    expect(fad_subprocess($code))->toBe('0:absent');
})->with('fad files');
