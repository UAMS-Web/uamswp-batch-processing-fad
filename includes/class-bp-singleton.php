<?php
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
if ( ! defined( 'ABSPATH' ) ) { // @pest-mutate-ignore: IfNegated
	die( 'Direct access is not allowed.' );
}

trait WP_BP_Singleton {
	/**
	 * The current instance
	 */
	protected static $instance;

	/**
	 * Returns the current instance
	 */
	final public static function get_instance() {
		return static::$instance ?? static::$instance = new static;
	}

	/**
	 * WP_BP_Singleton constructor.
	 */
	final private function __construct() {
		$this->init();
	}

	// Prevent instances
	protected function init() {
	}

	/**
	 * Refuses unserialize(): a singleton is built through get_instance() only.
	 *
	 * @throws Exception Always.
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize a singleton.' );
	}

	private function __clone() {
	}
}