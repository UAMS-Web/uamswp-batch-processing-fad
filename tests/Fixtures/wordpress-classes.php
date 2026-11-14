<?php

/**
 * Minimal stand-ins for the two WordPress classes the Unit suite meets.
 *
 * The Unit suite runs with no WordPress, so these carry only the members the plugin
 * calls. How the real classes behave is the Integration suite's subject; here they
 * give the plugin classes a parent to extend and a value to receive.
 */

declare(strict_types=1);

if (! class_exists('WP_Error')) {
    /**
     * Stand-in for WP_Error: a code and a message, read back by get_error_message().
     */
    class WP_Error
    {
        public function __construct(private string $code = '', private string $message = '') {}

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (! class_exists('WP_List_Table')) {
    /**
     * Stand-in for WP_List_Table: keeps its constructor arguments in $_args and renders
     * row_actions() and display() in a fixed, easily asserted shape.
     */
    #[AllowDynamicProperties]
    class WP_List_Table
    {
        /** @var array<string, mixed> */
        protected $_args = [];

        /** @var array<int, mixed> */
        public $items = [];

        /** @var array<int, array<mixed>> */
        public $_column_headers = [];

        /** @param array<string, mixed> $args */
        public function __construct($args = [])
        {
            $this->_args = $args;
        }

        /** @param array<string, string> $actions */
        public function row_actions($actions)
        {
            return '<div class="row-actions">'.implode(' | ', $actions).'</div>';
        }

        public function display()
        {
            echo 'table:'.count($this->items);
        }
    }
}
