<?php

declare(strict_types=1);

/********************************************************************
 * Copyright (C) 2019 Darko Gjorgjijoski (https://darkog.com)
 *
 * This file is part of WP Batch Processing
 *
 * WP Batch Processing is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * WP Batch Processing is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WP Batch Processing. If not, see <https://www.gnu.org/licenses/>.
 **********************************************************************/

// The direct-access guard runs as the file loads, before any test, and dies when it fires, so no test can reach it
// in this process; tests/Unit/LoadTimeTest.php pins it in a subprocess.
// @pest-mutate-ignore: RemoveNot
if (! defined('ABSPATH')) { // @pest-mutate-ignore: IfNegated
    exit('Direct access is not allowed.');
}

/**
 * Class WP_Batch_Processor
 */
class WP_Batch_Processor
{
    use WP_BP_Singleton;

    /**
     * List of batches
     *
     * @var array<int, WP_Batch>
     */
    protected $batches;

    /**
     * Initializes the runner
     */
    public function init(): void
    {
        $this->batches = [];
    }

    /**
     * Register batch
     *
     * @param  WP_Batch  $batch
     */
    public function register($batch): void
    {
        $this->batches[] = $batch;
    }

    /**
     * Returns array of registered batches
     *
     * @return array<int, WP_Batch>
     */
    public function get_batches()
    {
        return $this->batches;
    }

    /**
     * Returns a batch from the registered ones.
     *
     * The id is compared strictly, so an int never matches a batch whose id is the string of the same digits.
     *
     * @param  int|string  $id
     * @return null|WP_Batch
     */
    public function get_batch($id)
    {
        foreach ($this->batches as $batch) {
            if ($batch->id === $id) {
                return $batch;
            }
        }

        return null;
    }
}

// Builds the instance as the file loads, before any test; tests/Unit/LoadTimeTest.php pins it in a subprocess.
WP_Batch_Processor::get_instance(); // @pest-mutate-ignore: RemoveMethodCall
