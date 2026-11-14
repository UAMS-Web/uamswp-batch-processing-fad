<?php

/**
 * WP_BP_Helper::render(), which includes a view file from the plugin's views directory.
 *
 * @see includes/class-bp-helper.php
 */

declare(strict_types=1);

it('reports a view that does not exist', function (): void {
    ob_start();
    WP_BP_Helper::render('no-such-view');

    expect(ob_get_clean())->toBe('View no-such-view not found');
});

it('renders a view with the data extracted into scope', function (): void {
    ob_start();
    WP_BP_Helper::render('greeting', ['name' => 'Batch']);

    expect(ob_get_clean())->toBe('Hello, Batch');
});
