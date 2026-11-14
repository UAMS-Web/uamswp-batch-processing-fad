<?php

/**
 * A concrete WP_Batch for the Unit suite: queues the ids it is given, records what it
 * processes, and answers process() with a value the test sets.
 */

declare(strict_types=1);

final class FakeBatch extends WP_Batch
{
    /** @var array<int, int|string> */
    private array $seed;

    /** @var array<int, int|string> The ids process() was called with, in order. */
    public array $processedIds = [];

    /** @var bool|WP_Error What process() returns. */
    public $result = true;

    /** How many times finish() ran. */
    public int $finishCount = 0;

    /** @param array<int, int|string> $ids */
    public function __construct(string $id = 'fake', array $ids = [])
    {
        $this->id = $id;
        $this->seed = $ids;

        parent::__construct();
    }

    public function setup()
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

    public function finish()
    {
        $this->finishCount++;
    }

    /** Drops the queue, so a test can reach push()'s non-array guard. */
    public function breakItems(): void
    {
        $this->items = null;
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
