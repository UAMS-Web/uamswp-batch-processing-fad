<?php

/**
 * WP_Batch_Processor_Admin: the menu, the page router, the script gate, and the hook that
 * registers batches only on the plugin's own screens.
 *
 * @see includes/class-batch-processor-admin.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Actions\expectAdded;
use function Brain\Monkey\Actions\expectDone;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\when;

/**
 * Builds a fresh admin instance, as WordPress does once at load.
 */
function fresh_admin(): WP_Batch_Processor_Admin
{
    forget_singleton(WP_Batch_Processor_Admin::class);

    return WP_Batch_Processor_Admin::get_instance();
}

beforeEach(function (): void {
    $_GET = [];
    $_REQUEST = [];
});

afterEach(function (): void {
    $_GET = [];
    $_REQUEST = [];
    forget_singleton(WP_Batch_Processor_Admin::class);
});

it('names the nonce action and the capability the AJAX handler checks', function (): void {
    expect(WP_Batch_Processor_Admin::NONCE)->toBe('wp-batch-processing')
        ->and(WP_Batch_Processor_Admin::CAPABILITY)->toBe('manage_options');
});

it('hooks the menu, the script gate and the setup when it is built', function (): void {
    $callbackNamed = fn (string $method) => Mockery::on(
        fn ($callback): bool => is_array($callback) && $callback[0] instanceof WP_Batch_Processor_Admin && $callback[1] === $method
    );
    expectAdded('admin_menu')->once()->with($callbackNamed('admin_menu'));
    expectAdded('admin_enqueue_scripts')->once()->with($callbackNamed('enqueue_scripts'));
    expectAdded('init')->once()->with($callbackNamed('setup'), 0);

    fresh_admin();
});

describe('setup', function (): void {
    it('registers batches on the batches screen', function (): void {
        $_GET['page'] = 'dg-batches';
        expectDone('wp_batch_processing_init')->once();

        fresh_admin()->setup();
    });

    it('registers batches for the process and restart ajax actions', function (string $action): void {
        $_REQUEST['action'] = $action;
        expectDone('wp_batch_processing_init')->once();

        fresh_admin()->setup();
    })->with(['process' => ['dg_process_next_batch_item'], 'restart' => ['dg_restart_batch']]);

    it('registers nothing on any other screen', function (): void {
        $_GET['page'] = 'some-other-page';
        expectDone('wp_batch_processing_init')->never();

        fresh_admin()->setup();
    });

    it('registers nothing for any other ajax action', function (): void {
        $_REQUEST['action'] = 'heartbeat';
        expectDone('wp_batch_processing_init')->never();

        fresh_admin()->setup();
    });

    it('registers nothing on a request that names no page and no action', function (): void {
        expectDone('wp_batch_processing_init')->never();

        fresh_admin()->setup();
    });
});

describe('admin_menu', function (): void {
    it('adds the Batches page and its submenu entry for the capability', function (): void {
        Functions\expect('add_menu_page')->once()->with(
            'Manage Batches',
            'Batches ',
            'manage_options',
            'dg-batches',
            Mockery::on(fn ($callback): bool => is_array($callback) && $callback[1] === 'plugin_page'),
            'dashicons-grid-view',
            null
        )->andReturn('toplevel_page_dg-batches');
        Functions\expect('add_submenu_page')->once()->with(
            'dg-batches',
            'Batches',
            'Batches',
            'manage_options',
            'dg-batches',
            Mockery::on(fn ($callback): bool => is_array($callback) && $callback[1] === 'plugin_page')
        );

        fresh_admin()->admin_menu();
    });
});

describe('plugin_page', function (): void {
    it('renders the list view by default', function (): void {
        ob_start();
        fresh_admin()->plugin_page();

        expect(ob_get_clean())->toBe('batch-list:0');
    });

    it('renders the single-batch view for the view action, with the id from the query string', function (): void {
        $_GET = ['action' => 'view', 'id' => 'fake'];

        ob_start();
        fresh_admin()->plugin_page();

        expect(ob_get_clean())->toBe('batch-view:fake');
    });

    it('renders the list view for any other action, passing the id along', function (): void {
        $_GET = ['action' => 'delete', 'id' => 'fake'];

        ob_start();
        fresh_admin()->plugin_page();

        expect(ob_get_clean())->toBe('batch-list:fake');
    });
});

describe('enqueue_scripts', function (): void {
    beforeEach(function (): void {
        when('admin_url')->alias(fn (string $path): string => 'https://example.test/wp-admin/'.$path);
        when('wp_create_nonce')->alias(fn (string $action): string => 'nonce-for-'.$action);
        when('current_user_can')->justReturn(true);

        $_GET = ['page' => 'dg-batches', 'action' => 'view', 'id' => 'fake'];
    });

    /**
     * An admin whose menu was added, so it knows its page hook.
     */
    $admin_with_menu = function (): WP_Batch_Processor_Admin {
        when('add_menu_page')->justReturn('toplevel_page_dg-batches');
        when('add_submenu_page')->justReturn('toplevel_page_dg-batches');

        $admin = fresh_admin();
        $admin->admin_menu();

        return $admin;
    };

    it('enqueues the runner and its data on the batches page for a user who can open it', function () use ($admin_with_menu): void {
        $admin = $admin_with_menu();

        // The version is the script file's own modification time, so give the file a time no
        // directory can share, then put the real one back.
        $script = WP_BP_PATH.'assets/processor.js';
        $original_mtime = filemtime($script);
        touch($script, 1500000000);
        clearstatcache();
        $pinned_mtime = filemtime($script);
        expect($pinned_mtime)->not->toBe(filemtime(dirname($script)));

        try {
            Functions\expect('wp_enqueue_script')->once()->with(
                'wp-batch-processing',
                'https://example.test/wp-content/plugins/uamswp-batch-processing/assets/processor.js',
                ['jquery'],
                $pinned_mtime,
                true
            );
            expectApplied('wp_batch_processing_delay')->once()->with(0)->andReturn(3);
            Functions\expect('wp_localize_script')->once()->with('wp-batch-processing', 'DgBatchRunner', [
                'ajax_url' => 'https://example.test/wp-admin/admin-ajax.php',
                'nonce' => 'nonce-for-wp-batch-processing',
                'batch_id' => 'fake',
                'delay' => 3,
                'text' => ['processing' => 'Processing...', 'start' => 'Start'],
            ]);

            $admin->enqueue_scripts('toplevel_page_dg-batches');
        } finally {
            touch($script, $original_mtime);
            clearstatcache();
        }
    });

    it('sends batch id 0 when the query string carries none', function () use ($admin_with_menu): void {
        $admin = $admin_with_menu();
        unset($_GET['id']);
        when('wp_enqueue_script')->justReturn();
        Functions\expect('wp_localize_script')->once()->with(
            'wp-batch-processing',
            'DgBatchRunner',
            Mockery::on(fn (array $data): bool => $data['batch_id'] === 0)
        );

        $admin->enqueue_scripts('toplevel_page_dg-batches');
    });

    it('enqueues nothing before the menu has been added', function (): void {
        Functions\expect('wp_enqueue_script')->never();

        fresh_admin()->enqueue_scripts('toplevel_page_dg-batches');
    });

    it('enqueues nothing before the menu has been added, even for an empty hook suffix', function (): void {
        Functions\expect('wp_enqueue_script')->never();

        fresh_admin()->enqueue_scripts();
    });

    it('enqueues nothing on a different admin screen, whatever the query string says', function () use ($admin_with_menu): void {
        $admin = $admin_with_menu();
        Functions\expect('wp_enqueue_script')->never();

        $admin->enqueue_scripts('index.php');
    });

    it('enqueues nothing when no hook suffix is passed', function () use ($admin_with_menu): void {
        $admin = $admin_with_menu();
        Functions\expect('wp_enqueue_script')->never();

        $admin->enqueue_scripts();
    });

    it('enqueues nothing for a user without the capability', function () use ($admin_with_menu): void {
        $admin = $admin_with_menu();
        when('current_user_can')->justReturn(false);
        Functions\expect('wp_enqueue_script')->never();

        $admin->enqueue_scripts('toplevel_page_dg-batches');
    });

    it('enqueues nothing on the list view of the batches page', function () use ($admin_with_menu): void {
        $admin = $admin_with_menu();
        $_GET['action'] = 'list';
        Functions\expect('wp_enqueue_script')->never();

        $admin->enqueue_scripts('toplevel_page_dg-batches');
    });

    it('enqueues nothing when the page parameter is not the batches page', function () use ($admin_with_menu): void {
        $admin = $admin_with_menu();
        $_GET['page'] = 'elsewhere';
        Functions\expect('wp_enqueue_script')->never();

        $admin->enqueue_scripts('toplevel_page_dg-batches');
    });

    it('enqueues nothing when the view action is missing', function () use ($admin_with_menu): void {
        $admin = $admin_with_menu();
        unset($_GET['action']);
        Functions\expect('wp_enqueue_script')->never();

        $admin->enqueue_scripts('toplevel_page_dg-batches');
    });
});
