# UAMSWP Batch Processing

UAMSWP Batch Processing is a WordPress admin plugin that runs a list of work items one at a time from the browser, with a progress bar, and resumes where it stopped if the page is closed or the connection drops. It is a modified version of [WP Batch Processing](https://github.com/gdarko/wp-batch-processing), and it ships the batches the Find-a-Doc site uses to build and maintain its condition, treatment, provider, location and area-of-expertise content.

![Example](examples/processing.gif)

Batches suit any long job that can be split into items: importing or downloading data, sending email, or changing many posts.

## Documentation

The [wiki](https://github.com/UAMS-Web/uamswp-batch-processing-fad/wiki) covers running a batch, writing one, the reference for every shipped batch and for the plugin's hooks, and troubleshooting. Its pages are kept in [`docs/wiki/`](docs/wiki/) and published by the `Publish wiki` workflow.

## Installation

Requirements: WordPress 6.8 or later and PHP 8.2 or later. The Find-a-Doc batches also need Advanced Custom Fields and the Find-a-Doc post types and taxonomies.

1. Install the plugin in `wp-content/plugins/` from a [release](https://github.com/UAMS-Web/uamswp-batch-processing-fad/releases) of this repository, and activate it.
2. Open **Batches** in the admin menu, as a user with the `manage_options` capability.

The plugin loads only in the admin. To define a batch of your own, see [Writing a batch](https://github.com/UAMS-Web/uamswp-batch-processing-fad/wiki/Writing-a-batch) in the wiki.

## Contribute

If you notice a bug or you want to propose improvements feel free to create a pull request!


## Credits

Based on WP Batch Processing by Darko Gjorgjijoski.

Modified by UAMS Web Services.

Developers:

- Todd McKee
- Josh Daugherty

## Tests

The suite uses Pest 5 with Brain Monkey, so it needs no database and no WordPress checkout. It develops and runs on PHP 8.4.27 (`config.platform.php` in `composer.json`).

```bash
composer install
composer test
```

`composer test` runs `vendor/bin/pest` over `tests/Unit`. The shared local CI runner runs the same suite with the rest of the checks:

```bash
node .claude/skills/local-ci/local-ci.mjs
```

Name every test file `*Test.php`, or the suite does not pick it up.

### Mutation testing

`composer mutate` mutates `includes/`, `fad/` and the plugin file against the Unit tests that cover them, with PCOV or Xdebug loaded (see the `pcov-setup` skill). The script names its test files because the Unit bootstrap loads Patchwork, and a run that names no test file scores kills it did not earn. Pass `-- --min=100` to fail on any survivor, and `-- --path=<file>` to mutate one file. The shared remote chain runs the same script on changed files for a pull request and on everything for a manual dispatch, behind the `RUN_REMOTE_CI` switch below.

### Code quality

Rector, PHPStan (with the WordPress extension and stubs) and Pint from the shared code-quality standard are development dependencies only, and each is a runner job and a Composer script:

| Tool | Check | Fix |
| --- | --- | --- |
| Rector | `composer test:refactor` | `composer refactor` |
| PHPStan | `composer stan` | raise `level` in `phpstan.neon.dist` one level per change |
| Pint | `composer test:pint` | `composer pint` |

PHPStan starts at level 0, the highest level the code passes today, and rises one level per change. The two Advanced Custom Fields functions the batches call are declared for it in `phpstan-stubs.php`.

`.git-blame-ignore-revs` lists the commit that reformatted the whole tree with Pint, so `git blame` skips it. Git reads the file only when told to, once per clone:

```bash
git config blame.ignoreRevsFile .git-blame-ignore-revs
```

### Reading the runner's verdict

The runner selects jobs from the branch's diff against `origin/master`, runs each on the local PHP toolchain, and ends with a summary block that names the validated commit and lists every job as `PASS`, `FAIL` or `SKIPPED`. A failing job fails the run: the exit status is non-zero exactly when the summary says a job failed, and the summary is what to read. Write the run to a file and read the block at the end:

```bash
node .claude/skills/local-ci/local-ci.mjs > ci.log 2>&1
```

`--jobs a,b` runs named jobs only and `--all` runs every job; the two exclude each other. The jobs live in `local-ci.json`. A pull request carries the runner's verdict as a comment naming the commit it validated.

### Remote CI

`.github/workflows/ci.yml` calls the shared chain in `UAMS-Web/uams-ci-workflows`, pinned to a release tag, and ends in the one job named `CI`. The repository is private, so the call and `CI` are gated on the Actions variable `RUN_REMOTE_CI`: set to `false`, they report `skipped` and use no minutes, and the local runner above is the whole gate. Unset, or any other value, runs the chain. A `skipped` job proves nothing. To take a newer chain, move the pin in `ci.yml` and pass only inputs the new tag declares.

### Pre-commit guard

`.githooks/pre-commit` refuses a commit that stages a file matching `guard.deny` in `local-ci.json` (a `wp-config.php`, a database dump, a spreadsheet export, an `.env`, a key). Git does not run a hook from the repository on its own, so each clone turns it on once:

```bash
git config core.hooksPath .githooks
```

The runner warns when the hook is tracked and `core.hooksPath` is unset.


## License

The plugin is licensed under GPL v2

### Modified version

This is a modified version of [WP Batch Processing](https://github.com/gdarko/wp-batch-processing) by Darko Gjorgjijoski. UAMS Web Services changed the plugin between 2020-07-21 (the first commit in this repository) and 2026-11-13 (the date of this notice), adding the Find-a-Doc batches under `fad/` and changing files under `includes/`, `views/` and `assets/`. The commit history of this repository records each change. The whole work stays under the GNU General Public License, version 2 or any later version; the full text is in [`LICENSE`](LICENSE).

Copyright (C) 2020-2026 UAMS Web Services (modifications)

The upstream copyright and license notice follows unchanged.

```
Copyright (C) 2019 Darko Gjorgjijoski (https://darkog.com)

This file is part of WP Batch Processing

WP Batch Processing is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

WP Batch Processing is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WP Batch Processing. If not, see <https://www.gnu.org/licenses/>.
```