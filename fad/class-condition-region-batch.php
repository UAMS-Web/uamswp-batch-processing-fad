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
// in this process; tests/Unit/Fad/LoadTimeTest.php pins it in a subprocess.
// @pest-mutate-ignore: RemoveNot
if ( ! defined( 'ABSPATH' ) ) { // @pest-mutate-ignore: IfNegated
	die( 'Direct access is not allowed.' );
}

if ( class_exists( 'WP_Batch' ) ) {

	/**
	 * Class MY_Example_Batch
	 */
	class FaD_Condition_Region_Batch extends WP_Batch {

		/**
		 * Unique identifier of each batch
		 * @var string
		 */
		public $id = 'condition_update_regions';

		/**
		 * Describe the batch
		 * @var string
		 */
		public $title = 'Update Condition Regions';

		/**
         * To setup the batch data use the push() method to add WP_Batch_Item instances to the queue.
         *
         * Note: If the operation of obtaining data is expensive, cache it to avoid slowdowns.
         */
        public function setup(): void {

			$cpt = 'condition'; // Define Custom Post Type (target)


			// Get all Taxonomy
			$args = [
				'post_type' => $cpt,
                'orderby' => 'title',
                'order' => 'ASC',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'no_found_rows' => true,
                'update_post_term_cache' => false,
                'cache_results'  => false,
                // 'offset' => 0, // Change this to batch process
                // 'p' => '102770'
			];

			$the_query =  new WP_Query( $args );

			// $users = get_users( array(
			// 	'number' => '40',
			// 	'role'   => 'author',
			// ) );

			if ( $the_query->have_posts() ) {
                while ( $the_query->have_posts() ) {
                    $the_query->the_post();
                    $this->push( new WP_Batch_Item( get_the_ID(), [ 'id' => get_the_ID() ] ) );
                }
            }

            wp_reset_postdata();
		}

		/**
         * Handles processing of batch item. One at a time.
         *
         * In order to work it correctly you must return values as follows:
         *
         * - TRUE - If the item was processed successfully.
         * - WP_Error instance - If there was an error. Add message to display it in the admin area.
         *
         * @param WP_Batch_Item $item
         */
        public function process( $item ): bool {

            $p_id = $item->get_value( 'id' );

			// error_log("ID: ".$t_id);

			if (!empty($p_id)) {
                // Conditions
				$acf_field = 'condition_locations';
				$items = get_field($acf_field, $p_id);
				if ($items) {
					$regions = [];
					foreach( $items as $item ):
						$region = get_field( 'location_region', $item);
						$regions[] = strval($region);
						// break loop after first iteration = primary location
						// break;
					endforeach;

					$regions = array_unique($regions);
					update_field ('condition_region', $regions, $p_id);
                }

			}

			// // Retrieve the custom data
			// $author_id = $item->get_value( 'author_id' );

			// // Return WP_Error if the item processing failed (In our case we simply skip author with user id 5)
			// if ( $author_id == 5 ) {
			// 	return new WP_Error( 302, "Author skipped" );
			// }

			// Do the expensive processing here. eg. Sending email.
			// ...

			// Return true if the item processing is successful.
			return true;
		}

		/**
         * Called when specific process is finished (all items were processed).
         * This method can be overriden in the process class.
         */
        public function finish(): void {
			// Do something after process is finished.
			// You have $this->items, or other data you can set.
		}

	}

	/**
	 * Initialize the batches.
	 */
	function condition_region_batch_processing_init(): void {
		$batch = new FaD_Condition_Region_Batch();
		WP_Batch_Processor::get_instance()->register( $batch );
	}

	// Registers the batch as the file loads, before any test; tests/Unit/Fad/LoadTimeTest.php pins the hook, its priority and its argument count.
	add_action( 'wp_batch_processing_init', 'condition_region_batch_processing_init', 15, 1 ); // @pest-mutate-ignore: DecrementInteger, IncrementInteger, RemoveFunctionCall
}


