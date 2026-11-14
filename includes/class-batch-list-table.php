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

// Runs as the file loads, after the Unit bootstrap has declared its stand-in for WP_List_Table, so no test can reach
// the require in this process; tests/Unit/LoadTimeTest.php pins it in a subprocess.
// @pest-mutate-ignore: RemoveNot, ConcatSwitchSides, ConcatRemoveRight, ConcatRemoveLeft
if (! class_exists('WP_List_Table')) { // @pest-mutate-ignore: IfNegated
    require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class WP_BP_List_Table
 */
class WP_BP_List_Table extends WP_List_Table
{
    /**
     * The status filter read from the request by prepare_items().
     *
     * @var string|null
     */
    public $page_status;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'batch',
            'plural' => 'batches',
            'ajax' => false,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function get_table_classes(): array
    {
        return ['widefat', 'fixed', 'striped', $this->_args['plural']];
    }

    /**
     * Message to show if no designation found
     */
    public function no_items(): void
    {
        _e('No batches found. Read the documentation on the plugin github page to see how to register ones.', 'wp-batch-processing');
    }

    /**
     * Default column values if no callback found
     *
     * @param  WP_Batch  $item
     * @param  string  $column_name
     * @return string
     */
    public function column_default($item, $column_name)
    {

        return match ($column_name) {
            'title' => $item->title,
            'total_processed' => $item->get_processed_count(),
            'total_items' => $item->get_items_count(),
            default => $item->$column_name ?? '',
        };
    }

    /**
     * Get the column names
     *
     * @return array<string, string>
     */
    public function get_columns(): array
    {
        return [
            'title' => __('Title', 'wp-batch-processing'),
            'total_processed' => __('Total Processed', 'wp-batch-processing'),
            'total_items' => __('Total Items', 'wp-batch-processing'),
        ];
    }

    /**
     * Render the designation name column
     *
     * @param  WP_Batch  $item
     */
    public function column_title($item): string
    {

        $actions = [];
        $actions['edit'] = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', admin_url('admin.php?page=dg-batches&action=view&id='.$item->id), $item->id, __('Manage Batch', 'wp-batch-processing'), __('Manage', 'wp-batch-processing'));

        return sprintf('<a href="%1$s"><strong>%2$s</strong></a> %3$s', admin_url('admin.php?page=dg-batches&action=view&id='.$item->id), $item->title, $this->row_actions($actions));
    }

    /**
     * Get sortable columns
     *
     * @return array<string, mixed>
     */
    public function get_sortable_columns(): array
    {
        return [];
    }

    /**
     * Set the bulk actions
     *
     * @return array<string, string>
     */
    public function get_bulk_actions(): array
    {
        return [];
    }

    /**
     * Render the checkbox column
     *
     * @param  object  $item
     */
    public function column_cb($item): string
    {
        return '';
    }

    /**
     * Prepare the class items
     */
    public function prepare_items(): void
    {

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->page_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '2';

        $this->items = WP_Batch_Processor::get_instance()->get_batches();
    }
}
