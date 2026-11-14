<?php

/**
 * Unit bootstrap.
 *
 * The Unit suite runs with no WordPress: WordPress functions are mocked per
 * test with Brain Monkey.
 */

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

// Patchwork first, so a test can redefine functions declared in files loaded after it.
require_once __DIR__.'/../vendor/antecedent/patchwork/Patchwork.php';

// Plugin files open with a guard on ABSPATH; point it at a directory that is not a WordPress install.
if (! defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir().'/uamswp-batch-processing-tests-no-wordpress/');
}

// The plugin's main file defines this; views resolve against it, so point it at a fixture plugin.
define('WP_BP_PATH', __DIR__.'/Fixtures/plugin/');

// Likewise for the URL the plugin builds its asset links from.
define('WP_BP_URL', 'https://example.test/wp-content/plugins/uamswp-batch-processing/');

// Stand-ins for the WordPress classes the plugin extends or receives.
require_once __DIR__.'/Fixtures/wordpress-classes.php';

// Load the files that define classes, never the main plugin file: it requires every batch and registers hooks at file scope.
// Some of these files register hooks as they load (a singleton's init()), so load them with Brain Monkey up.
Brain\Monkey\setUp();

// PHP 8 warns as the singleton trait compiles: a private __wakeup() (a user-level warning, kept in $GLOBALS so a
// test can pin it) and private final methods (a compile-time warning no handler receives, so it is silenced for the
// load and left to the pinning test's docblock).
$GLOBALS['bp_load_warnings'] = [];
set_error_handler(static function (int $level, string $message): bool {
    $GLOBALS['bp_load_warnings'][] = $message;

    return true;
}, E_WARNING | E_DEPRECATED);
$bpErrorReporting = error_reporting(error_reporting() & ~E_COMPILE_WARNING);

foreach ([
    'class-bp-helper',
    'class-bp-singleton',
    'class-batch-item',
    'class-batch',
    'class-batch-processor',
    'class-batch-processor-admin',
    'class-batch-ajax-handler',
    'class-batch-list-table',
] as $file) {
    require_once __DIR__.'/../includes/'.$file.'.php';
}

restore_error_handler();
error_reporting($bpErrorReporting);

// The batches under fad/ register themselves on a hook as they load, so load them with Brain Monkey up too.
// Their process() methods call error_log() on some paths; send it to a file instead of the test output.
ini_set('error_log', sys_get_temp_dir().'/uamswp-batch-processing-tests.log');

Brain\Monkey\setUp();

foreach (glob(__DIR__.'/../fad/class-*.php') as $file) {
    require_once $file;
}

Brain\Monkey\tearDown();

// A concrete batch for the tests; it extends WP_Batch, so it loads after the includes.
require_once __DIR__.'/Fixtures/FakeBatch.php';
require_once __DIR__.'/Fixtures/JsonSent.php';

Brain\Monkey\tearDown();

// cspell:ignore ABSPATH
