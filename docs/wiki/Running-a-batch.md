# Running a batch

Written against v1.1.0.

At the end of this page you will have run a batch from start to finish in the WordPress admin, stopped it part way, and resumed it.

**Note:** the Batches screen and every batch action need the `manage_options` capability, which on a standard site means an Administrator.

1. **Open the Batches screen.** In the admin menu, choose **Batches**. The screen lists every registered batch with its **Title**, **Total Processed** and **Total Items**.
2. **Open a batch.** Choose a batch's title, or **Manage** under it. The batch screen shows **Total**, **Processed** with a percentage, and a progress bar.
3. **Start it.** Choose **Start**. The button changes to *Processing...* and the plugin processes one item per request, updating the counts and the bar after each. The line under the bar shows the last item's message: green when it succeeded, red when it failed.
4. **Read any errors.** A failed item is added to **List of errors** below the batch, and processing moves on to the next item.
5. **Stop it.** Choose **Stop**. The page reloads. Items already processed stay processed.
6. **Resume it.** Choose **Start** again. Processing continues with the first item not yet processed. Closing the tab, losing the connection or logging out has the same effect as **Stop**: the next **Start** resumes.
7. **Let it finish.** When every item is processed, the counts read 100% and the buttons are disabled. Reload the page and the batch shows **Restart** in place of **Start** and **Stop**.
8. **Restart it.** Choose **Restart** to clear the record of processed items. The page reloads with the batch at 0%, ready to run from the first item. If the restart is refused or fails, its message shows in red and in **List of errors**, and the page does not reload. After `Permission denied.`, the security nonce has expired: reload the page and choose **Restart** again.

**Warning:** a failed item is still recorded as processed, so it is not retried on resume. To run it again, restart the batch; every item then runs again, including the ones that succeeded. **List of errors** is not saved, so copy it before you stop or reload the page.

## Going further

- [Writing a batch](Writing-a-batch.md) to add a batch of your own.
- [Find-a-Doc batches](Find-a-Doc-batches.md) for what each shipped batch changes before you run it.
- [Hooks and stored data](Hooks-and-stored-data.md) to add a pause between items.
- [Troubleshooting](Troubleshooting.md) when a batch is missing or stops.
