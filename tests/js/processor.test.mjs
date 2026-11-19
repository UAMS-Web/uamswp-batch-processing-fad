// Tests for assets/processor.js, the batch screen's item runner. They need Node 18 or later and
// no packages: the runner is loaded into a sandbox with a small stand-in for jQuery that records
// every request and every change to the page, and each test answers the requests by hand.
//
// Run them with `node --test tests/js/*.test.mjs`. Name the files: on Node v24.22.3 a bare
// `tests/js/` argument is run as a module and fails with MODULE_NOT_FOUND. PROCESSOR_JS points
// the tests at another copy of the runner, so a reverted copy can be shown to fail them.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const runnerPath = process.env.PROCESSOR_JS ?? fileURLToPath(new URL('../../assets/processor.js', import.meta.url));
const runnerSource = readFileSync(runnerPath, 'utf8');

// Loads the runner into a fresh page and returns the page's state and its pending requests.
function loadRunner() {
    const elements = new Map();
    const handlers = [];
    const requests = [];

    const element = (selector) => {
        if (!elements.has(selector)) {
            elements.set(selector, { selector, text: '', html: '', props: {}, css: {}, shown: false, children: [] });
        }
        const state = elements.get(selector);
        const wrapper = {
            state,
            text(value) { state.text = value; return wrapper; },
            html(value) { state.html = value instanceof Object && value.state ? value.state.text : value; return wrapper; },
            prop(name, value) { state.props[name] = value; return wrapper; },
            css(name, value) { state.css[name] = value; return wrapper; },
            show() { state.shown = true; return wrapper; },
            append(value) { state.children.push(value instanceof Object && value.state ? value.state.text : value); return wrapper; },
        };
        return wrapper;
    };

    let fresh = 0;
    const document = {};
    const $ = (target) => {
        if (target === document) {
            return {
                on(event, selector, handler) { handlers.push({ event, selector, handler }); },
                trigger(event, args) {
                    handlers.filter((h) => h.event === event && typeof h.selector === 'function')
                        .forEach((h) => h.selector({}, ...args));
                },
            };
        }
        // A tag such as '<li></li>' makes a new, detached element.
        if (target.startsWith('<')) {
            fresh += 1;
            return element(`new:${fresh}:${target}`);
        }
        return element(target);
    };
    $.ajax = (options) => { requests.push(options); };

    const sandbox = {
        jQuery: $,
        document,
        DgBatchRunner: {
            ajax_url: 'https://example.test/wp-admin/admin-ajax.php',
            nonce: 'abc123',
            batch_id: 'demo',
            delay: 0,
            text: { processing: 'Processing...', start: 'Start' },
        },
        window: { location: { href: 'https://example.test/wp-admin/admin.php?page=dg-batches' } },
        console: { log() {} },
        setTimeout: (fn) => fn(),
        alert: (message) => { requests.alerts.push(message); },
    };
    requests.alerts = [];
    vm.runInNewContext(runnerSource, sandbox);

    const click = (selector) => {
        handlers.filter((h) => h.event === 'click' && h.selector === selector)
            .forEach((h) => h.handler({ preventDefault() {} }));
    };

    // Answers the oldest unanswered request, as jQuery would on a 200 response.
    let answered = 0;
    const answer = (response) => {
        const request = requests[answered];
        assert.ok(request, `no request is waiting for an answer (answered ${answered} of ${requests.length})`);
        answered += 1;
        if (request.beforeSend) request.beforeSend();
        request.success(response);
    };

    return { click, answer, requests, state: (selector) => element(selector).state };
}

const progress = (overrides) => ({
    success: true,
    data: { message: 'Processed item with id 1', is_finished: 0, total_processed: 1, total_items: 3, percentage: 33, ...overrides },
});

for (const message of ['Permission denied.', 'Invalid batch id']) {
    test(`a refused request (${message}) shows its message once and stops requesting`, () => {
        const page = loadRunner();
        page.click('#batch-process-start');
        assert.equal(page.requests.length, 1);

        page.answer({ success: false, data: { message } });

        assert.equal(page.requests.length, 1, 'the runner sent another request after the refusal');
        assert.equal(page.state('.batch-process-current-item').html, message);
        assert.deepEqual(page.state('#batch-errors-list').children, [message]);
        assert.equal(page.state('#batch-errors').shown, true);
        assert.equal(page.state('#batch-process-start').text, 'Start');
        assert.equal(page.state('#batch-process-start').props.disabled, false);
        assert.deepEqual(page.state('.batch-process-progress-bar-inner').css, {}, 'the progress bar moved on a refusal');
    });
}

test('a response with no JSON body stops requesting and says the request was refused', () => {
    const page = loadRunner();
    page.click('#batch-process-start');

    page.answer('0');

    assert.equal(page.requests.length, 1);
    assert.deepEqual(page.state('#batch-errors-list').children, ['The request was refused.']);
    assert.equal(page.state('#batch-process-start').props.disabled, false);
});

test('an item whose process() returned a WP_Error is listed and processing moves on', () => {
    const page = loadRunner();
    page.click('#batch-process-start');

    page.answer({ success: false, data: { message: 'Error processing item with id 1: broken', is_finished: 0, total_processed: 1, total_items: 3, percentage: 33 } });

    assert.equal(page.requests.length, 2, 'the runner did not ask for the next item after an item error');
    assert.deepEqual(page.state('#batch-errors-list').children, ['<li>Error processing item with id 1: broken</li>']);
    assert.equal(page.state('#batch-process-processed').text, 1);
});

test('a processed item asks for the next one and moves the progress bar', () => {
    const page = loadRunner();
    page.click('#batch-process-start');

    page.answer(progress());

    assert.equal(page.requests.length, 2);
    assert.equal(page.state('.batch-process-progress-bar-inner').css.width, '33%');
    assert.deepEqual(page.state('#batch-errors-list').children, []);
});

test('a finished batch stops requesting and disables Start and Stop', () => {
    const page = loadRunner();
    page.click('#batch-process-start');

    page.answer(progress({ message: 'Processing finished.', is_finished: 1, total_processed: 3, percentage: 100 }));

    assert.equal(page.requests.length, 1);
    assert.equal(page.state('#batch-process-start').text, 'Start');
    assert.equal(page.state('#batch-process-start, #batch-process-stop').props.disabled, true);
});
