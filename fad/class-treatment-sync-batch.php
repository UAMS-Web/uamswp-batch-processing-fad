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
	class FaD_Treatment_Sync_Batch extends WP_Batch {

		/**
		 * Unique identifier of each batch
		 * @var string
		 */
		public $id = 'treatments_update_sync_batch';

		/**
		 * Describe the batch
		 * @var string
		 */
		public $title = 'Update Treatments Sync to Conditions';

		/**
		 * To setup the batch data use the push() method to add WP_Batch_Item instances to the queue.
		 *
		 * Note: If the operation of obtaining data is expensive, cache it to avoid slowdowns.
		 *
		 * @return void
		 */
		public function setup() {

			$cpt = 'treatment'; // Define Custom Post Type (target)


			// Get all Taxonomy
			$args = array(
				'post_type' => $cpt,
                'orderby' => 'title',
                'order' => 'ASC',
                'posts_per_page' => -1, 
                'post_status' => 'any',
                'no_found_rows' => true,
                'update_post_term_cache' => false,
                'cache_results'  => false,
                // 'p' => '103291'
			);

			$the_query =  new WP_Query( $args );

			if ( $the_query->have_posts() ) {
                while ( $the_query->have_posts() ) {
                    $the_query->the_post();
                    $this->push( new WP_Batch_Item( get_the_ID(), array( 'id' => get_the_ID() ) ) );
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
		 *
		 * @return bool|\WP_Error
		 */
		public function process( $item ) {

            $p_id = $item->get_value( 'id' );

            // error_log("ID: ".$t_id);

			if (!empty($p_id)) {
                // Conditions
				$value = get_field('treatment_conditions', $p_id);
				if( is_array($value) ) {
					foreach( $value as $post_id2new ) {
                        // load existing related posts
                        $value2new = get_field('condition_treatments', $post_id2new, false);
            
                        // allow for selected posts to not contain a value
                        if( empty($value2new) ) {
                            $value2new = array();
                        }
            
                        // bail early if the current $post_id is already found in selected post's $value2new
                        if( in_array($post_id, $value2new) ) continue;
            
                        // append the current $post_id to the selected post's 'related_posts' value
                        $value2new[] = $p_id;
            
                        // update the selected post's value (use field's key for performance)
                        update_field('field_condition_treatments', $value2new, $post_id2new);
                    }
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
		 * @return void
		 */
		public function finish() {
			// Do something after process is finished.
			// You have $this->items, or other data you can set.
		}

	}

	/**
	 * Initialize the batches.
	 */
	function treatment_sync_batch_processing_init() {
		$batch = new FaD_Treatment_Sync_Batch();
		WP_Batch_Processor::get_instance()->register( $batch );
	}

	// Registers the batch as the file loads, before any test; tests/Unit/Fad/LoadTimeTest.php pins the hook, its priority and its argument count.
	add_action( 'wp_batch_processing_init', 'treatment_sync_batch_processing_init', 15, 1 ); // @pest-mutate-ignore: DecrementInteger, IncrementInteger, RemoveFunctionCall
}


