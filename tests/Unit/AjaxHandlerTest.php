<?php

/**
 * WP_Batch_Processing_Ajax_Handler: the two admin-ajax actions that run and restart a batch.
 *
 * wp_send_json_success() and wp_send_json_error() are replaced with functions that throw
 * JsonSent, which stops the handler where the real ones stop the request, so the `exit`
 * statements after them are never reached and the test reads what was sent.
 *
 * @see includes/class-batch-ajax-handler.php
 */

declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

/**
 * Runs a handler method and returns what it sent.
 */
function handler_sends(string $method): JsonSent
{
    try {
        WP_Batch_Processing_Ajax_Handler::get_instance()->{$method}();
    } catch (JsonSent $sent) {
        return $sent;
    }

    throw new LogicException("{$method}() sent nothing.");
}

beforeEach(function (): void {
    $this->store = [];
    stub_options($this->store);

    forget_singleton(WP_Batch_Processing_Ajax_Handler::class);
    forget_singleton(WP_Batch_Processor::class);

    Functions\when('wp_send_json_error')->alias(function ($data = null, $status = null): never {
        throw new JsonSent(false, $data, $status);
    });
    Functions\when('wp_send_json_success')->alias(function ($data = null, $status = null): never {
        throw new JsonSent(true, $data, $status);
    });
    Functions\when('is_wp_error')->alias(fn ($value): bool => $value instanceof WP_Error);

    // A user who passes both checks unless a test sets one of these to false. Each call is recorded.
    $this->nonceValid = true;
    $this->userCan = true;
    $this->calls = [];
    Functions\when('check_ajax_referer')->alias(function (...$args) {
        $this->calls['check_ajax_referer'][] = $args;

        return $this->nonceValid ? 1 : false;
    });
    Functions\when('current_user_can')->alias(function (...$args) {
        $this->calls['current_user_can'][] = $args;

        return $this->userCan;
    });

    $_REQUEST = [];
});

afterEach(function (): void {
    $_REQUEST = [];
    forget_singleton(WP_Batch_Processing_Ajax_Handler::class);
    forget_singleton(WP_Batch_Processor::class);
});

it('registers both actions on the admin-ajax hooks when it is built', function (): void {
    Actions\expectAdded('wp_ajax_dg_process_next_batch_item')->once()->with(Mockery::on(
        fn ($callback): bool => is_array($callback) && $callback[0] instanceof WP_Batch_Processing_Ajax_Handler && $callback[1] === 'process_next_item'
    ));
    Actions\expectAdded('wp_ajax_dg_restart_batch')->once()->with(Mockery::on(
        fn ($callback): bool => is_array($callback) && $callback[0] instanceof WP_Batch_Processing_Ajax_Handler && $callback[1] === 'restart_batch'
    ));

    WP_Batch_Processing_Ajax_Handler::get_instance();
});

describe('process_next_item', function (): void {
    it('refuses a request whose nonce does not check out', function (): void {
        $this->nonceValid = false;

        $sent = handler_sends('process_next_item');

        expect($sent->success)->toBeFalse()
            ->and($sent->data)->toBe(['message' => 'Permission denied.'])
            ->and($sent->status)->toBeNull()
            ->and($this->calls['check_ajax_referer'])->toBe([['wp-batch-processing', 'nonce', false]])
            ->and($this->calls)->not->toHaveKey('current_user_can');
    });

    it('refuses with 403 a user who lacks the capability, even with a valid nonce', function (): void {
        $_REQUEST['batch_id'] = 'fake';
        $this->userCan = false;

        $sent = handler_sends('process_next_item');

        expect($sent->success)->toBeFalse()
            ->and($sent->data)->toBe(['message' => 'Permission denied.'])
            ->and($sent->status)->toBe(403)
            ->and($this->calls['current_user_can'])->toBe([['manage_options']]);
    });

    it('refuses a request with no batch id', function (): void {
        $sent = handler_sends('process_next_item');

        expect($sent->success)->toBeFalse()
            ->and($sent->data)->toBe(['message' => 'Invalid batch id'])
            ->and($sent->status)->toBeNull();
    });

    it('refuses a batch id that is empty or zero', function (string $id): void {
        $_REQUEST['batch_id'] = $id;

        expect(handler_sends('process_next_item')->data)->toBe(['message' => 'Invalid batch id']);
    })->with(['empty string' => [''], 'zero' => ['0']]);

    it('processes the next unprocessed item and reports progress', function (): void {
        $batch = new FakeBatch('fake', [1, 2]);
        WP_Batch_Processor::get_instance()->register($batch);
        $_REQUEST['batch_id'] = 'fake';

        Filters\expectApplied('dg_batch_item_success_message')->once()->with('Processed item with id 1', Mockery::type(WP_Batch_Item::class));

        $sent = handler_sends('process_next_item');

        expect($sent->success)->toBeTrue()
            ->and($sent->data)->toBe([
                'message' => 'Processed item with id 1',
                'is_finished' => 0,
                'total_processed' => 1,
                'total_items' => 2,
                'percentage' => '50.00',
            ])
            ->and($batch->processedIds)->toBe([1])
            ->and($batch->get_processed_items())->toBe([1]);
    });

    it('sends the success message the filter returns', function (): void {
        WP_Batch_Processor::get_instance()->register(new FakeBatch('fake', [1]));
        $_REQUEST['batch_id'] = 'fake';
        Filters\expectApplied('dg_batch_item_success_message')->once()->andReturn('Done one');

        expect(handler_sends('process_next_item')->data['message'])->toBe('Done one');
    });

    it('reports an item whose processing returned an error, and still marks it processed', function (): void {
        $batch = new FakeBatch('fake', [1, 2]);
        $batch->result = new WP_Error('bad', 'boom');
        WP_Batch_Processor::get_instance()->register($batch);
        $_REQUEST['batch_id'] = 'fake';

        Filters\expectApplied('dg_batch_item_error_message')->once()->with('Error processing item with id 1: boom', Mockery::type(WP_Batch_Item::class));

        $sent = handler_sends('process_next_item');

        expect($sent->success)->toBeFalse()
            ->and($sent->data)->toBe([
                'message' => 'Error processing item with id 1: boom',
                'is_finished' => 0,
                'total_processed' => 1,
                'total_items' => 2,
                'percentage' => '50.00',
            ])
            ->and($sent->status)->toBeNull()
            ->and($batch->get_processed_items())->toBe([1]);
    });

    it('sends the error message the filter returns', function (): void {
        $batch = new FakeBatch('fake', [1]);
        $batch->result = new WP_Error('bad', 'boom');
        WP_Batch_Processor::get_instance()->register($batch);
        $_REQUEST['batch_id'] = 'fake';
        Filters\expectApplied('dg_batch_item_error_message')->once()->andReturn('Custom failure');

        expect(handler_sends('process_next_item')->data['message'])->toBe('Custom failure');
    });

    it('finishes the batch once no item is left', function (): void {
        $batch = new FakeBatch('fake', [1, 2]);
        $batch->mark_as_processed(1);
        $batch->mark_as_processed(2);
        WP_Batch_Processor::get_instance()->register($batch);
        $_REQUEST['batch_id'] = 'fake';

        Filters\expectApplied('dg_batch_item_error_message')->once()->with('Processing finished.');

        $sent = handler_sends('process_next_item');

        expect($sent->success)->toBeTrue()
            ->and($sent->data)->toBe([
                'message' => 'Processing finished.',
                'is_finished' => 1,
                'total_processed' => 2,
                'total_items' => 2,
                'percentage' => '100.00',
            ])
            ->and($batch->finishCount)->toBe(1)
            ->and($batch->processedIds)->toBe([]);
    });

    it('does not call finish() while items remain', function (): void {
        $batch = new FakeBatch('fake', [1, 2]);
        WP_Batch_Processor::get_instance()->register($batch);
        $_REQUEST['batch_id'] = 'fake';

        handler_sends('process_next_item');

        expect($batch->finishCount)->toBe(0);
    });
});

describe('restart_batch', function (): void {
    it('refuses a request whose nonce does not check out', function (): void {
        $this->nonceValid = false;

        $sent = handler_sends('restart_batch');

        expect($sent->success)->toBeFalse()
            ->and($sent->data)->toBe(['message' => 'Permission denied.'])
            ->and($sent->status)->toBeNull()
            ->and($this->calls['check_ajax_referer'])->toBe([['wp-batch-processing', 'nonce', false]])
            ->and($this->calls)->not->toHaveKey('current_user_can');
    });

    it('refuses with 403 a user who lacks the capability, even with a valid nonce', function (): void {
        $_REQUEST['batch_id'] = 'fake';
        $this->userCan = false;

        $sent = handler_sends('restart_batch');

        expect($sent->success)->toBeFalse()
            ->and($sent->data)->toBe(['message' => 'Permission denied.'])
            ->and($sent->status)->toBe(403)
            ->and($this->calls['current_user_can'])->toBe([['manage_options']]);
    });

    it('refuses a request with no batch id', function (): void {
        $sent = handler_sends('restart_batch');

        expect($sent->success)->toBeFalse()
            ->and($sent->data)->toBe(['message' => 'Invalid batch id']);
    });

    it('clears the batch progress and reports success', function (): void {
        $batch = new FakeBatch('fake', [1, 2]);
        $batch->mark_as_processed(1);
        WP_Batch_Processor::get_instance()->register($batch);
        $_REQUEST['batch_id'] = 'fake';

        $sent = handler_sends('restart_batch');

        expect($sent->success)->toBeTrue()
            ->and($sent->data)->toBeNull()
            ->and($this->store)->toBe([]);
    });

    it('does not touch a batch that was not asked for', function (): void {
        $other = new FakeBatch('other', [1]);
        $other->mark_as_processed(1);
        WP_Batch_Processor::get_instance()->register($other);
        WP_Batch_Processor::get_instance()->register(new FakeBatch('fake', [1]));
        $_REQUEST['batch_id'] = 'fake';

        handler_sends('restart_batch');

        expect($this->store)->toBe(['batch_other_processed' => [1]]);
    });
});
