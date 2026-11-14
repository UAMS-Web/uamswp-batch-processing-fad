<?php

/**
 * Pest configuration, for the Unit suite.
 *
 * The Integration suite is class-based PHPUnit 9.6 under tests/Integration, run
 * from its own Composer setup; Pest never loads it.
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

/*
 * Every Unit test runs on UnitTestCase, which wraps it in Brain Monkey
 * (tests/Support/UnitTestCase.php). Pest reads this file before PHPUnit's bootstrap runs, and the
 * repository has no autoload-dev map, so the file is loaded here.
 */
require_once __DIR__.'/Support/UnitTestCase.php';

pest()->extend(UnitTestCase::class)->in('Unit');

/**
 * Forgets a singleton's instance, so the next get_instance() builds a fresh one and runs init() again.
 *
 * @param  class-string  $class  A class that uses WP_BP_Singleton.
 */
function forget_singleton(string $class): void
{
    $property = new ReflectionProperty($class, 'instance');
    $property->setValue(null, null);
}

/**
 * Queues the post ids each `new WP_Query` in a batch's setup() will return, in order, and stubs the
 * template functions the loop calls: get_the_ID() answers with the post the_post() last moved to,
 * and wp_reset_postdata() adds one to $GLOBALS['bp_reset_postdata_calls'].
 *
 * @param  array<int, int>  ...$idLists  One list of post ids per query, in the order the queries are built.
 */
function fake_post_queries(array ...$idLists): void
{
    WP_Query::$responses = $idLists;

    when('get_the_ID')->alias(fn (): int => WP_Query::$currentId);
    when('wp_reset_postdata')->alias(function (): void {
        $GLOBALS['bp_reset_postdata_calls']++;
    });
}

/**
 * Runs a batch's process() over an item built from the given data, returning what process() returned.
 *
 * @param  array<string, mixed>  $data
 */
function process_item(WP_Batch $batch, int|string $id, array $data): mixed
{
    return $batch->process(new WP_Batch_Item($id, $data));
}

/**
 * Runs a callback with an error handler that records every warning, and returns the messages.
 *
 * Some batches raise PHP warnings as they run (an undefined variable); failOnWarning would fail a
 * test that triggered one unrecorded, so a test that pins such a warning asserts it from this list.
 *
 * @return array<int, string>
 */
function record_warnings(callable $callback): array
{
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    }, E_WARNING);

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $warnings;
}

/**
 * Backs get_option(), update_option() and delete_option() with an array, so a batch's processed ids
 * persist across calls inside one test the way they do in wp_options.
 *
 * @param  array<string, mixed>  $store  Read and written by the stubs; the test inspects it afterward.
 */
function stub_options(array &$store): void
{
    when('get_option')->alias(function (string $name, $default = false) use (&$store) {
        return array_key_exists($name, $store) ? $store[$name] : $default;
    });
    when('update_option')->alias(function (string $name, $value) use (&$store): bool {
        $store[$name] = $value;

        return true;
    });
    when('delete_option')->alias(function (string $name) use (&$store): bool {
        unset($store[$name]);

        return true;
    });
}
