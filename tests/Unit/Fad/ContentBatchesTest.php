<?php

/**
 * FaD_Condition_Content_Batch and FaD_Treatment_Content_Batch: copy an ACF content field into each
 * post's body. The two classes differ only in post type, ACF field, id, title and init function,
 * so one dataset drives both.
 *
 * @see fad/class-condition-content-batch.php
 * @see fad/class-treatment-content-batch.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

beforeEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

afterEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

dataset('content batches', [
    'conditions' => [
        FaD_Condition_Content_Batch::class,
        'conditions_update_content_batch',
        'Update Conditions content from ACF',
        'condition',
        'condition_content',
        'condition_content_batch_processing_init',
    ],
    'treatments' => [
        FaD_Treatment_Content_Batch::class,
        'treatments_update_content_batch',
        'Update Treatments content from ACF',
        'treatment',
        'treatment_procedure_content',
        'treatment_content_batch_processing_init',
    ],
]);

it('carries its batch id and title', function (string $class, string $id, string $title): void {
    fake_post_queries([]);
    $batch = batch_of($class);

    expect($batch->id)->toBe($id)
        ->and($batch->title)->toBe($title);
})->with('content batches');

describe('setup', function (): void {
    it('queues one item per post, in query order, carrying the post id', function (string $class): void {
        fake_post_queries([30, 10, 20]);
        $store = [];
        stub_options($store);

        $batch = batch_of($class);
        $seen = [];
        while (($item = $batch->get_next_item()) !== false) {
            $seen[] = [$item->id, $item->get_value('id')];
            $batch->mark_as_processed($item->id);
        }

        expect($seen)->toBe([[30, 30], [10, 10], [20, 20]]);
    })->with('content batches');

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
    })->with('content batches');

    it('queues nothing when there are no posts, and still resets the post data once', function (string $class): void {
        fake_post_queries([]);

        $batch = batch_of($class);

        expect($batch->get_items_count())->toBe(0)
            ->and($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    })->with('content batches');

    it('resets the post data once after the loop', function (string $class): void {
        fake_post_queries([1, 2]);

        new $class;

        expect($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    })->with('content batches');
});

describe('process', function (): void {
    it('reads nothing and returns true for an item with no post id', function (string $class): void {
        fake_post_queries([]);
        $batch = batch_of($class);
        Functions\expect('get_field')->never();
        Functions\expect('wp_update_post')->never();

        expect(process_item($batch, 1, []))->toBeTrue()
            ->and(process_item($batch, 1, ['id' => 0]))->toBeTrue();
    })->with('content batches');

    it('writes the ACF content into the post body', function (string $class, string $id, string $title, string $type, string $field): void {
        fake_post_queries([]);
        $batch = batch_of($class);
        Functions\expect('get_field')->once()->with($field, 7)->andReturn('<p>Body</p>');
        Functions\expect('wp_update_post')->once()->with(['ID' => 7, 'post_content' => '<p>Body</p>']);

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('content batches');

    it('leaves the post alone when the ACF field is empty', function (string $class, string $id, string $title, string $type, string $field): void {
        fake_post_queries([]);
        $batch = batch_of($class);
        Functions\expect('get_field')->once()->with($field, 7)->andReturn('');
        Functions\expect('wp_update_post')->never();

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('content batches');
});

it('does nothing in finish()', function (string $class): void {
    fake_post_queries([]);

    $batch = batch_of($class);

    $batch->finish();

    expect($batch->get_items_count())->toBe(0);
})->with('content batches');

it('registers one batch of its class with the processor when the init function runs', function (string $class, string $id, string $title, string $type, string $field, string $init): void {
    fake_post_queries([]);

    $init();

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf($class);
})->with('content batches');
