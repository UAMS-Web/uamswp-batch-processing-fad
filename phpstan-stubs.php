<?php

declare(strict_types=1);

/**
 * Declarations PHPStan reads and never runs, for functions the plugin calls that its
 * stubs do not cover.
 *
 * `get_field()` and `update_field()` come from Advanced Custom Fields, a plugin the
 * Find-a-Doc site installs and this repository does not. Listed under `scanFiles` in
 * `phpstan.neon.dist`, so the file is not loaded at runtime or in the tests.
 */

/**
 * @param  string  $selector
 * @param  mixed  $post_id  ACF reads an id, a post or term object, or a string such as `term_5`.
 * @return mixed
 */
function get_field($selector, $post_id = false, $format_value = true)
{
    return null;
}

/**
 * @param  string  $selector
 * @param  mixed  $value
 * @param  mixed  $post_id  ACF reads an id, a post or term object, or a string such as `term_5`.
 */
function update_field($selector, $value, $post_id = false): bool
{
    return true;
}
