<?php

/**
 * Base case for every Unit test.
 */

declare(strict_types=1);

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

use function Brain\Monkey\Functions\stubTranslationFunctions;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Brain Monkey around every test, and the properties the tests share between `beforeEach()` and `it()`.
 *
 * setUp() defines WordPress' hook functions and arms Patchwork; tearDown() verifies every expectation the
 * test set (a `->once()` that never happened fails here) and removes every mock, so nothing leaks into the
 * next test.
 *
 * The assertion count: Brain Monkey's expectations are Mockery expectations, which PHPUnit does not count.
 * Mockery's PHPUnit integration adds them, so a test whose only check is `Functions\expect(...)->once()`
 * is not risky, and failOnRisky does not fail it.
 *
 * A class rather than closures in tests/Pest.php, so PHPStan types `$this` in a test as this case and not
 * as Pest's `@internal` TestCall.
 */
abstract class UnitTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * The backing array for stub_options().
     *
     * @var array<string, mixed>
     */
    public array $store = [];

    /**
     * The batch under test, where a file builds it in `beforeEach()`.
     */
    public WP_Batch $batch;

    /**
     * The term a term batch's test feeds the batch.
     */
    public object $term;

    /**
     * What the stubbed nonce check answers.
     */
    public bool $nonceValid = true;

    /**
     * What the stubbed capability check answers.
     */
    public bool $userCan = true;

    /**
     * The calls the AJAX handler test records.
     *
     * @var array<string, list<array<int|string, mixed>>>
     */
    public array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        setUp();

        // __(), _e(), esc_html__() and the rest return or echo their text unchanged.
        stubTranslationFunctions();

        // No queued query responses or recorded query arguments carry over from the last test.
        WP_Query::reset();
        WP_Term_Query::reset();
        $GLOBALS['bp_reset_postdata_calls'] = 0;
    }

    protected function tearDown(): void
    {
        tearDown();

        parent::tearDown();
    }
}
