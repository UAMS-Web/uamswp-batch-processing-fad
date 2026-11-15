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

if (! class_exists('WP_Post')) {
    /**
     * Stand-in for WP_Post: a value an ACF list can hold in place of a post id.
     */
    class WP_Post
    {
        public function __construct(public object $post = new stdClass) {}
    }
}

if (! class_exists('WP_Query')) {
    /**
     * Stand-in for WP_Query, for the batches' setup(): each new instance takes the next list of
     * post ids from $responses, walks it with have_posts() and the_post(), and exposes the id
     * being visited as $currentId (which the get_the_ID() stub reads). Every set of arguments
     * it was built with is kept in $constructed.
     */
    class WP_Query
    {
        /** @var array<int, array<int, int>> Lists of post ids, handed out one per new query. */
        public static array $responses = [];

        /** @var array<int, array<string, mixed>> The arguments of every query built. */
        public static array $constructed = [];

        /** The id of the post the_post() last moved to. */
        public static int $currentId = 0;

        /** @var array<int, int> */
        private array $ids;

        private int $cursor = -1;

        /** How many times have_posts() was asked. */
        private int $asked = 0;

        /** @param array<string, mixed> $args */
        public function __construct($args = [])
        {
            self::$constructed[] = $args;
            $this->ids = array_shift(self::$responses) ?? [];
        }

        /**
         * Whether a post is left to visit. A loop that never calls the_post() would ask forever, so
         * the stand-in fails after more questions than the list can account for, which turns a hang
         * into a failing test.
         */
        public function have_posts(): bool
        {
            if (++$this->asked > count($this->ids) + 2) {
                throw new LogicException('have_posts() was asked again without the_post() moving on.');
            }

            return $this->cursor + 1 < count($this->ids);
        }

        public function the_post(): void
        {
            $this->cursor++;
            self::$currentId = $this->ids[$this->cursor];
        }

        /** Forgets every queued response, recorded argument and the current id. */
        public static function reset(): void
        {
            self::$responses = [];
            self::$constructed = [];
            self::$currentId = 0;
        }
    }
}

if (! class_exists('WP_Term_Query')) {
    /**
     * Stand-in for WP_Term_Query: each new instance takes the next list of terms from $responses
     * and exposes it as $terms. Every set of arguments it was built with is kept in $constructed.
     */
    class WP_Term_Query
    {
        /** @var array<int, array<int, object{term_id: int}>> Lists of terms, handed out one per new query. */
        public static array $responses = [];

        /** @var array<int, array<string, mixed>> The arguments of every query built. */
        public static array $constructed = [];

        /** @var array<int, object{term_id: int}> */
        public $terms = [];

        /** @param array<string, mixed> $args */
        public function __construct($args = [])
        {
            self::$constructed[] = $args;
            $this->terms = array_shift(self::$responses) ?? [];
        }

        /** Forgets every queued response and recorded argument. */
        public static function reset(): void
        {
            self::$responses = [];
            self::$constructed = [];
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
        /** @var array<int, mixed> */
        public $items = [];

        /** @var array<int, array<mixed>> */
        public $_column_headers = [];

        /** @param array<string, mixed> $_args */
        public function __construct(protected $_args = []) {}

        /** @param array<string, string> $actions */
        public function row_actions($actions): string
        {
            return '<div class="row-actions">'.implode(' | ', $actions).'</div>';
        }

        public function display(): void
        {
            echo 'table:'.count($this->items);
        }
    }
}
