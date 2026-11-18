# Troubleshooting

Written against v1.1.0.

- [There is no Batches menu](#there-is-no-batches-menu)
- [A batch screen says `Batch not found.`](#a-batch-screen-says-batch-not-found)
- [A batch I wrote is not on the Batches screen](#a-batch-i-wrote-is-not-on-the-batches-screen)
- [An `HTTP Error.` alert stops processing](#an-http-error-alert-stops-processing)
- [`Permission denied.` or `Invalid batch id` repeats without stopping](#permission-denied-or-invalid-batch-id-repeats-without-stopping)
- [Every item takes a long time](#every-item-takes-a-long-time)
- [A batch shows 100% but some items were not processed](#a-batch-shows-100-but-some-items-were-not-processed)
- [The NRC cache batch finished but a provider has no cached data](#the-nrc-cache-batch-finished-but-a-provider-has-no-cached-data)
- [Fixed](#fixed)
  - [Running a Make Posts from Taxonomy batch again created duplicate posts](#running-a-make-posts-from-taxonomy-batch-again-created-duplicate-posts)
  - [The log says `get_page_by_title` is deprecated since version 6.2.0](#the-log-says-get_page_by_title-is-deprecated-since-version-620)

## There is no Batches menu

**Cause:** the menu is added for users with the `manage_options` capability only, and only while the plugin is active.

**Fix:** activate the plugin, and use an account with `manage_options` (an Administrator on a standard site).

## A batch screen says `Batch not found.`

**Cause:** the `id` in the screen's URL matches no registered batch. The batch's file is not loaded, its registration did not run, or the id in the URL is mistyped. The three region batches under `fad/` are not loaded by the plugin and always give this message (see [Find-a-Doc batches](Find-a-Doc-batches.md#batches-in-fad-that-are-not-loaded)).

**Fix:** open the batch from the Batches screen rather than from a saved link, and check that the batch is registered as [Writing a batch](Writing-a-batch.md) describes.

## A batch I wrote is not on the Batches screen

**Cause:** batches are registered on `wp_batch_processing_init`, which fires on `init` at priority 0. An `add_action( 'wp_batch_processing_init', ... )` made inside an `init` callback, or from code that loads after `init`, is added after the action has fired, so the batch is never registered. A class wrapped in `if ( class_exists( 'WP_Batch' ) )` is also skipped when it loads before this plugin. If the registration sits outside that block, the callback is still hooked and the Batches screen instead fails with a fatal `Class "..." not found` error.

**Fix:** add the action when your plugin's main file loads, and define the class where `WP_Batch` already exists, for example in a file required from a `plugins_loaded` callback.

## An `HTTP Error.` alert stops processing

**Cause:** an item request failed outright rather than returning a result. Typical reasons are a PHP fatal error inside the batch's `process()` or `setup()`, a server time limit, or a request refused with `400` because the session ended, or with `403` because the user lost `manage_options`.

**Fix:** check the PHP error log for the request's time, fix the cause, reload the batch screen and choose **Start**. Processing resumes with the next unprocessed item. An item that failed before it was recorded as processed runs again.

## `Permission denied.` or `Invalid batch id` repeats without stopping

**Cause:** the batch screen's security nonce has expired (the page was left open for many hours), or the batch stopped being registered while its screen was open. The request is refused with a message but no progress data, and the runner reads that as "not finished" and asks again at once, so the red message and **List of errors** grow without end, the Total and Processed counts do not move, and the percentage reads `(undefined%)`.

**Fix:** choose **Stop**, or reload the page, which issues a fresh nonce. Then choose **Start**. Nothing was recorded as processed while the message repeated.

## Every item takes a long time

**Cause:** every registered batch is constructed, and its `setup()` run, on every Batches screen load and every item request, whichever batch is running. The 12 shipped batches each query every term or post of a type in `setup()`, so a large site pays for all 12 queries on every item, including items of your own batch.

**Fix:** cache the list your own batches' `setup()` builds; that removes their share but not the shipped batches'. Expect the cost of the shipped batches, and use `wp_batch_processing_delay` only when the server needs a pause between items ([Hooks and stored data](Hooks-and-stored-data.md#filters)).

## A batch shows 100% but some items were not processed

**Cause:** an item is recorded as processed whether `process()` succeeded or failed, and the shipped batches return `true` even for an item they skip (a deleted term, an empty field). Items added to the site after the batch was last run are picked up, but items already recorded are not run again.

**Fix:** choose **Restart**, then **Start**, to run every item again.

## The NRC cache batch finished but a provider has no cached data

**Cause:** *Update Cache Providers NRC Data* skips a provider with no `physician_npi`, skips a provider whose transient is still stored, and stores nothing when the request could not be made at all. An error response from the server (a `404` or `500`) is stored for 30 days like any other, so a provider can also have cached data that is an error.

**Fix:** check that the provider has an NPI. To refetch a provider whose cached response is an error, delete its `nrc_backup_<npi>` transient, then restart the batch and run it again.

## Fixed

Problems that a later change fixed, kept for sites still running the release named in each.

### Running a Make Posts from Taxonomy batch again created duplicate posts

Fixed after v1.1.0 ([#90](https://github.com/UAMS-Web/uamswp-batch-processing-fad/issues/90)): the batches now skip a term when a post of their own type has the term's name, and a page no longer counts. A generated post that has since been retitled is not found, so keep the generated titles, or expect a second post for that term.

**Cause:** in v1.1.0, *Make Condition Posts from Taxonomy* and *Make Treatment Posts from Taxonomy* skip a term only when a **page** already has its name as its title. An existing `condition` or `treatment` post does not stop them, so a second run creates a second post per term, with an ordinary ID because the offset ID is taken.

**Fix:** in v1.1.0, run each of these batches once per site. To remove duplicates made by an earlier version, take each term in turn, find the posts of that post type whose title is the term's name, keep the one whose ID is the term ID plus the offset (`100000` for conditions, `200000` for treatments) and delete the others. Do not delete a post only because its ID does not match the offset: a post made by hand, or on a first run where the offset ID was taken, has an ordinary ID too.

### The log says `get_page_by_title` is deprecated since version 6.2.0

Fixed after v1.1.0 ([#90](https://github.com/UAMS-Web/uamswp-batch-processing-fad/issues/90)): the batches no longer call `get_page_by_title()`.

The notice reads `Function get_page_by_title is deprecated since version 6.2.0! Use WP_Query instead.`, with `deprecated` in bold where it is shown as HTML.

**Cause:** in v1.1.0, the two *Make ... Posts from Taxonomy* batches call `get_page_by_title()`, which WordPress deprecated in 6.2. With `WP_DEBUG` on, each item logs the notice.

**Fix:** in v1.1.0, none needed for the batch to run; the notice does not stop it.
