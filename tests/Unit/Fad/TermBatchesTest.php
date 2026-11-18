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
function stub_term(object $term, array $fields = [], array $meta = []): void
{
    when('get_term_by')->justReturn($term);
    when('get_field')->alias(fn (string $name) => $fields[$name] ?? false);
    stub_no_term_post();
    when('get_term_meta')->justReturn($meta);
    when('is_serialized')->alias(fn ($value): bool => is_string($value) && str_starts_with($value, 'a:'));
}

/**
 * Stubs WP_BP_Helper::term_post_exists()'s lookup to find no post of the type with the term's name.
 */
function stub_no_term_post(): void
{
    when('get_posts')->justReturn([]);
}

it('carries its batch id and title', function (string $class, string $id, string $title): void {
    expect(batch_of($class)->id)->toBe($id)
        ->and(batch_of($class)->title)->toBe($title);
})->with('term batches');

describe('setup', function (): void {
    it('queues one item per term, in query order, carrying the term id', function (string $class): void {
        WP_Term_Query::$responses = [[(object) ['term_id' => 30], (object) ['term_id' => 10], (object) ['term_id' => 20]]];
        $store = [];
        stub_options($store);

        $batch = batch_of($class);
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
        expect(batch_of($class)->get_items_count())->toBe(0);
    })->with('term batches');
});

describe('process', function (): void {
    beforeEach(function (): void {
        /** @var UnitTestCase $this */
        $this->term = (object) ['name' => 'Asthma', 'slug' => 'asthma'];
    });

    it('looks nothing up and returns true for an item with no term id', function (string $class): void {
        $batch = batch_of($class);
        Functions\expect('get_term_by')->never();
        Functions\expect('wp_insert_post')->never();

        expect(process_item($batch, 1, []))->toBeTrue()
            ->and(process_item($batch, 1, ['id' => 0]))->toBeTrue();
    })->with('term batches');

    it('inserts nothing and returns true when the term no longer exists', function (string $class, string $id, string $title, string $taxonomy): void {
        $batch = batch_of($class);
        Functions\expect('get_term_by')->once()->with('id', 7, $taxonomy)->andReturn(false);
        Functions\expect('get_field')->never();
        Functions\expect('wp_insert_post')->never();

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('term batches');

    it("inserts nothing when a post of its type with the term's name already exists", function (string $class, string $id, string $title, string $taxonomy): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        Functions\expect('get_term_by')->once()->with('id', 7, $taxonomy)->andReturn($this->term);
        when('get_field')->justReturn(false);
        when('get_posts')->justReturn([1]);

        Functions\expect('wp_insert_post')->never();
        Functions\expect('update_post_meta')->never();

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('term batches');

    it("inserts the post when another term's post of its type holds the offset id", function (string $class, string $id, string $title, string $taxonomy): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        when('get_term_by')->justReturn($this->term);
        when('get_field')->justReturn(false);
        when('get_term_meta')->justReturn([]);
        // A post of the type sits at the offset id under another name, as an ordinary id an earlier insert got can.
        when('get_post_type')->justReturn($taxonomy);
        when('get_posts')->justReturn([]);
        Functions\expect('wp_insert_post')->once()->andReturn(500);

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');

    it("inserts the post when only a page has the term's name", function (string $class, string $id, string $title, string $taxonomy): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        when('get_term_by')->justReturn($this->term);
        when('get_field')->justReturn(false);
        when('get_term_meta')->justReturn([]);
        // Only a page has the name, and a page is not a post of the batch's type.
        Functions\expect('get_posts')->once()->with(Mockery::on(fn (array $args): bool => $args['post_type'] === $taxonomy && $args['title'] === 'Asthma'))->andReturn([]);
        Functions\expect('wp_insert_post')->once()->andReturn(500);

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');

    it('makes one post per term when the batch runs twice', function (string $class, string $id, string $title, string $taxonomy, int $offset): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        stub_term($this->term);
        // A small posts table. As in WordPress, wp_insert_post() uses the import id only while no post holds
        // it, and otherwise gives the post an ordinary auto-increment id; the lookup reads the table.
        $table = new class
        {
            /** @var array<int, string> Post type by post id. */
            public array $types = [];

            /** @var array<int, string> Title by post id. */
            public array $titles = [];

            /**
             * @param  array<mixed>  $post  What the batch passed to wp_insert_post().
             */
            public function insert(array $post): int
            {
                $importId = $post['import_id'] ?? null;
                $type = $post['post_type'] ?? null;
                $title = $post['post_title'] ?? null;
                if (! is_int($importId) || ! is_string($type) || ! is_string($title)) {
                    throw new LogicException('wp_insert_post() was called without an int import_id and string post_type and post_title');
                }

                $postId = isset($this->types[$importId]) ? max(array_keys($this->types)) + 1 : $importId;
                $this->types[$postId] = $type;
                $this->titles[$postId] = $title;

                return $postId;
            }

            /**
             * @param  array<mixed>  $args  What WP_BP_Helper passed to get_posts().
             * @return array<int, int>
             */
            public function find(array $args): array
            {
                $found = [];
                foreach ($this->types as $postId => $type) {
                    if ($type === $args['post_type'] && $this->titles[$postId] === $args['title']) {
                        $found[] = $postId;
                    }
                }

                return $found;
            }
        };
        when('wp_insert_post')->alias(fn (array $post): int => $table->insert($post));
        when('get_posts')->alias(fn (array $args): array => $table->find($args));

        process_item($batch, 7, ['id' => 7]);
        process_item($batch, 7, ['id' => 7]);

        expect(array_keys($table->types))->toBe([7 + $offset]);
    })->with('term batches');

    it('inserts a published post named after the term, at the term id plus the offset', function (string $class, string $id, string $title, string $taxonomy, int $offset, string $contentField, string $excerptField): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
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
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        $log = tempnam(sys_get_temp_dir(), 'bp-log');
        $previous = ini_set('error_log', $log);
        when('get_term_by')->justReturn($this->term);
        when('get_field')->justReturn(false);
        when('get_posts')->justReturn([1]);

        try {
            process_item($batch, 7, ['id' => 7]);
        } finally {
            ini_set('error_log', $previous);
        }

        $logged = (string) file_get_contents($log);
        unlink($log);
        expect($logged)->toContain('ID: 7');
    })->with('term batches');

    it("looks the post up by the term's name within its post type", function (string $class, string $id, string $title, string $taxonomy): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        when('get_term_by')->justReturn($this->term);
        when('get_field')->justReturn(false);
        Functions\expect('get_posts')->once()->with(Mockery::on(fn (array $args): bool => $args['post_type'] === $taxonomy && $args['title'] === 'Asthma'))->andReturn([1]);

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');

    it('copies each term meta value onto the new post, unserializing a serialized one', function (string $class): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
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
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        stub_term($this->term);
        when('wp_insert_post')->justReturn(500);
        Functions\expect('update_post_meta')->never();

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');

    it('stores the related ids of the other type, offset and as strings', function (string $class, string $id, string $title, string $taxonomy, int $offset, string $contentField, string $excerptField, string $relationField, int $relationOffset): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        stub_term($this->term, [$relationField => ['5', 12]]);
        when('wp_insert_post')->justReturn(500);
        Functions\expect('update_post_meta')->once()->with(500, $relationField, [(string) (5 + $relationOffset), (string) (12 + $relationOffset)]);

        expect(process_item($batch, 7, ['id' => 7]))->toBeTrue();
    })->with('term batches');

    it('reads the relations from the term', function (string $class, string $id, string $title, string $taxonomy, int $offset, string $contentField, string $excerptField, string $relationField): void {
        /** @var UnitTestCase $this */
        $batch = batch_of($class);
        when('get_term_by')->justReturn($this->term);
        stub_no_term_post();
        when('get_term_meta')->justReturn([]);
        when('wp_insert_post')->justReturn(500);
        Functions\expect('get_field')->once()->with($contentField, $this->term)->andReturn('');
        Functions\expect('get_field')->once()->with($excerptField, $this->term)->andReturn('');
        Functions\expect('get_field')->once()->with($relationField, $this->term)->andReturn(false);

        process_item($batch, 7, ['id' => 7]);
    })->with('term batches');
});

it('does nothing in finish()', function (string $class): void {
    $batch = batch_of($class);
    $batch->finish();

    expect($batch->get_items_count())->toBe(0);
})->with('term batches');

it('registers one batch of its class with the processor when the init function runs', function (string $class, string $id, string $title, string $taxonomy, int $offset, string $contentField, string $excerptField, string $relationField, int $relationOffset, string $init): void {
    run_init($init);

    $batches = WP_Batch_Processor::get_instance()->get_batches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0])->toBeInstanceOf(class_named($class));
})->with('term batches');
