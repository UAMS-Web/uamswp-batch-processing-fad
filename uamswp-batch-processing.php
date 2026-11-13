<?php
/*
Plugin Name: UAMSWP Batch Processing
Plugin URI: https://github.com/gdarko/wp-batch-processing
Description: Batch Processing for WordPress. Find-a-doc 
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

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! is_admin() ) {
	return;
}

define( 'WP_BP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_BP_URL', plugin_dir_url( __FILE__ ) );

require_once 'includes/class-bp-helper.php';
require_once 'includes/class-bp-singleton.php';
require_once 'includes/class-batch-item.php';
require_once 'includes/class-batch.php';
require_once 'includes/class-batch-processor.php';
require_once 'includes/class-batch-ajax-handler.php';
require_once 'includes/class-batch-list-table.php';
require_once 'includes/class-batch-processor-admin.php';

// Batches
require_once 'fad/class-condition-batch.php';
require_once 'fad/class-treatment-batch.php';
require_once 'fad/class-providers-batch.php';
require_once 'fad/class-locations-batch.php';
require_once 'fad/class-aoes-batch.php';
require_once 'fad/class-condition-content-batch.php';
require_once 'fad/class-treatment-content-batch.php';
require_once 'fad/class-condition-sync-batch.php';
require_once 'fad/class-treatment-sync-batch.php';
require_once 'fad/class-providers-region-batch.php';
require_once 'fad/class-providers-filter-batch.php';
require_once 'fad/class-providers-nrc-cache.php';