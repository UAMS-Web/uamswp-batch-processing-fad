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

    protected function init(): void
    {
        self::$inits++;
    }
}

// Not final on purpose: the analyzer reads the empty init() of a final class as a call with no effect, which
// is the very default this class exists to exercise.
class SingletonPlain
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
    expect(fn (): SingletonPlain => new SingletonPlain)->toThrow(Error::class);

    $instance = SingletonPlain::get_instance();

    expect(fn (): object => clone $instance)->toThrow(Error::class);
});

it('refuses unserialize() of an instance', function (): void {
    $serialized = serialize(SingletonPlain::get_instance());

    expect(fn (): mixed => unserialize($serialized))->toThrow(Exception::class, 'Cannot unserialize a singleton.');
});

it('refuses to be built through the constructor, and says so as an Error', function (): void {
    expect(fn (): SingletonProbe => new SingletonProbe)->toThrow(Error::class, 'private');
    expect(SingletonProbe::$inits)->toBe(0);
});

it('compiles and loads on this PHP without a warning of any kind', function (): void {
    // A compile-time warning reaches no error handler, so the file is loaded in a fresh process with every
    // level reported and display on; anything PHP has to say about the trait is printed.
    $process = open_process(
        [PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-r', 'define("ABSPATH", "/tmp/"); include '.var_export(dirname(__DIR__, 2).'/includes/class-bp-singleton.php', true).'; echo "loaded";'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    expect($output)->toBe('loaded')
        ->and($errors)->toBe('');
});
