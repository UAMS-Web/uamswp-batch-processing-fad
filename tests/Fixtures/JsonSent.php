<?php

/**
 * Stands in for the end of a request.
 *
 * wp_send_json_success() and wp_send_json_error() print JSON and then stop the request
 * (wp_die()), and the plugin's AJAX handlers rely on that: they follow some calls with exit.
 * A test replaces both functions with ones that throw this, so the handler stops at the
 * same point and the test reads what would have been sent.
 */

declare(strict_types=1);

final class JsonSent extends RuntimeException
{
    /** @param mixed $data */
    public function __construct(public readonly bool $success, public readonly mixed $data, public readonly ?int $status)
    {
        parent::__construct($success ? 'success' : 'error');
    }
}
