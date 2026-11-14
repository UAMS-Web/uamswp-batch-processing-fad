<?php

/**
 * FaD_AoEs_Batch: copies each Area of Expertise post's condition and treatment relations into the
 * `_cpt` fields, offsetting each related id so it points at the custom post type (conditions by
 * 100000, treatments by 200000).
 *
 * @see fad/class-aoes-batch.php
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

it('is the batch named aoes_update_post_taxonomy', function (): void {
    fake_post_queries([]);
    $batch = new FaD_AoEs_Batch;

    expect($batch->id)->toBe('aoes_update_post_taxonomy')
        ->and($batch->title)->toBe('Update AoEs Post from Taxonomy');
});

describe('setup', function (): void {
    it('queues one item per expertise post, in query order, carrying the post id', function (): void {
        fake_post_queries([30, 10, 20]);
        $store = [];
        stub_options($store);

        $batch = new FaD_AoEs_Batch;
        $seen = [];
        while (($item = $batch->get_next_item()) !== false) {
            $seen[] = [$item->id, $item->get_value('id')];
            $batch->mark_as_processed($item->id);
        }

        expect($seen)->toBe([[30, 30], [10, 10], [20, 20]]);
    });

    it('queries every expertise post of any status, ordered by title, with no pagination', function (): void {
        fake_post_queries([]);

        new FaD_AoEs_Batch;

        expect(WP_Query::$constructed)->toBe([[
            'post_type' => 'expertise',
            'orderby' => 'title',
            'order' => 'ASC',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'cache_results' => false,
        ]]);
    });

    it('queues nothing when there are no expertise posts, and still resets the post data', function (): void {
        fake_post_queries([]);

        $batch = new FaD_AoEs_Batch;

        expect($batch->get_items_count())->toBe(0)
            ->and($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    });

    it('resets the post data once after the loop', function (): void {
        fake_post_queries([1, 2]);

        new FaD_AoEs_Batch;

        expect($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    });
});

describe('process', function (): void {
    beforeEach(function (): void {
        fake_post_queries([]);
        $this->batch = new FaD_AoEs_Batch;
    });

    it('reads nothing and returns true for an item with no post id', function (): void {
        Functions\expect('get_field')->never();
        Functions\expect('update_field')->never();

        expect(process_item($this->batch, 1, []))->toBeTrue()
            ->and(process_item($this->batch, 1, ['id' => 0]))->toBeTrue();
    });

    it('offsets each related condition id by 100000 and stores the strings', function (): void {
        Functions\expect('get_field')->once()->with('expertise_conditions', 7)->andReturn(['5', 12]);
        Functions\expect('get_field')->once()->with('expertise_treatments', 7)->andReturn(false);
        Functions\expect('update_field')->once()->with('expertise_conditions_cpt', ['100005', '100012'], 7);

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue();
    });

    it('offsets each related treatment id by 200000 and stores the strings', function (): void {
        Functions\expect('get_field')->once()->with('expertise_conditions', 7)->andReturn([]);
        Functions\expect('get_field')->once()->with('expertise_treatments', 7)->andReturn(['3', 4]);
        Functions\expect('update_field')->once()->with('expertise_treatments_cpt', ['200003', '200004'], 7);

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue();
    });

    it('updates both fields when both have relations', function (): void {
        when('get_field')->alias(fn (string $field, int $id): array => $field === 'expertise_conditions' ? [1] : [2]);
        Functions\expect('update_field')->once()->with('expertise_conditions_cpt', ['100001'], 9);
        Functions\expect('update_field')->once()->with('expertise_treatments_cpt', ['200002'], 9);

        process_item($this->batch, 9, ['id' => 9]);
    });

    it('updates nothing when neither field has relations', function (): void {
        when('get_field')->justReturn(false);
        Functions\expect('update_field')->never();

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue();
    });
});

it('does nothing in finish()', function (): void {
    fake_post_queries([]);

    expect((new FaD_AoEs_Batch)->finish())->toBeNull();
});

it('registers one FaD_AoEs_Batch with the processor when the init function runs', function (): void {
    fake_post_queries([]);

    aoe_batch_processing_init();

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf(FaD_AoEs_Batch::class);
});
