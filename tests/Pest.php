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
    WP_Query::$responses = array_values($idLists);

    when('get_the_ID')->alias(fn (): int => WP_Query::$currentId);
    when('wp_reset_postdata')->alias(function (): void {
        $GLOBALS['bp_reset_postdata_calls']++;
    });
}

/**
 * Builds the batch a dataset names.
 *
 * A dataset hands a test its class as a plain string, so `new $class` reaches the analyzer as an
 * unknown type; this returns a WP_Batch it can follow, and refuses a class that is not one.
 *
 * @param  string  $class  A WP_Batch subclass.
 */
function batch_of(string $class): WP_Batch
{
    if (! is_a($class, WP_Batch::class, true)) {
        throw new InvalidArgumentException($class.' is not a WP_Batch.');
    }

    return new $class;
}

/**
 * Returns the batch's next unprocessed item, failing the test when there is none.
 */
function next_item(WP_Batch $batch): WP_Batch_Item
{
    return $batch->get_next_item() ?: throw new LogicException('The batch has no item left to process.');
}

/**
 * Opens a subprocess, failing the test when the process cannot start.
 *
 * @param  list<string>  $command
 * @param  mixed  $stdout  Overwritten, whatever it held.
 * @param  mixed  $stderr  Overwritten, whatever it held.
 *
 * @param-out resource $stdout  Set to the read end of the process's standard output.
 * @param-out resource $stderr  Set to the read end of the process's standard error.
 *
 * @return resource
 */
function open_process(array $command, &$stdout, &$stderr)
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes) ?: throw new RuntimeException('proc_open failed.');
    $stdout = $pipes[1];
    $stderr = $pipes[2];

    return $process;
}

/**
 * Calls the init function a dataset names, failing the test when no such function exists.
 */
function run_init(string $function): void
{
    if (! is_callable($function)) {
        throw new InvalidArgumentException($function.' is not a function.');
    }

    $function();
}

/**
 * Returns the class a dataset names, failing the test when no such class exists.
 *
 * @return class-string
 */
function class_named(string $class): string
{
    return class_exists($class) ? $class : throw new InvalidArgumentException($class.' is not a class.');
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
