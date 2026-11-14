<?php

/**
 * FaD_Condition_Sync_Batch and FaD_Treatment_Sync_Batch: for each post, copy its id into the reverse
 * relationship field of every post it relates to. The two classes mirror each other (post type,
 * field names and init function), so one dataset drives both.
 *
 * process() skips a related post whose reverse field already lists the item's id (`$p_id`), so a
 * second run adds nothing.
 *
 * @see fad/class-condition-sync-batch.php
 * @see fad/class-treatment-sync-batch.php
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;
use Brain\Monkey\Functions;

beforeEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

afterEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

dataset('sync batches', [
    'conditions' => [
        FaD_Condition_Sync_Batch::class,
        'conditions_update_sync_batch',
        'Update Conditions Sync to Treatments',
        'condition',
        'condition_treatments',
        'treatment_conditions',
        'field_treatment_conditions',
        'condition_sync_batch_processing_init',
    ],
    'treatments' => [
        FaD_Treatment_Sync_Batch::class,
        'treatments_update_sync_batch',
        'Update Treatments Sync to Conditions',
        'treatment',
        'treatment_conditions',
        'condition_treatments',
        'field_condition_treatments',
        'treatment_sync_batch_processing_init',
    ],
]);

it('carries its batch id and title', function (string $class, string $id, string $title): void {
    fake_post_queries([]);
    $batch = new $class;

    expect($batch->id)->toBe($id)
        ->and($batch->title)->toBe($title);
})->with('sync batches');

describe('setup', function (): void {
    it('queues one item per post, in query order, carrying the post id', function (string $class): void {
        fake_post_queries([30, 10, 20]);
        $store = [];
        stub_options($store);

        $batch = new $class;
        $seen = [];
        while (($item = $batch->get_next_item()) !== false) {
            $seen[] = [$item->id, $item->get_value('id')];
            $batch->mark_as_processed($item->id);
        }

        expect($seen)->toBe([[30, 30], [10, 10], [20, 20]]);
    })->with('sync batches');

    it('queries every post of its type and any status, ordered by title, with no pagination', function (string $class, string $id, string $title, string $type): void {
        fake_post_queries([]);

        new $class;

        expect(WP_Query::$constructed)->toBe([[
            'post_type' => $type,
            'orderby' => 'title',
            'order' => 'ASC',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'cache_results' => false,
        ]]);
    })->with('sync batches');

    it('queues nothing when there are no posts, and still resets the post data once', function (string $class): void {
        fake_post_queries([]);

        $batch = new $class;

        expect($batch->get_items_count())->toBe(0)
            ->and($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    })->with('sync batches');

    it('resets the post data once after the loop', function (string $class): void {
        fake_post_queries([1, 2]);

        new $class;

        expect($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    })->with('sync batches');
});

describe('process', function (): void {
    beforeEach(function (): void {
        fake_post_queries([]);
    });

    it('reads nothing and returns true for an item with no post id', function (string $class): void {
        $batch = new $class;
        Functions\expect('get_field')->never();
        Functions\expect('update_field')->never();

        expect(process_item($batch, 1, []))->toBeTrue()
            ->and(process_item($batch, 1, ['id' => 0]))->toBeTrue();
    })->with('sync batches');

    it('updates nothing when the relationship field is not an array', function (string $class, string $id, string $title, string $type, string $field): void {
        $batch = new $class;
        Functions\expect('get_field')->once()->with($field, 7)->andReturn(false);
        Functions\expect('update_field')->never();

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('sync batches');

    it('adds the post id to the reverse field of a related post that has none', function (string $class, string $id, string $title, string $type, string $field, string $reverse, string $key): void {
        $batch = new $class;
        Functions\expect('get_field')->once()->with($field, 7)->andReturn([40]);
        Functions\expect('get_field')->once()->with($reverse, 40, false)->andReturn('');
        Functions\expect('update_field')->once()->with($key, [7], 40);

        process_item($batch, 7, ['id' => 7]);
    })->with('sync batches');

    it('appends the post id to the reverse field of a related post that has others', function (string $class, string $id, string $title, string $type, string $field, string $reverse, string $key): void {
        $batch = new $class;
        Functions\expect('get_field')->once()->with($field, 7)->andReturn([40]);
        Functions\expect('get_field')->once()->with($reverse, 40, false)->andReturn([5, 6]);
        Functions\expect('update_field')->once()->with($key, [5, 6, 7], 40);

        process_item($batch, 7, ['id' => 7]);
    })->with('sync batches');

    it('updates each related post in turn', function (string $class, string $id, string $title, string $type, string $field, string $reverse, string $key): void {
        $batch = new $class;
        when('get_field')->alias(fn (string $name, int $post, $format = true): array => $name === $field ? [40, 41] : []);
        $written = [];
        when('update_field')->alias(function (string $name, array $value, int $post) use (&$written): void {
            $written[] = [$name, $value, $post];
        });

        process_item($batch, 7, ['id' => 7]);

        expect($written)->toBe([[$key, [7], 40], [$key, [7], 41]]);
    })->with('sync batches');

    it('raises no warning while it walks the related posts', function (string $class, string $id, string $title, string $type, string $field): void {
        $batch = new $class;
        when('get_field')->alias(fn (string $name, int $post, $format = true): array => $name === $field ? [40, 41] : []);
        when('update_field')->justReturn(true);

        $warnings = record_warnings(fn (): mixed => process_item($batch, 7, ['id' => 7]));

        expect($warnings)->toBe([]);
    })->with('sync batches');

    it('skips a related post whose reverse field already lists the post id, as an int or a string', function (string $class, string $id, string $title, string $type, string $field, string $reverse, string $key, string $init, mixed $listed): void {
        $batch = new $class;
        Functions\expect('get_field')->once()->with($field, 7)->andReturn([40]);
        Functions\expect('get_field')->once()->with($reverse, 40, false)->andReturn([5, $listed]);
        Functions\expect('update_field')->never();

        process_item($batch, 7, ['id' => 7]);
    })->with('sync batches')->with(['an int' => [7], 'a string' => ['7']]);

    it('carries on to the next related post after skipping one', function (string $class, string $id, string $title, string $type, string $field, string $reverse, string $key): void {
        $batch = new $class;
        when('get_field')->alias(fn (string $name, int $post, $format = true): array => match (true) {
            $name === $field => [40, 41],
            $post === 40 => [5, 7],
            default => [],
        });
        $written = [];
        when('update_field')->alias(function (string $name, array $value, int $post) use (&$written): void {
            $written[] = $post;
        });

        process_item($batch, 7, ['id' => 7]);

        expect($written)->toBe([41]);
    })->with('sync batches');
});

it('does nothing in finish()', function (string $class): void {
    fake_post_queries([]);

    expect((new $class)->finish())->toBeNull();
})->with('sync batches');

it('registers one batch of its class with the processor when the init function runs', function (string $class, string $id, string $title, string $type, string $field, string $reverse, string $key, string $init): void {
    fake_post_queries([]);

    $init();

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf($class);
})->with('sync batches');
