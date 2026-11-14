<?php

/**
 * WP_Batch_Processor: the registry of batches, a singleton.
 *
 * @see includes/class-batch-processor.php
 */

declare(strict_types=1);

beforeEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

afterEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

it('starts with no batches', function (): void {
    expect(WP_Batch_Processor::get_instance()->get_batches())->toBe([]);
});

it('keeps the batches in the order they were registered', function (): void {
    $first = new FakeBatch('first');
    $second = new FakeBatch('second');
    $processor = WP_Batch_Processor::get_instance();

    $processor->register($first);
    $processor->register($second);

    expect($processor->get_batches())->toBe([$first, $second]);
});

it('finds a registered batch by its id', function (): void {
    $first = new FakeBatch('first');
    $second = new FakeBatch('second');
    $processor = WP_Batch_Processor::get_instance();
    $processor->register($first);
    $processor->register($second);

    expect($processor->get_batch('second'))->toBe($second)
        ->and($processor->get_batch('first'))->toBe($first);
});

it('returns null for an id nothing registered', function (): void {
    $processor = WP_Batch_Processor::get_instance();
    $processor->register(new FakeBatch('first'));

    expect($processor->get_batch('nope'))->toBeNull();
});

it('matches an id by type as well as value', function (): void {
    $processor = WP_Batch_Processor::get_instance();
    $processor->register(new FakeBatch('1'));

    expect($processor->get_batch(1))->toBeNull()
        ->and($processor->get_batch('1'))->not->toBeNull();
});

it('hands back one shared instance', function (): void {
    expect(WP_Batch_Processor::get_instance())->toBe(WP_Batch_Processor::get_instance());
});

it('forgets its batches when a new instance is built', function (): void {
    WP_Batch_Processor::get_instance()->register(new FakeBatch('first'));

    forget_singleton(WP_Batch_Processor::class);

    expect(WP_Batch_Processor::get_instance()->get_batches())->toBe([]);
});
