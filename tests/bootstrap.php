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

// Load the files that define classes, never the main plugin file: it registers hooks at file scope.
require_once __DIR__.'/../includes/class-bp-helper.php';

// cspell:ignore ABSPATH
