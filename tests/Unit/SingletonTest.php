<?php

/**
 * WP_BP_Singleton: one instance per using class, built through get_instance() only.
 *
 * @see includes/class-bp-singleton.php
 */

declare(strict_types=1);

final class SingletonProbe
{
    use WP_BP_Singleton;

    public static int $inits = 0;

    protected function init()
    {
        self::$inits++;
    }
}

final class SingletonPlain
{
    use WP_BP_Singleton;
}

beforeEach(function (): void {
    SingletonProbe::$inits = 0;
    forget_singleton(SingletonProbe::class);
    forget_singleton(SingletonPlain::class);
});

it('builds the instance on the first call and runs init() once', function (): void {
    $first = SingletonProbe::get_instance();
    $second = SingletonProbe::get_instance();

    expect($first)->toBeInstanceOf(SingletonProbe::class)
        ->and($second)->toBe($first)
        ->and(SingletonProbe::$inits)->toBe(1);
});

it('keeps a separate instance for each class that uses it', function (): void {
    expect(SingletonProbe::get_instance())->not->toBe(SingletonPlain::get_instance());
});

it('has an init() that does nothing unless the class defines one', function (): void {
    expect(SingletonPlain::get_instance())->toBeInstanceOf(SingletonPlain::class);
});

it('refuses new and clone from outside the class', function (): void {
    expect(fn () => new SingletonPlain)->toThrow(Error::class);

    $instance = SingletonPlain::get_instance();

    expect(fn () => clone $instance)->toThrow(Error::class);
});

it('warns, as the trait compiles, that its private __wakeup() is not public', function (): void {
    // Pins what PHP 8.4 says about the trait today; the bootstrap captured it as the file loaded. The
    // trait's private final methods draw a second, compile-time warning ("Private methods cannot be final
    // as they are never overridden by other classes") that no handler can receive, so it is not asserted.
    expect($GLOBALS['bp_load_warnings'])->toBe([
        'The magic method WP_BP_Singleton::__wakeup() must have public visibility',
    ]);
});
