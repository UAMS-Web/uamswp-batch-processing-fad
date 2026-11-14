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
// in this process; tests/Unit/Fad/LoadTimeTest.php pins it in a subprocess.
// @pest-mutate-ignore: RemoveNot
if (! defined('ABSPATH')) { // @pest-mutate-ignore: IfNegated
    exit('Direct access is not allowed.');
}

if (class_exists('WP_Batch')) {

    /**
     * Class MY_Example_Batch
     */
    class FaD_Treatment_Batch extends WP_Batch
    {
        /**
         * Unique identifier of each batch
         *
         * @var string
         */
        public $id = 'treatments_posts_from_taxonomy';

        /**
         * Describe the batch
         *
         * @var string
         */
        public $title = 'Make Treatment Posts from Taxonomy';

        /**
         * To setup the batch data use the push() method to add WP_Batch_Item instances to the queue.
         *
         * Note: If the operation of obtaining data is expensive, cache it to avoid slowdowns.
         */
        public function setup(): void
        {

            $taxonomy = 'treatment'; // Define Custom Taxonomy (source)
            // $post_type = 'condition'; // Define Custom Post Type (target)
            // Conditions offset 100000
            // Treatment offset 200000
            // $post_id_offset = 200000;

            // Get all Taxonomy
            $args = [
                'taxonomy' => $taxonomy,
                // 'parent' => 0, //In my case I only wanted top level terms returned
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
                // 'number' => 100, // Change this to batch process
                // 'offset' => 0, // Change this to batch process
                // 'slug' => 'implantable-hearing-devices'
                // 'count' => true
            ];

            $terms = new WP_Term_Query($args);

            // $users = get_users( array(
            // 	'number' => '40',
            // 	'role'   => 'author',
            // ) );

            foreach ($terms->terms as $term) {
                // error_log("ID: ".print_r($term, true));
                $this->push(new WP_Batch_Item($term->term_id, ['id' => $term->term_id]));
            }
        }

        /**
         * Handles processing of batch item. One at a time.
         *
         * In order to work it correctly you must return values as follows:
         *
         * - TRUE - If the item was processed successfully.
         * - WP_Error instance - If there was an error. Add message to display it in the admin area.
         *
         * @param  WP_Batch_Item  $item
         */
        public function process($item): bool
        {

            $t_id = $item->get_int_value('id');

            error_log('ID: '.$t_id);

            if (! empty($t_id)) {

                $taxonomy = 'treatment'; // Define Custom Taxonomy (source)
                $post_type = 'treatment'; // Define Custom Post Type (target)
                $post_id_offset = 200000;

                $term = get_term_by('id', $t_id, $taxonomy);
                // A term deleted since setup() queued it has nothing left to copy.
                if ($term === false) {
                    return true;
                }

                // $term_meta = get_option( "taxonomy_$t_id" );
                $name = $term->name; // Title
                $slug = $term->slug; // Slug

                $content = get_field('treatment_procedure_content', $term);
                $excerpt = get_field('treatment_procedure_short_desc', $term);
                // $description = $term->description; //Description

                $new_id = $t_id + $post_id_offset;

                // Above finds all the data from Custom Taxonomy and associated metadata.
                // We make a new post for each item, using same details from Taxonomy
                if (get_page_by_title($name) == null) { // If that post doesn't exist of course.
                    $new_post = [
                        'import_id' => $new_id,
                        'post_title' => $name,
                        'post_content' => WP_BP_Helper::acf_string($content), // Use Taxonomy description for Post Content
                        'post_excerpt' => WP_BP_Helper::acf_string($excerpt),
                        'post_name' => $slug,
                        'post_status' => 'publish',
                        'post_type' => $post_type,

                    ];
                    // Insert post
                    $post_id = wp_insert_post($new_post);

                    // Insert meta where it exists. Note that my meta is stored like so: http://pippinsplugins.com/adding-custom-meta-fields-to-taxonomies/
                    // if (!empty($term_meta['artist_showcase_meta'])) : update_post_meta ($post_id, '_cmb_showcase', $term_meta['artist_showcase_meta']); endif;
                    foreach (WP_BP_Helper::first_meta_values(get_term_meta($t_id)) as $key => $v) {
                        update_post_meta($post_id, $key, $v);
                    }

                    // Custom Fields
                    // Custom Field Name to update
                    $acf_field = 'treatment_procedure_conditions';
                    // Offset amount -> opposite of the current taxonomy
                    // Conditions offset 100000
                    // Treatment offset 200000
                    $acf_offset = 100000;
                    $items = get_field($acf_field, $term);
                    if ($items) {
                        $items_cpt = [];
                        foreach (WP_BP_Helper::acf_ids($items) as $item) {
                            $item_id = intval($item) + $acf_offset;
                            $items_cpt[] = strval($item_id);
                        }

                        update_post_meta($post_id, $acf_field, $items_cpt);
                    }

                    // } else { // Do sweet F.A.
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
        public function finish(): void
        {
            // Do something after process is finished.
            // You have $this->items, or other data you can set.
        }
    }

    /**
     * Initialize the batches.
     */
    function treatment_batch_processing_init(): void
    {
        $batch = new FaD_Treatment_Batch;
        WP_Batch_Processor::get_instance()->register($batch);
    }

    // Registers the batch as the file loads, before any test; tests/Unit/Fad/LoadTimeTest.php pins the hook, its priority and its argument count.
    add_action('wp_batch_processing_init', 'treatment_batch_processing_init', 15, 1); // @pest-mutate-ignore: DecrementInteger, IncrementInteger, RemoveFunctionCall
}
