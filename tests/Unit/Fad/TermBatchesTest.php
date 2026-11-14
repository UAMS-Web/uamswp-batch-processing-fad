<?php

/**
 * FaD_Condition_Batch and FaD_Treatment_Batch: make a post of the same name for each term of a
 * taxonomy, keeping the term id plus an offset as the post id, copying the term's ACF content,
 * excerpt and meta onto it, and storing the term's relations to the other type with that type's
 * offset applied. The classes mirror each other, so one dataset drives both.
 *
 * @see fad/class-condition-batch.php
 * @see fad/class-treatment-batch.php
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

dataset('term batches', [
    'conditions' => [
        FaD_Condition_Batch::class,
        'conditions_posts_from_taxonomy',
        'Make Condition Posts from Taxonomy',
        'condition',
        100000,
        'condition_content',
        'condition_short_desc',
        'condition_treatments',
        200000,
        'condition_batch_processing_init',
    ],
    'treatments' => [
        FaD_Treatment_Batch::class,
        'treatments_posts_from_taxonomy',
        'Make Treatment Posts from Taxonomy',
        'treatment',
        200000,
        'treatment_procedure_content',
        'treatment_procedure_short_desc',
        'treatment_procedure_conditions',
        100000,
        'treatment_batch_processing_init',
    ],
]);

/**
 * Stubs the lookups process() makes for a term, with no meta and no relations unless a test says otherwise.
 *
 * @param  array<string, mixed>  $fields  ACF values by field name; a field not listed is false.
 * @param  array<string, array<int, mixed>>  $meta  What get_term_meta() returns.
 */
function stub_term(object $term, array $fields = [], array $meta = [], mixed $existingPage = null): void
{
    when('get_term_by')->justReturn($term);
    when('get_field')->alias(fn (string $name) => $fields[$name] ?? false);
    when('get_page_by_title')->justReturn($existingPage);
    when('get_term_meta')->justReturn($meta);
    when('is_serialized')->alias(fn ($value): bool => is_string($value) && str_starts_with($value, 'a:'));
}

it('carries its batch id and title', function (string $class, string $id, string $title): void {
    expect((new $class)->id)->toBe($id)
        ->and((new $class)->title)->toBe($title);
})->with('term batches');

describe('setup', function (): void {
    it('queues one item per term, in query order, carrying the term id', function (string $class): void {
        WP_Term_Query::$responses = [[(object) ['term_id' => 30], (object) ['term_id' => 10], (object) ['term_id' => 20]]];
        $store = [];
        stub_options($store);

        $batch = new $class;
        $seen = [];
        while (($item = $batch->get_next_item()) !== false) {
            $seen[] = [$item->id, $item->get_value('id')];
            $batch->mark_as_processed($item->id);
        }

        expect($seen)->toBe([[30, 30], [10, 10], [20, 20]]);
    })->with('term batches');

    it('queries every term of its taxonomy, empty ones included, ordered by name', function (string $class, string $id, string $title, string $taxonomy): void {
        new $class;

        expect(WP_Term_Query::$constructed)->toBe([[
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]]);
    })->with('term batches');

    it('queues nothing when the taxonomy has no terms', function (string $class): void {
        expect((new $class)->get_items_count())->toBe(0);
    })->with('term batches');
});

describe('process', function (): void {
    beforeEach(function (): void {
        $this->term = (object) ['name' => 'Asthma', 'slug' => 'asthma'];
    });

    it('looks nothing up and returns true for an item with no term id', function (string $class): void {
        $batch = new $class;
        Functions\expect('get_term_by')->never();
        Functions\expect('wp_insert_post')->never();

        expect(process_item($batch, 1, []))->toBeTrue()
            ->and(process_item($batch, 1, ['id' => 0]))->toBeTrue();
    })->with('term batches');

    it("inserts nothing when a post with the term's name already exists", function (string $class, string $id, string $title, string $taxonomy): void {
        $batch = new $class;
        Functions\expect('get_term_by')->once()->with('id', 7, $taxonomy)->andReturn($this->term);
        when('get_field')->justReturn(false);
        when('get_page_by_title')->justReturn((object) ['ID' => 1]);

        Functions\expect('wp_insert_post')->never();
        Functions\expect('update_post_meta')->never();

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('term batches');

    it('inserts a published post named after the term, at the term id plus the offset', function (string $class, string $id, string $title, string $taxonomy, int $offset, string $contentField, string $excerptField): void {
        $batch = new $class;
        stub_term($this->term, [$contentField => '<p>Body</p>', $excerptField => 'Short']);
        Functions\expect('wp_insert_post')->once()->with([
            'import_id' => 7 + $offset,
            'post_title' => 'Asthma',
            'post_content' => '<p>Body</p>',
            'post_excerpt' => 'Short',
            'post_name' => 'asthma',
            'post_status' => 'publish',
            'post_type' => $taxonomy,
        ])->andReturn(500);

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('term batches');

    it('logs the term id it is about to process', function (string $class): void {
        $batch = new $class;
        $log = tempnam(sys_get_temp_dir(), 'bp-log');
        $previous = ini_set('error_log', $log);
        when('get_term_by')->justReturn($this->term);
        when('get_field')->justReturn(false);
        when('get_page_by_title')->justReturn((object) ['ID' => 1]);

        try {
            process_item($batch, 7, ['id' => 7]);
        } finally {
            ini_set('error_log', $previous);
        }

        $logged = (string) file_get_contents($log);
        unlink($log);
        expect($logged)->toContain('ID: 7');
    })->with('term batches');

    it('treats any falsy but non-null lookup result as no existing post', function (string $class, string $id, string $title, string $taxonomy): void {
        $batch = new $class;
        stub_term($this->term, [], [], false);
        Functions\expect('wp_insert_post')->once()->andReturn(500);

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');

    it("looks the post up by the term's name", function (string $class): void {
        $batch = new $class;
        when('get_term_by')->justReturn($this->term);
        when('get_field')->justReturn(false);
        Functions\expect('get_page_by_title')->once()->with('Asthma')->andReturn((object) ['ID' => 1]);

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');

    it('copies each term meta value onto the new post, unserializing a serialized one', function (string $class): void {
        $batch = new $class;
        stub_term($this->term, [], ['color' => ['red', 'ignored'], 'size' => [serialize(['a' => 1])]]);
        when('wp_insert_post')->justReturn(500);
        $metaRequests = [];
        when('get_term_meta')->alias(function (int $term) use (&$metaRequests): array {
            $metaRequests[] = $term;

            return ['color' => ['red', 'ignored'], 'size' => [serialize(['a' => 1])]];
        });
        $copied = [];
        when('update_post_meta')->alias(function (int $post, string $key, $value) use (&$copied): void {
            $copied[] = [$post, $key, $value];
        });

        process_item($batch, 7, ['id' => 7]);

        expect($copied)->toBe([[500, 'color', 'red'], [500, 'size', ['a' => 1]]])
            ->and($metaRequests)->toBe([7]);
    })->with('term batches');

    it('copies no meta when the term has none', function (string $class): void {
        $batch = new $class;
        stub_term($this->term);
        when('wp_insert_post')->justReturn(500);
        Functions\expect('update_post_meta')->never();

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');

    it('stores the related ids of the other type, offset and as strings', function (string $class, string $id, string $title, string $taxonomy, int $offset, string $contentField, string $excerptField, string $relationField, int $relationOffset): void {
        $batch = new $class;
        stub_term($this->term, [$relationField => ['5', 12]]);
        when('wp_insert_post')->justReturn(500);
        Functions\expect('update_post_meta')->once()->with(500, $relationField, [(string) (5 + $relationOffset), (string) (12 + $relationOffset)]);

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('term batches');

    it('reads the relations from the term', function (string $class, string $id, string $title, string $taxonomy, int $offset, string $contentField, string $excerptField, string $relationField): void {
        $batch = new $class;
        when('get_term_by')->justReturn($this->term);
        when('get_page_by_title')->justReturn();
        when('get_term_meta')->justReturn([]);
        when('wp_insert_post')->justReturn(500);
        Functions\expect('get_field')->once()->with($contentField, $this->term)->andReturn('');
        Functions\expect('get_field')->once()->with($excerptField, $this->term)->andReturn('');
        Functions\expect('get_field')->once()->with($relationField, $this->term)->andReturn(false);

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');
});

it('does nothing in finish()', function (string $class): void {
    expect((new $class)->finish())->toBeNull();
})->with('term batches');

it('registers one batch of its class with the processor when the init function runs', function (string $class, string $id, string $title, string $taxonomy, int $offset, string $contentField, string $excerptField, string $relationField, int $relationOffset, string $init): void {
    $init();

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf($class);
})->with('term batches');
