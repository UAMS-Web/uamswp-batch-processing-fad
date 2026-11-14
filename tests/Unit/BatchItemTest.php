<?php

/**
 * WP_Batch_Item: an id and a data array, with a defaulted read of one key.
 *
 * @see includes/class-batch-item.php
 */

declare(strict_types=1);

it('keeps the id and the data it was built with', function (): void {
    $item = new WP_Batch_Item(7, ['name' => 'Ada']);

    expect($item->id)->toBe(7)
        ->and($item->data)->toBe(['name' => 'Ada']);
});

it('has no data unless it is given some', function (): void {
    expect((new WP_Batch_Item(1))->data)->toBeNull();
});

it('returns the value stored under a key', function (): void {
    $item = new WP_Batch_Item(1, ['name' => 'Ada']);

    expect($item->get_value('name'))->toBe('Ada');
});

it('returns the default for a key it does not hold', function (): void {
    $item = new WP_Batch_Item(1, ['name' => 'Ada']);

    expect($item->get_value('missing'))->toBeNull()
        ->and($item->get_value('missing', 'fallback'))->toBe('fallback');
});

it('returns the default when the data is not an array', function (mixed $data): void {
    expect((new WP_Batch_Item(1, $data))->get_value('name', 'fallback'))->toBe('fallback');
})->with(['null' => [null], 'string' => ['name'], 'int' => [5], 'plain object' => [new stdClass]]);

it('reads from data that behaves as an array', function (): void {
    expect((new WP_Batch_Item(1, new ArrayObject(['name' => 'Ada'])))->get_value('name'))->toBe('Ada');
});

it('reads a value as an integer id', function (mixed $stored, int $expected): void {
    expect((new WP_Batch_Item(1, ['id' => $stored]))->get_int_value('id'))->toBe($expected);
})->with([
    'int' => [7, 7],
    'numeric string' => ['8', 8],
    'zero' => [0, 0],
    'text' => ['abc', 0],
    'float' => [2.5, 0],
    'array' => [[3], 0],
    'null' => [null, 0],
]);

it('reads the id key unless told another, and 0 for a key it does not hold', function (): void {
    $item = new WP_Batch_Item(1, ['id' => 4, 'other' => 9]);

    expect($item->get_int_value())->toBe(4)
        ->and($item->get_int_value('other'))->toBe(9)
        ->and($item->get_int_value('missing'))->toBe(0);
});

it('treats a stored null as absent and returns the default', function (): void {
    // get_value() reads with isset(), so a key holding null falls back to the default.
    $item = new WP_Batch_Item(1, ['name' => null]);

    expect($item->get_value('name', 'fallback'))->toBe('fallback');
});

it('returns the default when the item has no data at all', function (): void {
    expect((new WP_Batch_Item(1))->get_value('name', 'fallback'))->toBe('fallback');
});
