<?php

/**
 * Pest configuration, for the Unit suite.
 *
 * The Integration suite is class-based PHPUnit 9.6 under tests/Integration, run
 * from its own Composer setup; Pest never loads it.
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/*
 * Brain Monkey around every test.
 *
 * setUp() defines WordPress' hook functions and arms Patchwork; tearDown()
 * verifies every expectation the test set (a `->once()` that never happened
 * fails here) and removes every mock, so nothing leaks into the next test.
 *
 * The assertion count: Brain Monkey's expectations are Mockery expectations,
 * which PHPUnit does not count. Without adding them, a test whose only check is
 * `Functions\expect(...)->once()` reports as risky, and failOnRisky fails it.
 */
uses()
    ->beforeEach(function (): void {
        setUp();

        // __(), _e(), esc_html__() and the rest return or echo their text unchanged.
        Functions\stubTranslationFunctions();
    })
    ->afterEach(function (): void {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());

        tearDown();
    })
    ->in('Unit');

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
 * Backs get_option(), update_option() and delete_option() with an array, so a batch's processed ids
 * persist across calls inside one test the way they do in wp_options.
 *
 * @param  array<string, mixed>  $store  Read and written by the stubs; the test inspects it afterward.
 */
function stub_options(array &$store): void
{
    Functions\when('get_option')->alias(function (string $name, $default = false) use (&$store) {
        return array_key_exists($name, $store) ? $store[$name] : $default;
    });
    Functions\when('update_option')->alias(function (string $name, $value) use (&$store): bool {
        $store[$name] = $value;

        return true;
    });
    Functions\when('delete_option')->alias(function (string $name) use (&$store): bool {
        unset($store[$name]);

        return true;
    });
}
