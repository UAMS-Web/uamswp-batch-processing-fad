<?php

/**
 * The four region batches. Condition, expertise and treatment each store the unique regions of every
 * location a post relates to; the provider batch stores only the region of the first (primary)
 * location. All four share one setup() shape, so one dataset drives that part.
 *
 * @see fad/class-condition-region-batch.php
 * @see fad/class-expertise-region-batch.php
 * @see fad/class-treatment-region-batch.php
 * @see fad/class-providers-region-batch.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

beforeEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

afterEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

dataset('region batches', [
    'conditions' => [FaD_Condition_Region_Batch::class, 'condition_update_regions', 'Update Condition Regions', 'condition', 'condition_region_batch_processing_init'],
    'expertise' => [FaD_Expertise_Region_Batch::class, 'expertise_update_regions', 'Update Expertise Regions', 'expertise', 'expertise_region_batch_processing_init'],
    'treatments' => [FaD_Treatment_Region_Batch::class, 'treatment_update_regions', 'Update Treatment Regions', 'treatment', 'treatment_region_batch_processing_init'],
    'providers' => [FaD_Provider_Region_Batch::class, 'providers_update_regions', 'Update Providers Regions', 'provider', 'provider_region_batch_processing_init'],
]);

// The batches that store every unique region: class, locations field, region field to write.
dataset('all-region batches', [
    'conditions' => [FaD_Condition_Region_Batch::class, 'condition_locations', 'condition_region'],
    'expertise' => [FaD_Expertise_Region_Batch::class, 'location_expertise', 'expertise_region'],
    'treatments' => [FaD_Treatment_Region_Batch::class, 'treatment_procedure_locations', 'treatment_procedure_region'],
]);

it('carries its batch id and title', function (string $class, string $id, string $title): void {
    fake_post_queries([]);
    $batch = new $class;

    expect($batch->id)->toBe($id)
        ->and($batch->title)->toBe($title);
})->with('region batches');

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
    })->with('region batches');

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
    })->with('region batches');

    it('queues nothing when there are no posts, and still resets the post data once', function (string $class): void {
        fake_post_queries([]);

        $batch = new $class;

        expect($batch->get_items_count())->toBe(0)
            ->and($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    })->with('region batches');

    it('resets the post data once after the loop', function (string $class): void {
        fake_post_queries([1, 2]);

        new $class;

        expect($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    })->with('region batches');
});

describe('process, for the batches that store every region', function (): void {
    beforeEach(function (): void {
        fake_post_queries([]);
    });

    it('reads nothing and returns true for an item with no post id', function (string $class): void {
        $batch = new $class;
        Functions\expect('get_field')->never();
        Functions\expect('update_field')->never();

        expect(process_item($batch, 1, []))->toBeTrue()
            ->and(process_item($batch, 1, ['id' => 0]))->toBeTrue();
    })->with('all-region batches');

    it('updates nothing when the post relates to no locations', function (string $class, string $locations): void {
        $batch = new $class;
        Functions\expect('get_field')->once()->with($locations, 7)->andReturn([]);
        Functions\expect('update_field')->never();

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('all-region batches');

    it('stores each location\'s region once, as strings', function (string $class, string $locations, string $target): void {
        $batch = new $class;
        Functions\when('get_field')->alias(fn (string $name, int $post) => match ($name) {
            $locations => [11, 12, 13],
            'location_region' => match ($post) {
                11 => 'North',
                12 => 'North',
                default => 5,
            },
        });
        Functions\expect('update_field')->once()->with($target, ['North', 2 => '5'], 7);

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('all-region batches');
});

describe('process, for the provider batch', function (): void {
    beforeEach(function (): void {
        fake_post_queries([]);
        $this->batch = new FaD_Provider_Region_Batch;
    });

    it('reads nothing and returns true for an item with no post id', function (): void {
        Functions\expect('get_field')->never();
        Functions\expect('update_field')->never();

        expect(process_item($this->batch, 1, []))->toBeTrue()
            ->and(process_item($this->batch, 1, ['id' => 0]))->toBeTrue();
    });

    it('updates nothing when the provider has no locations', function (): void {
        Functions\expect('get_field')->once()->with('physician_locations', 7)->andReturn(false);
        Functions\expect('update_field')->never();

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue();
    });

    it('stores only the region of the first location', function (): void {
        $lookups = [];
        Functions\when('get_field')->alias(function (string $name, int $post) use (&$lookups) {
            if ($name === 'physician_locations') {
                return [11, 12];
            }
            $lookups[] = $post;

            return $post === 11 ? 'Primary' : 'Secondary';
        });
        Functions\expect('update_field')->once()->with('physician_region', 'Primary', 7);

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue()
            ->and($lookups)->toBe([11]);
    });
});

it('does nothing in finish()', function (string $class): void {
    fake_post_queries([]);

    expect((new $class)->finish())->toBeNull();
})->with('region batches');

it('registers one batch of its class with the processor when the init function runs', function (string $class, string $id, string $title, string $type, string $init): void {
    fake_post_queries([]);

    $init();

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf($class);
})->with('region batches');
