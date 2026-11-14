<?php

/**
 * WP_BP_List_Table: the table of registered batches on the list view.
 *
 * Its parent, WP_List_Table, is a stand-in (tests/Fixtures/wordpress-classes.php); what is
 * tested is what this class adds: the columns, how a cell renders, and where the items come from.
 *
 * @see includes/class-batch-list-table.php
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

use function Brain\Monkey\Functions\when;

beforeEach(function (): void {
    $_GET = [];
    forget_singleton(WP_Batch_Processor::class);
});

afterEach(function (): void {
    $_GET = [];
    forget_singleton(WP_Batch_Processor::class);
});

it('passes the table its singular and plural names and turns AJAX off', function (): void {
    $args = (new ReflectionProperty(WP_BP_List_Table::class, '_args'))->getValue(new WP_BP_List_Table);

    expect($args)->toBe(['singular' => 'batch', 'plural' => 'batches', 'ajax' => false]);
});

it('styles the table with the core classes plus its plural name', function (): void {
    expect((new WP_BP_List_Table)->get_table_classes())->toBe(['widefat', 'fixed', 'striped', 'batches']);
});

it('leaves the plural name out of the classes when it is not a string', function (): void {
    $table = new WP_BP_List_Table;
    (new ReflectionProperty(WP_BP_List_Table::class, '_args'))->setValue($table, ['plural' => 5]);

    expect($table->get_table_classes())->toBe(['widefat', 'fixed', 'striped', '']);
});

it('says there are no batches when the list is empty', function (): void {
    ob_start();
    (new WP_BP_List_Table)->no_items();

    expect(ob_get_clean())->toBe('No batches found. Read the documentation on the plugin github page to see how to register ones.');
});

it('has three columns and no sortable columns, bulk actions or checkbox', function (): void {
    $table = new WP_BP_List_Table;

    expect($table->get_columns())->toBe([
        'title' => 'Title',
        'total_processed' => 'Total Processed',
        'total_items' => 'Total Items',
    ])
        ->and($table->get_sortable_columns())->toBe([])
        ->and($table->get_bulk_actions())->toBe([])
        ->and($table->column_cb(new FakeBatch('b')))->toBe('');
});

describe('column_default', function (): void {
    it('shows the batch title, processed count and item count', function (): void {
        $store = [];
        stub_options($store);
        $batch = new FakeBatch('b', [1, 2, 3]);
        $batch->title = 'My batch';
        $batch->mark_as_processed(1);

        $table = new WP_BP_List_Table;

        expect($table->column_default($batch, 'title'))->toBe('My batch')
            ->and($table->column_default($batch, 'total_processed'))->toBe(1)
            ->and($table->column_default($batch, 'total_items'))->toBe(3);
    });

    it('falls back to a property of the batch with that name', function (): void {
        $batch = new FakeBatch('b');

        expect((new WP_BP_List_Table)->column_default($batch, 'id'))->toBe('b');
    });

    it('shows nothing for a column the batch has no property for', function (): void {
        expect((new WP_BP_List_Table)->column_default(new FakeBatch('b'), 'nope'))->toBe('');
    });
});

it('links the title to the batch view and adds a Manage row action', function (): void {
    when('admin_url')->alias(fn (string $path): string => 'https://example.test/wp-admin/'.$path);
    $batch = new FakeBatch('b');
    $batch->id = '7';
    $batch->title = 'My batch';

    $url = 'https://example.test/wp-admin/admin.php?page=dg-batches&action=view&id=7';

    expect((new WP_BP_List_Table)->column_title($batch))->toBe(
        '<a href="'.$url.'"><strong>My batch</strong></a> '
        .'<div class="row-actions"><a href="'.$url.'" data-id="7" title="Manage Batch">Manage</a></div>'
    );
});

describe('prepare_items', function (): void {
    it('takes its items from the registered batches and sets the column headers', function (): void {
        $first = new FakeBatch('first');
        $second = new FakeBatch('second');
        WP_Batch_Processor::get_instance()->register($first);
        WP_Batch_Processor::get_instance()->register($second);
        $table = new WP_BP_List_Table;

        $table->prepare_items();

        expect($table->items)->toBe([$first, $second])
            ->and($table->_column_headers)->toBe([$table->get_columns(), [], []]);
    });

    it('defaults the page status to 2', function (): void {
        $table = new WP_BP_List_Table;

        $table->prepare_items();

        expect($table->page_status)->toBe('2');
    });

    it('defaults the page status to 2 when the query string holds an array', function (): void {
        $_GET['status'] = ['5'];
        Functions\expect('sanitize_text_field')->never();
        $table = new WP_BP_List_Table;

        $table->prepare_items();

        expect($table->page_status)->toBe('2');
    });

    it('takes the page status from the query string, sanitized', function (): void {
        $_GET['status'] = ' 5 ';
        Functions\expect('sanitize_text_field')->once()->with(' 5 ')->andReturn('5');
        $table = new WP_BP_List_Table;

        $table->prepare_items();

        expect($table->page_status)->toBe('5');
    });
});
