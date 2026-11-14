<?php

declare(strict_types=1);
/********************************************************************
 * Copyright (C) 2019 Darko Gjorgjijoski (https://darkog.com)
 *
 * This file is part of WP Batch Processing
 *
 * WP Batch Processing is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * WP Batch Processing is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WP Batch Processing. If not, see <https://www.gnu.org/licenses/>.
 **********************************************************************/

// The direct-access guard runs as the file loads, before any test, and dies when it fires, so no test can reach it
// in this process; tests/Unit/LoadTimeTest.php pins it in a subprocess.
// @pest-mutate-ignore: RemoveNot
if (! defined('ABSPATH')) { // @pest-mutate-ignore: IfNegated
    exit('Direct access is not allowed.');
}

/**
 * Class WP_Batch_Processor_Admin
 */
class WP_Batch_Processor_Admin
{
    use WP_BP_Singleton;

    const NONCE = 'wp-batch-processing';

    /**
     * The capability required to open the batches page and to run a batch over ajax.
     */
    const CAPABILITY = 'manage_options';

    /**
     * The hook suffix of the batches page, as returned by add_menu_page().
     *
     * @var string
     */
    // A property default is fixed as the class loads, before any test; AdminTest.php shows its effect (no script
    // is enqueued before the menu is added).
    // @pest-mutate-ignore: EmptyStringToNotEmpty
    private $page_hook = '';

    /**
     * Kick-in the class
     *
     * @return void
     */
    protected function init()
    {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('init', [$this, 'setup'], 0);
    }

    /**
     * Init hook. Only run when it is on its own pages.
     */
    public function setup(): void
    {
        if ($this->is_batch_runner_screen() || $this->is_batch_runner_ajax()) {
            do_action('wp_batch_processing_init');
        }
    }

    /**
     * Enqueues admin scripts
     *
     * @param  string  $hook_suffix  The hook suffix of the admin page being rendered.
     */
    public function enqueue_scripts($hook_suffix = ''): void
    {

        // Only print the runner (and its nonce) on the batches page itself, for a user who can open it.
        // The $_GET check alone is not enough: any admin screen can carry page=dg-batches in its query string.
        if ($this->page_hook === '' || $hook_suffix !== $this->page_hook) {
            return;
        }

        if (! current_user_can(self::CAPABILITY)) {
            return;
        }

        if (! $this->is_batch_runner_screen('view')) {
            return;
        }

        wp_enqueue_script(
            'wp-batch-processing',
            WP_BP_URL.'assets/processor.js',
            ['jquery'],
            filemtime(WP_BP_PATH.'assets/processor.js'),
            true
        );
        wp_localize_script('wp-batch-processing', 'DgBatchRunner', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp-batch-processing'),
            'batch_id' => $_GET['id'] ?? 0,
            'delay' => apply_filters('wp_batch_processing_delay', 0), // Set delay in seconds before processing the next item. Default 0. No delay.
            'text' => [
                'processing' => __('Processing...', 'wp-batch-processing'),
                'start' => __('Start', 'wp-batch-processing'),
            ],
        ]);
    }

    /**
     * Add menu items
     */
    public function admin_menu(): void
    {

        $this->page_hook = add_menu_page(
            __('Manage Batches', 'wp-batch-processing'),
            __('Batches ', 'wp-batch-processing'),
            self::CAPABILITY,
            'dg-batches',
            [$this, 'plugin_page'],
            'dashicons-grid-view',
            null
        );

        add_submenu_page(
            'dg-batches',
            __('Batches', 'wp-batch-processing'),
            __('Batches', 'wp-batch-processing'),
            self::CAPABILITY,
            'dg-batches',
            [$this, 'plugin_page']
        );
    }

    /**
     * Handles the plugin page
     */
    public function plugin_page(): void
    {
        $action = $_GET['action'] ?? 'list';
        $id = $_GET['id'] ?? 0;
        $view = match ($action) {
            'view' => 'batch-view',
            default => 'batch-list',
        };

        WP_BP_Helper::render($view, ['id' => $id]);
    }

    /**
     * Returns true if the it is ajax action
     */
    private function is_batch_runner_ajax(): bool
    {
        return isset($_REQUEST['action']) && $_REQUEST['action'] === 'dg_process_next_batch_item' || isset($_REQUEST['action']) && $_REQUEST['action'] === 'dg_restart_batch';
    }

    /**
     * Returns true if the current screen is batch runners one.
     *
     * @param  string|null  $action
     * @return bool
     */
    private function is_batch_runner_screen($action = null)
    {
        $is_main_screen = isset($_GET['page']) && $_GET['page'] === 'dg-batches';
        if (! is_null($action)) {
            $is_main_screen = $is_main_screen && isset($_GET['action']) && $_GET['action'] === $action;
        }

        return $is_main_screen;
    }
}

// Hooks the admin up as the file loads, before any test; tests/Unit/LoadTimeTest.php pins it in a subprocess.
WP_Batch_Processor_Admin::get_instance(); // @pest-mutate-ignore: RemoveMethodCall
