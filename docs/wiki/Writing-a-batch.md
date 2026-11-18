# Writing a batch

Written against v1.1.0.

A batch is a class that extends `WP_Batch`. It fills a queue of `WP_Batch_Item` objects in `setup()`, handles one item at a time in `process()`, and is registered with `WP_Batch_Processor` so it appears on the Batches screen. The batches under `fad/` in the repository are working examples.

## Steps

1. **Extend `WP_Batch`** and give the batch an `$id` and a `$title`. The id must be unique across every registered batch: it identifies the batch in the screen's URL and in the option that records progress.
2. **Fill the queue in `setup()`** by calling `$this->push()` with one `WP_Batch_Item` per unit of work. An item takes an id, unique within the batch, and optional data.
3. **Process one item in `process()`.** Read the item's data with `get_value()` or `get_int_value()`, do the work, and return `true` on success or a `WP_Error` on failure. The error's message is shown in the batch's **List of errors**.
4. **Optionally override `finish()`**, which runs once when a request finds no item left to process.
5. **Register an instance** on the `wp_batch_processing_init` action.

```php
if ( class_exists( 'WP_Batch' ) ) {

	class MY_Example_Batch extends WP_Batch {

		/** Unique identifier of the batch. */
		public $id = 'email_post_authors';

		/** Shown on the Batches screen. */
		public $title = 'Email Post Authors';

		public function setup() {
			$users = get_users( array(
				'number' => '40',
				'role'   => 'author',
			) );

			foreach ( $users as $user ) {
				$this->push( new WP_Batch_Item( $user->ID, array( 'author_id' => $user->ID ) ) );
			}
		}

		/**
		 * @param WP_Batch_Item $item
		 * @return bool|\WP_Error
		 */
		public function process( $item ) {
			$author_id = $item->get_int_value( 'author_id' );

			if ( $author_id === 0 ) {
				return new WP_Error( 'missing_author', 'The item has no author id' );
			}

			// Do the expensive work for one item here.

			return true;
		}

		public function finish() {
			// Runs once, after the last item.
		}
	}
}

function my_example_batch_init() {
	WP_Batch_Processor::get_instance()->register( new MY_Example_Batch() );
}
add_action( 'wp_batch_processing_init', 'my_example_batch_init', 15, 1 );
```

**Note:** `setup()` runs every time the batch is constructed, and the batch is constructed on every load of the Batches screen and on every item request. A `setup()` that runs a slow query makes every item slow. Cache an expensive list, for example in a transient, and keep `setup()` returning the same items in the same order, because progress is recorded by item id.

**Note:** `process()` is treated as a success unless it returns a `WP_Error`. Return `WP_Error` for every failure you want listed.

**Warning:** `wp_batch_processing_init` fires on `init` at priority 0, and only on the Batches screen and its AJAX requests. Add your `add_action()` call when your plugin loads, not inside an `init` callback, or it is added after the action has fired.

## Reference

- [Hooks and stored data](Hooks-and-stored-data.md): `wp_batch_processing_init`, the delay and message filters, and the option that records progress.
- [Find-a-Doc batches](Find-a-Doc-batches.md): the batches this plugin ships, built the same way.
- [Running a batch](Running-a-batch.md): starting, stopping and restarting the batch once it is registered.
