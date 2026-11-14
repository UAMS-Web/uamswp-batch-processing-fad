<?php

/**
 * A concrete WP_Batch for the Unit suite: queues the ids it is given, records what it
 * processes, and answers process() with a value the test sets.
 */

declare(strict_types=1);

final class FakeBatch extends WP_Batch
{
    /** @var array<int, int|string> The ids process() was called with, in order. */
    public array $processedIds = [];

    /** @var bool|WP_Error What process() returns. */
    public $result = true;

    /** How many times finish() ran. */
    public int $finishCount = 0;

    /** @param array<int, int|string> $seed */
    public function __construct(string $id = 'fake', private array $seed = [])
    {
        $this->id = $id;

        parent::__construct();
    }

    public function setup(): void
    {
        foreach ($this->seed as $id) {
            $this->push(new WP_Batch_Item($id, ['n' => $id]));
        }
    }

    public function process($item)
    {
        $this->processedIds[] = $item->id;

        return $this->result;
    }

    public function finish(): void
    {
        $this->finishCount++;
    }

    /**
     * Drops the queue, so a test can reach push()'s non-array guard.
     *
     * WP_Batch::$items is documented as an array, and the guard exists for a subclass that breaks that,
     * so the value goes in through reflection: a plain assignment would be the very contract break the
     * analyzer reports.
     */
    public function breakItems(): void
    {
        (new ReflectionProperty(WP_Batch::class, 'items'))->setValue($this, null);
    }

    /** @param WP_Batch_Item $item */
    public function pushPublic($item): void
    {
        $this->push($item);
    }

    /** @return array<int, WP_Batch_Item> */
    public function items(): array
    {
        return $this->items;
    }
}
