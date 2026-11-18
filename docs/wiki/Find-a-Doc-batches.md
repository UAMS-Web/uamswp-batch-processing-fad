# Find-a-Doc batches

<!-- cspell:words aoes uamswp -->

Written against v1.1.0.

The plugin ships the batches below, one class per file under `fad/`. Each reads the posts of one post type, or the terms of one taxonomy, and processes one of them per item. The fields they read and write are Advanced Custom Fields (ACF) fields on the Find-a-Doc content types, so ACF has to be active on the site.

**Warning:** every batch writes to the site's content and none can be undone from the Batches screen. Take a backup before running one on a site that matters.

**Note:** each shipped `process()` is declared to return `bool` and returns `true` for every item, including an item it skips, so these batches never add to **List of errors**. Watch the counts, not the error list.

## The ID offsets

The taxonomy-to-post batches create each condition post with the ID of its `condition` term plus `100000`, and each treatment post with the ID of its `treatment` term plus `200000`. The batches that convert term references to post references add the same offsets, so a reference to condition term `42` becomes a reference to post `100042`. Run the two *Make ... Posts from Taxonomy* batches before the batches that convert references.

## Batches registered on the Batches screen

| Title | Batch id | Reads | Writes |
| --- | --- | --- | --- |
| Make Condition Posts from Taxonomy | `conditions_posts_from_taxonomy` | every `condition` term, empty ones included | a published `condition` post per term: title, slug, `condition_content` as the body, `condition_short_desc` as the excerpt, the first value of each of the term's meta keys, and `condition_treatments` converted to treatment post IDs |
| Make Treatment Posts from Taxonomy | `treatments_posts_from_taxonomy` | every `treatment` term, empty ones included | a published `treatment` post per term: title, slug, `treatment_procedure_content` as the body, `treatment_procedure_short_desc` as the excerpt, the first value of each of the term's meta keys, and `treatment_procedure_conditions` converted to condition post IDs |
| Update Providers Post from Taxonomy | `providers_update_post_taxonomy` | `physician_conditions` and `physician_treatments` on every `provider` post | `physician_conditions_cpt` and `physician_treatments_cpt` as post IDs |
| Update Locations Post from Taxonomy | `location_update_post_taxonomy` | `location_conditions` and `location_treatments` on every `location` post | `location_conditions_cpt` and `location_treatments_cpt` as post IDs |
| Update AoEs Post from Taxonomy | `aoes_update_post_taxonomy` | `expertise_conditions` and `expertise_treatments` on every `expertise` post | `expertise_conditions_cpt` and `expertise_treatments_cpt` as post IDs |
| Update Conditions content from ACF | `conditions_update_content_batch` | `condition_content` on every `condition` post | the post body, when the field holds text |
| Update Treatments content from ACF | `treatments_update_content_batch` | `treatment_procedure_content` on every `treatment` post | the post body, when the field holds text |
| Update Conditions Sync to Treatments | `conditions_update_sync_batch` | `condition_treatments` on every `condition` post | adds the condition to `treatment_conditions` on each treatment it names, when it is not already there |
| Update Treatments Sync to Conditions | `treatments_update_sync_batch` | `treatment_conditions` on every `treatment` post | adds the treatment to `condition_treatments` on each condition it names, when it is not already there |
| Update Providers Regions | `providers_update_regions` | the first location in `physician_locations` on every `provider` post, and that location's `location_region` | `physician_region` |
| Update Providers Filter | `providers_update_filter` | the titles of the posts in `physician_expertise`, `physician_conditions_cpt` and `physician_treatments_cpt` on every `provider` post (run *Update Providers Post from Taxonomy* first) | `physician_asp_filter`: the three lists joined with `, `, which keeps the separator when a list is empty |
| Update Cache Providers NRC Data | `providers_cache_nrc_data` | `physician_npi` on every `provider` post | a 30-day transient `nrc_backup_<npi>` holding the provider's NRC Health ratings response, fetched only when no transient is stored. A request that could not be made stores nothing, but an error response from the server (a `404` or `500`) is stored for 30 days like any other |

The two taxonomy batches read terms ordered by name. Every other batch reads posts in every status except trash and auto-draft, ordered by title.

**Note:** every registered batch runs its `setup()` query on every Batches screen load and every item request, whichever batch is running. See [Troubleshooting](Troubleshooting.md#every-item-takes-a-long-time).

Since the change after v1.1.0 ([#90](https://github.com/UAMS-Web/uamswp-batch-processing-fad/issues/90)), the two *Make ... Posts from Taxonomy* batches skip a term that already has its post: a post of the batch's own type at the term's offset ID, or one titled with the term's name. A page with that name does not count. In v1.1.0 they skipped a term only when a **page** had its name, so running either batch a second time made a second post per term.

## Batches in `fad/` that are not loaded

Three files define batches that the plugin file does not load, so they do not appear on the Batches screen:

| Title | Batch id | File | Would write |
| --- | --- | --- | --- |
| Update Condition Regions | `condition_update_regions` | `fad/class-condition-region-batch.php` | `condition_region`, the distinct regions of the post's `condition_locations` |
| Update Treatment Regions | `treatment_update_regions` | `fad/class-treatment-region-batch.php` | `treatment_procedure_region`, the distinct regions of the post's `treatment_procedure_locations` |
| Update Expertise Regions | `expertise_update_regions` | `fad/class-expertise-region-batch.php` | `expertise_region`, the distinct regions of the post's `location_expertise` |

Making one available means adding a `require_once` for its file to `uamswp-batch-processing.php` through a pull request.

## See also

- [Running a batch](Running-a-batch.md)
- [Writing a batch](Writing-a-batch.md)
- [Hooks and stored data](Hooks-and-stored-data.md)
