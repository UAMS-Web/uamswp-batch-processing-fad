<?php

/**
 * FaD_Provider_Filter_Batch: builds a comma-separated list of a provider's expertise, condition and
 * treatment titles and stores it in the provider's filter field.
 *
 * process() starts each list empty, so a provider with no titles of one kind contributes an empty
 * segment and no warning is raised.
 *
 * @see fad/class-providers-filter-batch.php
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

/**
 * Runs process() for provider 7 with the three relation fields answered, returning what was stored
 * in the filter field and the warnings raised.
 *
 * @param  array<int, int>  $expertises
 * @param  array<int, int>  $conditions
 * @param  array<int, int>  $treatments
 * @return array{stored: array<int, array<int, mixed>>, warnings: array<int, string>}
 */
function run_filter_process(array $expertises, array $conditions, array $treatments): array
{
    fake_post_queries([]);
    $batch = new FaD_Provider_Filter_Batch;
    when('get_field')->alias(fn (string $name, int $post): array|false => match ($name) {
        'field_physician_expertise' => $expertises,
        'field_physician_conditions_cpt' => $conditions,
        'field_physician_treatments_cpt' => $treatments,
        default => false,
    });
    when('get_the_title')->alias(fn (int $post): string => "T{$post}");

    $stored = [];
    when('update_field')->alias(function (string $name, $value, int $post) use (&$stored): void {
        $stored[] = [$name, $value, $post];
    });

    $warnings = record_warnings(fn (): mixed => process_item($batch, 7, ['id' => 7]));

    return ['stored' => $stored, 'warnings' => $warnings];
}

it('is the batch named providers_update_filter', function (): void {
    fake_post_queries([]);
    $batch = new FaD_Provider_Filter_Batch;

    expect($batch->id)->toBe('providers_update_filter')
        ->and($batch->title)->toBe('Update Providers Filter');
});

describe('setup', function (): void {
    it('queues one item per provider, in query order, carrying the post id', function (): void {
        fake_post_queries([30, 10, 20]);
        $store = [];
        stub_options($store);

        $batch = new FaD_Provider_Filter_Batch;
        $seen = [];
        while (($item = $batch->get_next_item()) !== false) {
            $seen[] = [$item->id, $item->get_value('id')];
            $batch->mark_as_processed($item->id);
        }

        expect($seen)->toBe([[30, 30], [10, 10], [20, 20]]);
    });

    it('queries every provider of any status, ordered by title, with no pagination', function (): void {
        fake_post_queries([]);

        new FaD_Provider_Filter_Batch;

        expect(WP_Query::$constructed)->toBe([[
            'post_type' => 'provider',
            'orderby' => 'title',
            'order' => 'ASC',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'cache_results' => false,
        ]]);
    });

    it('queues nothing when there are no providers, and still resets the post data once', function (): void {
        fake_post_queries([]);

        $batch = new FaD_Provider_Filter_Batch;

        expect($batch->get_items_count())->toBe(0)
            ->and($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    });

    it('resets the post data once after the loop', function (): void {
        fake_post_queries([1, 2]);

        new FaD_Provider_Filter_Batch;

        expect($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    });
});

describe('process', function (): void {
    it('reads nothing and returns true for an item with no post id', function (): void {
        fake_post_queries([]);
        $batch = new FaD_Provider_Filter_Batch;
        Functions\expect('get_field')->never();
        Functions\expect('update_field')->never();

        expect(process_item($batch, 1, []))->toBeTrue()
            ->and(process_item($batch, 1, ['id' => 0]))->toBeTrue();
    });

    it('reads the three relation fields of the provider', function (): void {
        fake_post_queries([]);
        $batch = new FaD_Provider_Filter_Batch;
        $read = [];
        when('get_field')->alias(function (string $name, int $post) use (&$read): bool {
            $read[] = [$name, $post];

            return false;
        });
        when('update_field')->justReturn(true);

        record_warnings(fn (): mixed => process_item($batch, 7, ['id' => 7]));

        expect($read)->toBe([
            ['field_physician_expertise', 7],
            ['field_physician_conditions_cpt', 7],
            ['field_physician_treatments_cpt', 7],
        ]);
    });

    it('joins the titles of each list with commas, and the three lists with commas', function (): void {
        $result = run_filter_process([1, 2, 3], [4, 5, 6], [7, 8, 9]);

        expect($result['stored'])->toBe([['field_physician_asp_filter', 'T1, T2, T3, T4, T5, T6, T7, T8, T9', 7]]);
    });

    it('leaves an empty list as an empty segment', function (): void {
        $result = run_filter_process([], [3], []);

        expect($result['stored'])->toBe([['field_physician_asp_filter', ', T3, ', 7]]);
    });

    it('stores only separators when the provider has no relations', function (): void {
        $result = run_filter_process([], [], []);

        expect($result['stored'])->toBe([['field_physician_asp_filter', ', , ', 7]]);
    });

    it('returns true', function (): void {
        fake_post_queries([]);
        $batch = new FaD_Provider_Filter_Batch;
        when('get_field')->justReturn(false);
        when('update_field')->justReturn(true);

        $result = null;
        record_warnings(function () use ($batch, &$result): void {
            $result = process_item($batch, 7, ['id' => 7]);
        });

        expect($result)->toBeTrue();
    });

    it('raises no warning, whichever of the three lists are empty', function (): void {
        // A literal list rather than a dataset: a dataset closure's untyped array parameters would not
        // satisfy the helper's `array<int, int>` parameters at PHPStan level 10.
        $cases = [
            'all three' => [[1], [2], [3]],
            'expertise only' => [[1], [], []],
            'conditions only' => [[], [2], []],
            'treatments only' => [[], [], [3]],
            'none' => [[], [], []],
        ];

        foreach ($cases as $case => [$expertises, $conditions, $treatments]) {
            $result = run_filter_process($expertises, $conditions, $treatments);

            expect($result['warnings'])->toBe([], $case);
        }
    });
});

it('does nothing in finish()', function (): void {
    fake_post_queries([]);

    $batch = new FaD_Provider_Filter_Batch;
    $batch->finish();

    expect($batch->get_items_count())->toBe(0);
});

it('registers one FaD_Provider_Filter_Batch with the processor when the init function runs', function (): void {
    fake_post_queries([]);

    provider_filter_batch_processing_init();

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf(FaD_Provider_Filter_Batch::class);
});
