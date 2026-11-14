<?php

/**
 * FaD_Provider_Nrc_Cache: for each provider with an NPI, fetches the NRC Health transparency data
 * and keeps the response in a 30-day transient, unless one is already cached.
 *
 * @see fad/class-providers-nrc-cache.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

// WordPress defines this; the batch multiplies it by 30 for the transient's lifetime.
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);

beforeEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

afterEach(function (): void {
    forget_singleton(WP_Batch_Processor::class);
});

it('is the batch named providers_cache_nrc_data', function (): void {
    fake_post_queries([]);
    $batch = new FaD_Provider_Nrc_Cache;

    expect($batch->id)->toBe('providers_cache_nrc_data')
        ->and($batch->title)->toBe('Update Cache Providers NRC Data');
});

describe('setup', function (): void {
    it('queues one item per provider, in query order, carrying the post id', function (): void {
        fake_post_queries([30, 10, 20]);
        $store = [];
        stub_options($store);

        $batch = new FaD_Provider_Nrc_Cache;
        $seen = [];
        while (($item = $batch->get_next_item()) !== false) {
            $seen[] = [$item->id, $item->get_value('id')];
            $batch->mark_as_processed($item->id);
        }

        expect($seen)->toBe([[30, 30], [10, 10], [20, 20]]);
    });

    it('queries every provider of any status, ordered by title, with no pagination', function (): void {
        fake_post_queries([]);

        new FaD_Provider_Nrc_Cache;

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

        $batch = new FaD_Provider_Nrc_Cache;

        expect($batch->get_items_count())->toBe(0)
            ->and($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    });

    it('resets the post data once after the loop', function (): void {
        fake_post_queries([1, 2]);

        new FaD_Provider_Nrc_Cache;

        expect($GLOBALS['bp_reset_postdata_calls'])->toBe(1);
    });
});

describe('process', function (): void {
    beforeEach(function (): void {
        fake_post_queries([]);
        $this->batch = new FaD_Provider_Nrc_Cache;
    });

    it('reads nothing and returns true for an item with no post id', function (): void {
        Functions\expect('get_field')->never();
        Functions\expect('get_transient')->never();

        expect(process_item($this->batch, 1, []))->toBeTrue()
            ->and(process_item($this->batch, 1, ['id' => 0]))->toBeTrue();
    });

    it('fetches nothing for a provider with no NPI', function (): void {
        Functions\expect('get_field')->once()->with('physician_npi', 7)->andReturn('');
        Functions\expect('get_transient')->never();
        Functions\expect('wp_remote_get')->never();

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue();
    });

    it('fetches the NPI\'s profile and caches the body for 30 days on a miss', function (): void {
        Functions\expect('get_field')->once()->with('physician_npi', 7)->andReturn('1234567890');
        Functions\expect('get_transient')->once()->with('nrc_backup_1234567890')->andReturn(false);
        Functions\expect('wp_remote_get')->once()
            ->with('https://transparency.nrchealth.com/widget/api/org-profile/uams/npi/1234567890/50')
            ->andReturn(['body' => 'raw']);
        Functions\expect('wp_remote_retrieve_body')->once()->with(['body' => 'raw'])->andReturn('{"reviews":[]}');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\expect('set_transient')->once()->with('nrc_backup_1234567890', '{"reviews":[]}', 30 * 86400);

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue();
    });

    it('caches nothing when the fetched body is an error', function (): void {
        Functions\when('get_field')->justReturn('1234567890');
        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_remote_get')->justReturn(['body' => 'raw']);
        Functions\when('wp_remote_retrieve_body')->justReturn(new WP_Error);
        Functions\expect('is_wp_error')->once()->andReturn(true);
        Functions\expect('set_transient')->never();

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue();
    });

    it('leaves a cached response alone, whatever it holds', function (mixed $cached): void {
        Functions\when('get_field')->justReturn('1234567890');
        Functions\when('get_transient')->justReturn($cached);
        Functions\expect('wp_remote_get')->never();
        Functions\expect('set_transient')->never();

        expect(process_item($this->batch, 7, ['id' => 7]))->toBeTrue();
    })->with([
        'a body' => ['{"reviews":[]}'],
        'an empty string' => [''],
        'zero' => [0],
    ]);
});

it('does nothing in finish()', function (): void {
    fake_post_queries([]);

    expect((new FaD_Provider_Nrc_Cache)->finish())->toBeNull();
});

it('registers one FaD_Provider_Nrc_Cache with the processor when the init function runs', function (): void {
    fake_post_queries([]);

    providers_cache_nrc_data_batch_processing_init();

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf(FaD_Provider_Nrc_Cache::class);
});
