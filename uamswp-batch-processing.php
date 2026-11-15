<?php

declare(strict_types=1);
/*
Plugin Name: UAMSWP Batch Processing
Plugin URI: https://github.com/UAMS-Web/uamswp-batch-processing-fad
Description: Batch Processing for WordPress. Find-a-doc
Requires at least: 6.8
Requires PHP: 8.2
Version: 1.0.2.1
Author: UAMS Web Services, based on work by Darko Gjorgjijoski
Author URI: https://communications.uams.edu/web/
License: GPL-2+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

/*
 * Copyright (C) 2020-2026 UAMS Web Services (modifications)
 *
 * This is a modified version of WP Batch Processing by Darko Gjorgjijoski
 * (https://github.com/gdarko/wp-batch-processing). It is free software under
 * the GNU General Public License, version 2 or any later version; see LICENSE.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! is_admin()) {
    return;
}

define('WP_BP_PATH', plugin_dir_path(__FILE__));
define('WP_BP_URL', plugin_dir_url(__FILE__));

require_once __DIR__.'/includes/class-bp-helper.php';
require_once __DIR__.'/includes/class-bp-singleton.php';
require_once __DIR__.'/includes/class-batch-item.php';
require_once __DIR__.'/includes/class-batch.php';
require_once __DIR__.'/includes/class-batch-processor.php';
require_once __DIR__.'/includes/class-batch-ajax-handler.php';
require_once __DIR__.'/includes/class-batch-list-table.php';
require_once __DIR__.'/includes/class-batch-processor-admin.php';

// Batches
require_once __DIR__.'/fad/class-condition-batch.php';
require_once __DIR__.'/fad/class-treatment-batch.php';
require_once __DIR__.'/fad/class-providers-batch.php';
require_once __DIR__.'/fad/class-locations-batch.php';
require_once __DIR__.'/fad/class-aoes-batch.php';
require_once __DIR__.'/fad/class-condition-content-batch.php';
require_once __DIR__.'/fad/class-treatment-content-batch.php';
require_once __DIR__.'/fad/class-condition-sync-batch.php';
require_once __DIR__.'/fad/class-treatment-sync-batch.php';
require_once __DIR__.'/fad/class-providers-region-batch.php';
require_once __DIR__.'/fad/class-providers-filter-batch.php';
require_once __DIR__.'/fad/class-providers-nrc-cache.php';
