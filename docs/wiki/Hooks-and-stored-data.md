# Hooks and stored data

Written against v1.1.0.

The plugin loads only in the WordPress admin: on the front end its main file returns before loading anything. The action and filters below are the ways to extend it, and the options are where it records progress.

## Action

| Hook | Fires | Use |
| --- | --- | --- |
| `wp_batch_processing_init` | On `init` at priority 0, only on the Batches screen (`admin.php?page=dg-batches`) and on the plugin's two AJAX requests | Register batches with `WP_Batch_Processor::get_instance()->register( $batch )`. See [Writing a batch](Writing-a-batch.md). |

## Filters

| Filter | Arguments | Default | Effect |
| --- | --- | --- | --- |
| `wp_batch_processing_delay` | `$delay` (int, seconds) | `0` | Seconds the browser waits after one item before requesting the next. |
| `dg_batch_item_success_message` | `$message` (string), `$item` (`WP_Batch_Item`) | `Processed item with id <id>` | The message shown for an item that succeeded. |
| `dg_batch_item_error_message` | `$message` (string), `$item` (`WP_Batch_Item`) | `Error processing item with id <id>: <error message>` | The message shown, and added to **List of errors**, for an item whose `process()` returned a `WP_Error`. |

**Note:** `dg_batch_item_error_message` is also applied to the closing `Processing finished.` message, with no second argument. A callback that takes two arguments needs a default for `$item`.

Example: wait two seconds between items.

```php
add_filter( 'wp_batch_processing_delay', function () {
	return 2;
} );
```

## AJAX actions

The runner on the batch screen calls two `admin-ajax.php` actions. Both require the `wp-batch-processing` nonce and the `manage_options` capability, and both take a `batch_id`.

| Action | Effect | Response data |
| --- | --- | --- |
| `dg_process_next_batch_item` | Processes the batch's next unprocessed item and records it as processed, or, when none is left, calls the batch's `finish()` | `message`, `is_finished` (`0` or `1`), `total_processed`, `total_items`, `percentage` |
| `dg_restart_batch` | Deletes the batch's record of processed items | none |

A failed nonce check, a missing capability, or an unknown or missing `batch_id` answers with `success: false` and a `message`.

## Capability

`manage_options` opens the Batches screen, loads its runner script, and is checked again on each AJAX request.

## Stored data

| Option | Holds | Cleared by |
| --- | --- | --- |
| `batch_<batch id>_processed` | The list of item ids already processed | **Restart**, or deleting the option |

Nothing else is stored by the plugin itself. The batches store what they write: see [Find-a-Doc batches](Find-a-Doc-batches.md).

**Note:** when the option holds something other than a list, the batch treats every item as unprocessed and starts over, and PHP logs a warning naming the batch.
