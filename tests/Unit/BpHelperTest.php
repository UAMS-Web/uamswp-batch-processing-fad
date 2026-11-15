<?php

/**
 * WP_BP_Helper: renders a view from the plugin's views directory, and reads the untyped values Advanced
 * Custom Fields and WordPress hand back (lists, ids, posts, strings, term meta) as typed ones.
 *
 * @see includes/class-bp-helper.php
 */

declare(strict_types=1);

use function Brain\Monkey\Functions\when;

it('reports a view that does not exist', function (): void {
    ob_start();
    WP_BP_Helper::render('no-such-view');

    expect(ob_get_clean())->toBe('View no-such-view not found');
});

it('renders a view with the data extracted into scope', function (): void {
    ob_start();
    WP_BP_Helper::render('greeting', ['name' => 'Batch']);

    expect(ob_get_clean())->toBe('Hello, Batch');
});

describe('acf_list', function (): void {
    it('is an empty list for an empty value, without a warning', function (mixed $value): void {
        $result = null;
        $warnings = record_warnings(function () use (&$result, $value): void {
            $result = WP_BP_Helper::acf_list($value);
        });

        expect($result)->toBe([])
            ->and($warnings)->toBe([]);
    })->with(['false' => [false], 'null' => [null], 'empty string' => [''], 'empty array' => [[]], 'zero' => [0]]);

    it('returns the entries of an array as a list', function (): void {
        expect(WP_BP_Helper::acf_list([3 => 'a', 7 => 'b']))->toBe(['a', 'b']);
    });

    it('warns and returns an empty list for a value that is not an array', function (mixed $value, string $type): void {
        $result = null;
        $warnings = record_warnings(function () use (&$result, $value): void {
            $result = WP_BP_Helper::acf_list($value);
        });

        expect($result)->toBe([])
            ->and($warnings)->toBe(["foreach() argument must be of type array|object, {$type} given"]);
    })->with(['string' => ['abc', 'string'], 'int' => [5, 'int'], 'true' => [true, 'bool']]);
});

describe('acf_ids', function (): void {
    it('reads integers and numeric strings as integers, in order', function (): void {
        expect(WP_BP_Helper::acf_ids([4, '5', 6]))->toBe([4, 5, 6]);
    });

    it('warns about an entry that is not an id and leaves it out', function (): void {
        $result = null;
        $warnings = record_warnings(function () use (&$result): void {
            $result = WP_BP_Helper::acf_ids([1, 'x', [2], 3.5, 2]);
        });

        expect($result)->toBe([1, 2])
            ->and($warnings)->toBe([
                'Skipped a list entry that is not an id: string given',
                'Skipped a list entry that is not an id: array given',
                'Skipped a list entry that is not an id: float given',
            ]);
    });

    it('is empty for an empty value and warns for a value that is not an array', function (): void {
        $warnings = record_warnings(function (): void {
            expect(WP_BP_Helper::acf_ids(false))->toBe([])
                ->and(WP_BP_Helper::acf_ids('abc'))->toBe([]);
        });

        expect($warnings)->toBe(['foreach() argument must be of type array|object, string given']);
    });
});

describe('acf_posts', function (): void {
    it('keeps integers and post objects, and reads numeric strings as integers', function (): void {
        $post = new WP_Post(new stdClass);

        expect(WP_BP_Helper::acf_posts([4, '5', $post]))->toBe([4, 5, $post]);
    });

    it('warns about an entry that is neither and leaves it out', function (): void {
        $result = null;
        $warnings = record_warnings(function () use (&$result): void {
            $result = WP_BP_Helper::acf_posts([1, 'x', null, 2]);
        });

        expect($result)->toBe([1, 2])
            ->and($warnings)->toBe([
                'Skipped a list entry that is not a post: string given',
                'Skipped a list entry that is not a post: null given',
            ]);
    });

    it('is empty for an empty value and warns for a value that is not an array', function (): void {
        $warnings = record_warnings(function (): void {
            expect(WP_BP_Helper::acf_posts(null))->toBe([])
                ->and(WP_BP_Helper::acf_posts(9))->toBe([]);
        });

        expect($warnings)->toBe(['foreach() argument must be of type array|object, int given']);
    });
});

describe('acf_string', function (): void {
    it('reads a scalar as a string', function (mixed $value, string $expected): void {
        expect(WP_BP_Helper::acf_string($value))->toBe($expected);
    })->with(['string' => ['abc', 'abc'], 'int' => [12, '12'], 'float' => [1.5, '1.5'], 'true' => [true, '1'], 'false' => [false, '']]);

    it('reads anything that is not a scalar as an empty string', function (mixed $value): void {
        expect(WP_BP_Helper::acf_string($value))->toBe('');
    })->with(['null' => [null], 'array' => [['a']], 'object' => [new stdClass]]);
});

describe('first_meta_values', function (): void {
    beforeEach(function (): void {
        when('is_serialized')->alias(fn ($value): bool => is_string($value) && str_starts_with($value, 'a:'));
    });

    it('takes the first value of each meta key', function (): void {
        expect(WP_BP_Helper::first_meta_values(['color' => ['red', 'blue'], 'size' => ['L']]))->toBe(['color' => 'red', 'size' => 'L']);
    });

    it('unserializes a serialized value and leaves a plain one alone', function (): void {
        expect(WP_BP_Helper::first_meta_values(['list' => [serialize([1, 2])], 'text' => ['plain']]))
            ->toBe(['list' => [1, 2], 'text' => 'plain']);
    });

    it('reads a key with no entries, or entries that are not a list, as null', function (): void {
        expect(WP_BP_Helper::first_meta_values(['empty' => [], 'bare' => 'x']))->toBe(['empty' => null, 'bare' => null]);
    });

    it('is empty when the meta is not an array', function (mixed $meta): void {
        expect(WP_BP_Helper::first_meta_values($meta))->toBe([]);
    })->with(['false' => [false], 'string' => ['x'], 'null' => [null]]);
});
