<?php

/**
 * FaD_Provider_Batch and FaD_Location_Batch: copy each post's condition and treatment relations into
 * the `_cpt` fields, offsetting each related id so it points at the custom post type (conditions by
 * 100000, treatments by 200000). They match FaD_AoEs_Batch apart from post type and field names, so
 * one dataset drives both.
 *
 * @see fad/class-providers-batch.php
 * @see fad/class-locations-batch.php
 * @see tests/Unit/Fad/AoesBatchTest.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

beforeEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

afterEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

dataset('offset batches', [
    'providers' => [
        FaD_Provider_Batch::class,
        'providers_update_post_taxonomy',
        'Update Providers Post from Taxonomy',
        'provider',
        'physician_conditions',
        'physician_treatments',
        'physician_conditions_cpt',
        'physician_treatments_cpt',
        'provider_batch_processing_init',
    ],
    'locations' => [
        FaD_Location_Batch::class,
        'location_update_post_taxonomy',
        'Update Locations Post from Taxonomy',
        'location',
        'location_conditions',
        'location_treatments',
        'location_conditions_cpt',
        'location_treatments_cpt',
        'location_batch_processing_init',
    ],
]);

it('carries its batch id and title', function (string $class, string $id, string $title): void {
    fake_post_queries([]);
    $batch = new $class;

    expect($batch->id)->toBe($id)
        ->and($batch->title)->toBe($title);
})->with('offset batches');

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
    })->with('offset batches');

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
    })->with('offset batches');

    it('queues nothing when there are no posts, and still resets the post data once', function (string $class): void {
        fake_post_queries([]);

        $batch = new $class;

        expect($batch->get_items_count())->toBe(0)
            ->and($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    })->with('offset batches');

    it('resets the post data once after the loop', function (string $class): void {
        fake_post_queries([1, 2]);

        new $class;

        expect($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    })->with('offset batches');
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
    })->with('offset batches');

    it('offsets each related condition id by 100000 and stores the strings', function (string $class, string $id, string $title, string $type, string $conditions, string $treatments, string $conditionsCpt): void {
        $batch = new $class;
        Functions\expect('get_field')->once()->with($conditions, 7)->andReturn(['5', 12]);
        Functions\expect('get_field')->once()->with($treatments, 7)->andReturn(false);
        Functions\expect('update_field')->once()->with($conditionsCpt, ['100005', '100012'], 7);

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('offset batches');

    it('offsets each related treatment id by 200000 and stores the strings', function (string $class, string $id, string $title, string $type, string $conditions, string $treatments, string $conditionsCpt, string $treatmentsCpt): void {
        $batch = new $class;
        Functions\expect('get_field')->once()->with($conditions, 7)->andReturn([]);
        Functions\expect('get_field')->once()->with($treatments, 7)->andReturn(['3', 4]);
        Functions\expect('update_field')->once()->with($treatmentsCpt, ['200003', '200004'], 7);

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('offset batches');

    it('updates both fields when both have relations', function (string $class, string $id, string $title, string $type, string $conditions, string $treatments, string $conditionsCpt, string $treatmentsCpt): void {
        $batch = new $class;
        when('get_field')->alias(fn (string $field, int $post): array => $field === $conditions ? [1] : [2]);
        Functions\expect('update_field')->once()->with($conditionsCpt, ['100001'], 9);
        Functions\expect('update_field')->once()->with($treatmentsCpt, ['200002'], 9);

        process_item($batch, 9, ['id' => 9]);
    })->with('offset batches');

    it('updates nothing when neither field has relations', function (string $class): void {
        $batch = new $class;
        when('get_field')->justReturn(false);
        Functions\expect('update_field')->never();

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('offset batches');
});

it('does nothing in finish()', function (string $class): void {
    fake_post_queries([]);

    expect((new $class)->finish())->toBeNull();
})->with('offset batches');

it('registers one batch of its class with the processor when the init function runs', function (string $class, string $id, string $title, string $type, string $conditions, string $treatments, string $conditionsCpt, string $treatmentsCpt, string $init): void {
    fake_post_queries([]);

    $init();

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf($class);
})->with('offset batches');
