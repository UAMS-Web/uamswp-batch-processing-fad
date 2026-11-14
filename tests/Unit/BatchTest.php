<?php

/**
 * WP_Batch: the queue of items and the processed-id bookkeeping kept in wp_options.
 *
 * Options are backed by an array (stub_options() in tests/Pest.php), so what a batch writes is
 * what it reads next.
 *
 * @see includes/class-batch.php
 */

declare(strict_types=1);

beforeEach(function (): void {
    $this->store = [];
    stub_options($this->store);
});

it('builds its queue from setup() when constructed', function (): void {
    $batch = new FakeBatch('b', [1, 2, 3]);

    expect($batch->get_items_count())->toBe(3);
});

it('has no items before setup() pushes any', function (): void {
    expect((new FakeBatch('b'))->get_items_count())->toBe(0);
});

it('names its option after its id', function (): void {
    expect((new FakeBatch('my_batch'))->get_db_identifier())->toBe('batch_my_batch_processed');
});

it('starts a queue when push() meets a store that is not an array', function (): void {
    $batch = new FakeBatch('b');
    $batch->breakItems();

    $batch->pushPublic(new WP_Batch_Item(9));

    expect($batch->items())->toHaveCount(1)
        ->and($batch->items()[0]->id)->toBe(9);
});

it('appends to the queue in the order pushed', function (): void {
    $batch = new FakeBatch('b');
    $batch->pushPublic(new WP_Batch_Item(2));
    $batch->pushPublic(new WP_Batch_Item(1));

    expect(array_map(fn (WP_Batch_Item $item) => $item->id, $batch->items()))->toBe([2, 1]);
});

it('reports no processed items when nothing is stored', function (): void {
    expect((new FakeBatch('b', [1]))->get_processed_items())->toBe([]);
});

it('reads the processed ids from the batch option', function (): void {
    $batch = new FakeBatch('b', [1, 2]);
    $this->store['batch_b_processed'] = [1];

    expect($batch->get_processed_items())->toBe([1])
        ->and($batch->get_processed_count())->toBe(1);
});

it('records a processed id under the batch option', function (): void {
    $batch = new FakeBatch('b', [1, 2]);

    $batch->mark_as_processed(1);

    expect($this->store)->toBe(['batch_b_processed' => [1]]);
});

it('records each id once however often it is marked', function (): void {
    $batch = new FakeBatch('b', [1, 2]);

    $batch->mark_as_processed(1);
    $batch->mark_as_processed(2);
    $batch->mark_as_processed(1);

    expect(array_values($this->store['batch_b_processed']))->toBe([1, 2]);
});

it('says whether an item was processed', function (): void {
    $batch = new FakeBatch('b', [1, 2]);
    $batch->mark_as_processed(1);

    expect($batch->is_processed(new WP_Batch_Item(1)))->toBeTrue()
        ->and($batch->is_processed(new WP_Batch_Item(2)))->toBeFalse();
});

it('hands out the first item that is not processed yet', function (): void {
    $batch = new FakeBatch('b', [1, 2, 3]);
    $batch->mark_as_processed(1);

    expect($batch->get_next_item()->id)->toBe(2);

    $batch->mark_as_processed(2);

    expect($batch->get_next_item()->id)->toBe(3);
});

it('hands out the first item when none is processed', function (): void {
    expect((new FakeBatch('b', [4, 5]))->get_next_item()->id)->toBe(4);
});

it('hands out false when every item is processed', function (): void {
    $batch = new FakeBatch('b', [1, 2]);
    $batch->mark_as_processed(1);
    $batch->mark_as_processed(2);

    expect($batch->get_next_item())->toBeFalse();
});

it('hands out false when the queue is empty', function (): void {
    expect((new FakeBatch('b'))->get_next_item())->toBeFalse();
});

it('is finished only once every item is processed', function (): void {
    $batch = new FakeBatch('b', [1, 2]);

    expect($batch->is_finished())->toBeFalse();

    $batch->mark_as_processed(1);

    expect($batch->is_finished())->toBeFalse();

    $batch->mark_as_processed(2);

    expect($batch->is_finished())->toBeTrue();
});

it('is finished when it has no items', function (): void {
    expect((new FakeBatch('b'))->is_finished())->toBeTrue();
});

it('reports the share of items processed, to two decimals', function (): void {
    $batch = new FakeBatch('b', [1, 2, 3]);

    expect($batch->get_percentage())->toBe('0.00');

    $batch->mark_as_processed(1);

    expect($batch->get_percentage())->toBe('33.33');

    $batch->mark_as_processed(2);
    $batch->mark_as_processed(3);

    expect($batch->get_percentage())->toBe('100.00');
});

it('reports zero percent for a batch with no items', function (): void {
    expect((new FakeBatch('b'))->get_percentage())->toBe('0.00');
});

it('forgets what was processed when restarted', function (): void {
    $batch = new FakeBatch('b', [1, 2]);
    $batch->mark_as_processed(1);

    $batch->restart();

    expect($this->store)->toBe([])
        ->and($batch->get_processed_count())->toBe(0);
});

it('leaves other batches alone when restarted', function (): void {
    $batch = new FakeBatch('b', [1]);
    $this->store['batch_other_processed'] = [5];
    $batch->mark_as_processed(1);

    $batch->restart();

    expect($this->store)->toBe(['batch_other_processed' => [5]]);
});

it('does nothing in finish() unless a batch overrides it', function (): void {
    $plain = new class extends WP_Batch
    {
        public function setup() {}

        public function process($item)
        {
            return true;
        }
    };

    expect($plain->finish())->toBeNull();
});
