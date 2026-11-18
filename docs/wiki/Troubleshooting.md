# Troubleshooting

Written against v1.1.0.

- [There is no Batches menu](#there-is-no-batches-menu)
- [A batch screen says `Batch not found.`](#a-batch-screen-says-batch-not-found)
- [A batch I wrote is not on the Batches screen](#a-batch-i-wrote-is-not-on-the-batches-screen)
- [An `HTTP Error.` alert stops processing](#an-http-error-alert-stops-processing)
- [`Permission denied.` or `Invalid batch id` repeats without stopping](#permission-denied-or-invalid-batch-id-repeats-without-stopping)
- [Every item takes a long time](#every-item-takes-a-long-time)
- [A batch shows 100% but some items were not processed](#a-batch-shows-100-but-some-items-were-not-processed)
- [Running a Make Posts from Taxonomy batch again created duplicate posts](#running-a-make-posts-from-taxonomy-batch-again-created-duplicate-posts)
- [The log shows `get_page_by_title is deprecated`](#the-log-shows-get_page_by_title-is-deprecated)
- [The NRC cache batch finished but a provider has no cached data](#the-nrc-cache-batch-finished-but-a-provider-has-no-cached-data)

## There is no Batches menu

**Cause:** the menu is added for users with the `manage_options` capability only, and only while the plugin is active.

**Fix:** activate the plugin, and use an account with `manage_options` (an Administrator on a standard site).

## A batch screen says `Batch not found.`

**Cause:** the `id` in the screen's URL matches no registered batch. The batch's file is not loaded, its registration did not run, or the id in the URL is mistyped. The three region batches under `fad/` are not loaded by the plugin and always give this message (see [Find-a-Doc batches](Find-a-Doc-batches.md#batches-in-fad-that-are-not-loaded)).

**Fix:** open the batch from the Batches screen rather than from a saved link, and check that the batch is registered as [Writing a batch](Writing-a-batch.md) describes.

## A batch I wrote is not on the Batches screen

**Cause:** batches are registered on `wp_batch_processing_init`, which fires on `init` at priority 0. An `add_action( 'wp_batch_processing_init', ... )` made inside an `init` callback, or from code that loads after `init`, is added after the action has fired, so the batch is never registered. A class wrapped in `if ( class_exists( 'WP_Batch' ) )` is also skipped when it loads before this plugin.

**Fix:** add the action when your plugin's main file loads, and define the class where `WP_Batch` already exists, for example in a file required from a `plugins_loaded` callback.

## An `HTTP Error.` alert stops processing

**Cause:** an item request failed outright rather than returning a result. Typical reasons are a PHP fatal error inside the batch's `process()` or `setup()`, a server time limit, or a request refused with `403` because the session ended or the user lost `manage_options`.

**Fix:** check the PHP error log for the request's time, fix the cause, reload the batch screen and choose **Start**. Processing resumes with the next unprocessed item. An item that failed before it was recorded as processed runs again.

## `Permission denied.` or `Invalid batch id` repeats without stopping

**Cause:** the batch screen's security nonce has expired (the page was left open for many hours), or the batch stopped being registered while its screen was open. The request is refused with a message but no progress data, and the runner reads that as "not finished" and asks again at once, so the red message and **List of errors** grow without end and the counts do not move.

**Fix:** choose **Stop**, or reload the page, which issues a fresh nonce. Then choose **Start**. Nothing was recorded as processed while the message repeated.

## Every item takes a long time

**Cause:** the batch's `setup()` runs on every item request, because the batch is constructed again for each one. The shipped batches query every post of a post type in `setup()`, so a large site pays for that query on every item.

**Fix:** for your own batches, cache the list `setup()` builds. For a shipped batch, expect the cost and use `wp_batch_processing_delay` only when the server needs a pause between items ([Hooks and stored data](Hooks-and-stored-data.md#filters)).

## A batch shows 100% but some items were not processed

**Cause:** an item is recorded as processed whether `process()` succeeded or failed, and the shipped batches return `true` even for an item they skip (a deleted term, an empty field). Items added to the site after the batch was last run are picked up, but items already recorded are not run again.

**Fix:** choose **Restart**, then **Start**, to run every item again.

## Running a Make Posts from Taxonomy batch again created duplicate posts

**Cause:** *Make Condition Posts from Taxonomy* and *Make Treatment Posts from Taxonomy* skip a term only when a **page** already has its name as its title. An existing `condition` or `treatment` post does not stop them, so a second run creates a second post per term, with an ordinary ID because the offset ID is taken.

**Fix:** run each of these batches once per site. To remove the duplicates, delete the posts whose IDs are not the term ID plus the offset (`100000` for conditions, `200000` for treatments).

## The log shows `get_page_by_title is deprecated`

**Cause:** the two *Make ... Posts from Taxonomy* batches call `get_page_by_title()`, which WordPress deprecated in 6.2. With `WP_DEBUG` on, each item logs the notice.

**Fix:** none needed for the batch to run; the notice does not stop it.

## The NRC cache batch finished but a provider has no cached data

**Cause:** *Update Cache Providers NRC Data* stores the response only when the request succeeds, skips a provider with no `physician_npi`, and skips a provider whose transient is still stored.

**Fix:** check that the provider has an NPI, then restart the batch and run it again. A failed request is retried on the next run.
