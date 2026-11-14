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
 * Class WP_Batch_Item
 */
class WP_Batch_Item
{
    /**
     * WP_Batch_Item constructor
     *
     * @param  int  $id
     * @param  mixed  $data
     */
    public function __construct(
        /**
         * Unique identifier of the batch item
         */
        public $id,
        /**
         * Additional data for the item
         */
        public $data = null
    ) {}

    /**
     * Return data value
     *
     *
     * @return mixed|null
     */
    public function get_value($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
}
