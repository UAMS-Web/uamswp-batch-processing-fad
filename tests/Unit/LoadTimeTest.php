<?php

/**
 * What the includes files do as they load, which no in-process test can reach.
 *
 * Each file opens with a guard that dies unless ABSPATH is defined, and four of them act at file
 * scope (the list table requires WordPress' parent class; three singletons build themselves).
 * The Unit bootstrap has already loaded them all, so each case here runs a fresh PHP process
 * and reads what it printed. The lines these cases pin carry `@pest-mutate-ignore` in the plugin
 * source: a mutation run mutates the files inside the test process only, so a subprocess cannot
 * kill a mutant, and the reason is recorded here instead.
 *
 * @see includes/class-bp-singleton.php
 * @see includes/class-batch-list-table.php
 */

declare(strict_types=1);

/**
 * Runs PHP code in a fresh process and returns what it printed and how it exited.
 *
 * Only fatal errors are reported, so the compile-time warnings the singleton trait draws on PHP 8
 * stay out of the output being compared.
 *
 * @return array{output: string, status: int}
 */
function php_subprocess(string $code): array
{
    $process = proc_open(
        [PHP_BINARY, '-d', 'error_reporting=1', '-d', 'display_errors=1', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['output' => $output, 'status' => proc_close($process)];
}

/**
 * The absolute path of a file under includes/, as PHP source for a string literal.
 */
function includes_path(string $file): string
{
    return var_export(dirname(__DIR__, 2).'/includes/'.$file.'.php', true);
}

dataset('includes files', [
    'class-batch-ajax-handler',
    'class-batch-item',
    'class-batch-list-table',
    'class-batch-processor-admin',
    'class-batch-processor',
    'class-batch',
    'class-bp-helper',
    'class-bp-singleton',
]);

it('dies with a message when an includes file is loaded outside WordPress', function (string $file): void {
    $result = php_subprocess('include '.includes_path($file).'; echo "NOT REACHED";');

    expect($result['output'])->toBe('Direct access is not allowed.')
        ->and($result['status'])->toBe(0);
})->with('includes files');

it('loads an includes file quietly once ABSPATH is defined', function (): void {
    $result = php_subprocess("define('ABSPATH', '/tmp/'); include ".includes_path('class-batch-item').'; echo class_exists("WP_Batch_Item") ? "loaded" : "missing";');

    expect($result['output'])->toBe('loaded');
});

it('registers the admin hooks as the admin file loads', function (): void {
    $code = "define('ABSPATH', '/tmp/'); \$GLOBALS['hooks'] = [];"
        .'function add_action($name, $callback, $priority = 10) { $GLOBALS["hooks"][] = $name.":".$callback[1].":".$priority; }'
        .'include '.includes_path('class-bp-singleton').'; include '.includes_path('class-batch-processor-admin').';'
        .'echo implode(",", $GLOBALS["hooks"]);';

    expect(php_subprocess($code)['output'])->toBe('admin_menu:admin_menu:10,admin_enqueue_scripts:enqueue_scripts:10,init:setup:0');
});

it('registers the ajax actions as the ajax handler file loads', function (): void {
    $code = "define('ABSPATH', '/tmp/'); \$GLOBALS['hooks'] = [];"
        .'function add_action($name, $callback, $priority = 10) { $GLOBALS["hooks"][] = $name.":".$callback[1]; }'
        .'include '.includes_path('class-bp-singleton').'; include '.includes_path('class-batch-ajax-handler').';'
        .'echo implode(",", $GLOBALS["hooks"]);';

    expect(php_subprocess($code)['output'])->toBe('wp_ajax_dg_process_next_batch_item:process_next_item,wp_ajax_dg_restart_batch:restart_batch');
});

it('builds the batch registry as the processor file loads', function (): void {
    $code = "define('ABSPATH', '/tmp/');"
        .'include '.includes_path('class-bp-singleton').'; include '.includes_path('class-batch-processor').';'
        .'echo (new ReflectionProperty("WP_Batch_Processor", "instance"))->getValue() instanceof WP_Batch_Processor ? "built" : "not built";';

    expect(php_subprocess($code)['output'])->toBe('built');
});

describe('the list table file', function (): void {
    it('requires WordPress\' list table class when it is not loaded yet', function (): void {
        $abspath = sys_get_temp_dir().'/bp-load-time-'.bin2hex(random_bytes(4)).'/';
        mkdir($abspath.'wp-admin/includes', 0777, true);
        file_put_contents($abspath.'wp-admin/includes/class-wp-list-table.php', '<?php class WP_List_Table { const SOURCE = "wordpress"; public function __construct($args = []) {} }');

        $code = 'define("ABSPATH", '.var_export($abspath, true).');'
            .'include '.includes_path('class-batch-list-table').';'
            .'echo WP_List_Table::SOURCE, ":", get_parent_class("WP_BP_List_Table");';

        $result = php_subprocess($code);

        unlink($abspath.'wp-admin/includes/class-wp-list-table.php');
        rmdir($abspath.'wp-admin/includes');
        rmdir($abspath.'wp-admin');
        rmdir($abspath);

        expect($result['output'])->toBe('wordpress:WP_List_Table');
    });

    it('leaves the list table class alone when it is already loaded', function (): void {
        // ABSPATH points at a directory with no wp-admin: a require would be a fatal error.
        $code = 'define("ABSPATH", "/nonexistent/");'
            .'class WP_List_Table { const SOURCE = "preloaded"; public function __construct($args = []) {} }'
            .'include '.includes_path('class-batch-list-table').';'
            .'echo WP_List_Table::SOURCE;';

        $result = php_subprocess($code);

        expect($result['output'])->toBe('preloaded');
    });
});
