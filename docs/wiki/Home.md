# UAMSWP Batch Processing

<!-- cspell:words UAMSWP -->

Written against v1.1.0.

UAMSWP Batch Processing is a WordPress admin plugin that runs a list of work items one at a time from the browser, with a progress bar, and resumes where it stopped. It is a modified version of [WP Batch Processing](https://github.com/gdarko/wp-batch-processing), and it ships the batches the Find-a-Doc site uses to build and maintain its condition, treatment, provider, location and area-of-expertise content.

This wiki covers running batches, writing a new one, and the reference for the batches and hooks. The [README](https://github.com/UAMS-Web/uamswp-batch-processing-fad#readme) keeps the overview, installation and development notes.

**Getting started**

- [Installation (README)](https://github.com/UAMS-Web/uamswp-batch-processing-fad#installation): requirements and how to install the plugin.
- [Running a batch](Running-a-batch.md): open the Batches screen, start a batch, stop it, resume it and restart it.

**Guides**

- [Writing a batch](Writing-a-batch.md): define a batch class, fill its queue, process each item and register it.

**Reference**

- [Find-a-Doc batches](Find-a-Doc-batches.md): every batch this plugin ships, what it reads and what it writes.
- [Hooks and stored data](Hooks-and-stored-data.md): the action and filters, the AJAX actions, the capability and the options a batch stores.

**Troubleshooting**

- [Troubleshooting](Troubleshooting.md): what to do when a batch is missing, stops, or reports errors.

**Project**

- [Report a problem](https://github.com/UAMS-Web/uamswp-batch-processing-fad/issues)
- [Releases](https://github.com/UAMS-Web/uamswp-batch-processing-fad/releases)
