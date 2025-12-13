<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductItem;
use App\Services\Import\AddressImportService;
use App\Services\Import\CustomerImportService;
use App\Services\Import\DateRangeResolver;
use App\Services\Import\ExpenseImportService;
use App\Services\Import\OrderImportService;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Create test products and product items
    $this->product = Product::factory()->create(['ProductID' => 1]);
    $this->productItem = ProductItem::factory()->create([
        'ItemID' => 100,
        'ProductID' => $this->product->ProductID,
    ]);
    // Create expense type for expense tests
    $this->expenseType = \App\Models\Expensetype::factory()->create(['ExpenseID' => 1]);
});

test('date range resolver handles single date', function () {
    $resolver = new DateRangeResolver;
    [$from, $to] = $resolver->resolve(date: '2022-07-02');

    expect($from->format('Y-m-d'))->toBe('2022-07-02');
    expect($to->format('Y-m-d'))->toBe('2022-07-02');
});

test('date range resolver handles date range', function () {
    $resolver = new DateRangeResolver;
    [$from, $to] = $resolver->resolve(from: '2022-07-01', to: '2022-07-31');

    expect($from->format('Y-m-d'))->toBe('2022-07-01');
    expect($to->format('Y-m-d'))->toBe('2022-07-31');
});

test('date range resolver handles last N days', function () {
    $resolver = new DateRangeResolver;
    [$from, $to] = $resolver->resolve(lastDays: 7);

    expect($from->lessThanOrEqualTo(Carbon::now()))->toBeTrue();
    expect($to->greaterThanOrEqualTo(Carbon::now()->subDays(7)))->toBeTrue();
});

test('customer import creates new customer with email', function () {
    $service = new CustomerImportService;

    $orderData = [
        'Email' => 'test@example.com',
        'Name' => 'Test Customer',
        'Phone' => '1234567890',
    ];

    $customer = $service->importFromOrder($orderData);

    expect($customer)->not->toBeNull();
    expect($customer->email)->toBe('test@example.com');
    expect($customer->name)->toBe('Test Customer');
    expect($customer->phone)->toBe('1234567890');
});

test('customer import updates existing customer if data changed', function () {
    $service = new CustomerImportService;

    $existingCustomer = Customer::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Old Name',
        'phone' => '1111111111',
    ]);

    $orderData = [
        'Email' => 'test@example.com',
        'Name' => 'New Name',
        'Phone' => '2222222222',
    ];

    $customer = $service->importFromOrder($orderData);

    expect($customer->id)->toBe($existingCustomer->id);
    expect($customer->name)->toBe('New Name');
    expect($customer->phone)->toBe('2222222222');
});

test('customer import does not update if data unchanged', function () {
    $service = new CustomerImportService;

    $existingCustomer = Customer::factory()->create([
        'email' => 'test@example.com',
        'name' => 'Test Name',
        'phone' => '1234567890',
    ]);

    $originalUpdatedAt = $existingCustomer->updated_at;

    $orderData = [
        'Email' => 'test@example.com',
        'Name' => 'Test Name',
        'Phone' => '1234567890',
    ];

    $customer = $service->importFromOrder($orderData);

    expect($customer->id)->toBe($existingCustomer->id);
    expect($customer->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

test('customer import handles Amazon FBA orders without email', function () {
    $service = new CustomerImportService;

    $orderData = [
        'Name' => 'Amazon Customer',
        'Phone' => null,
    ];

    $customer = $service->importFromOrder($orderData);

    expect($customer)->not->toBeNull();
    expect($customer->email)->toBeNull();
    expect($customer->name)->toBe('Amazon Customer');
});

test('address import creates billing address when only billing fields exist', function () {
    $service = new AddressImportService;
    $customer = Customer::factory()->create();
    $order = Order::factory()->create();

    $orderData = [
        'Name' => 'John Doe',
        'BillingAddress' => '123 Main St',
        'BillingCity' => 'New York',
        'BillingState' => 'NY',
        'BillingZip' => '10001',
        'BillingCountry' => 'USA',
    ];

    $addresses = $service->importFromOrder($orderData, $customer, $order->id);

    expect($addresses)->toHaveCount(1);
    expect($addresses[0]->type)->toBe('billing');
    expect($addresses[0]->address)->toBe('123 Main St');
});

test('address import creates shipping address when only shipping fields exist', function () {
    $service = new AddressImportService;
    $customer = Customer::factory()->create();
    $order = Order::factory()->create();

    $orderData = [
        'ShippingName' => 'Jane Doe',
        'ShippingAddress' => '456 Oak Ave',
        'ShippingCity' => 'Los Angeles',
        'ShippingState' => 'CA',
        'ShippingZip' => '90001',
        'ShippingCountry' => 'USA',
    ];

    $addresses = $service->importFromOrder($orderData, $customer, $order->id);

    expect($addresses)->toHaveCount(1);
    expect($addresses[0]->type)->toBe('shipping');
    expect($addresses[0]->address)->toBe('456 Oak Ave');
});

test('address import creates single address with type both when billing equals shipping', function () {
    $service = new AddressImportService;
    $customer = Customer::factory()->create();
    $order = Order::factory()->create();

    $orderData = [
        'Name' => 'John Doe',
        'BillingAddress' => '123 Main St',
        'BillingCity' => 'New York',
        'BillingState' => 'NY',
        'BillingZip' => '10001',
        'BillingCountry' => 'USA',
        'ShippingAddress' => '123 Main St',
        'ShippingCity' => 'New York',
        'ShippingState' => 'NY',
        'ShippingZip' => '10001',
        'ShippingCountry' => 'USA',
    ];

    $addresses = $service->importFromOrder($orderData, $customer, $order->id);

    expect($addresses)->toHaveCount(1);
    expect($addresses[0]->type)->toBe('both');
});

test('address import creates two addresses when billing differs from shipping', function () {
    $service = new AddressImportService;
    $customer = Customer::factory()->create();
    $order = Order::factory()->create();

    $orderData = [
        'Name' => 'John Doe',
        'BillingAddress' => '123 Main St',
        'BillingCity' => 'New York',
        'BillingState' => 'NY',
        'BillingZip' => '10001',
        'ShippingAddress' => '456 Oak Ave',
        'ShippingCity' => 'Los Angeles',
        'ShippingState' => 'CA',
        'ShippingZip' => '90001',
    ];

    $addresses = $service->importFromOrder($orderData, $customer, $order->id);

    expect($addresses)->toHaveCount(2);
    expect($addresses[0]->type)->toBe('billing');
    expect($addresses[1]->type)->toBe('shipping');
});

test('address import deduplicates addresses using hash', function () {
    $service = new AddressImportService;
    $customer = Customer::factory()->create();
    $order1 = Order::factory()->create();
    $order2 = Order::factory()->create();

    $orderData = [
        'Name' => 'John Doe',
        'BillingAddress' => '123 Main St',
        'BillingCity' => 'New York',
        'BillingState' => 'NY',
        'BillingZip' => '10001',
        'BillingCountry' => 'USA',
    ];

    $addresses1 = $service->importFromOrder($orderData, $customer, $order1->id);
    $addresses2 = $service->importFromOrder($orderData, $customer, $order2->id);

    expect($addresses1)->toHaveCount(1);
    expect($addresses2)->toHaveCount(1);
    expect($addresses1[0]->id)->toBe($addresses2[0]->id);
});

test('re-running import does not create duplicate orders', function () {
    $customerService = new CustomerImportService;
    $addressService = new AddressImportService;
    $orderItemService = new \App\Services\Import\OrderItemImportService;
    $service = new OrderImportService($customerService, $addressService, $orderItemService);

    $orderData = [
        'OrderID' => 12345,
        'Agent' => 'Test Agent',
        'Created' => '2022-07-02 10:00:00',
        'OrderDate' => '20220702',
        'OrderNum' => 'ORD-001',
        'ProductTotal' => 100.00,
        'GrandTotal' => 110.00,
        'RefundAmount' => 0,
        'BrandID' => $this->product->ProductID,
        'Email' => 'test@example.com',
        'Name' => 'Test Customer',
    ];

    // First import
    $result1 = $service->import([$orderData]);
    expect($result1['created'])->toBe(1);
    expect(Order::where('OrderID', 12345)->count())->toBe(1);

    // Second import (idempotent)
    $result2 = $service->import([$orderData]);
    expect($result2['created'])->toBe(0);
    expect($result2['updated'])->toBe(1);
    expect(Order::where('OrderID', 12345)->count())->toBe(1);
});

test('updated external data updates internal records', function () {
    $customerService = new CustomerImportService;
    $addressService = new AddressImportService;
    $orderItemService = new \App\Services\Import\OrderItemImportService;
    $service = new OrderImportService($customerService, $addressService, $orderItemService);

    $orderData = [
        'OrderID' => 12345,
        'Agent' => 'Test Agent',
        'Created' => '2022-07-02 10:00:00',
        'OrderDate' => '20220702',
        'OrderNum' => 'ORD-001',
        'ProductTotal' => 100.00,
        'GrandTotal' => 110.00,
        'RefundAmount' => 0,
        'BrandID' => $this->product->ProductID,
        'Email' => 'test@example.com',
        'Name' => 'Test Customer',
    ];

    // First import
    $service->import([$orderData]);
    $order = Order::where('OrderID', 12345)->first();
    expect($order->GrandTotal)->toBe('110.00');

    // Updated data
    $orderData['GrandTotal'] = 120.00;
    $service->import([$orderData]);

    $order->refresh();
    expect($order->GrandTotal)->toBe('120.00');
});

test('order import skips orders with non-existent products', function () {
    $customerService = new CustomerImportService;
    $addressService = new AddressImportService;
    $orderItemService = new \App\Services\Import\OrderItemImportService;
    $service = new OrderImportService($customerService, $addressService, $orderItemService);

    $orderData = [
        'OrderID' => 12345,
        'Agent' => 'Test Agent',
        'Created' => '2022-07-02 10:00:00',
        'OrderDate' => '20220702',
        'OrderNum' => 'ORD-001',
        'ProductTotal' => 100.00,
        'GrandTotal' => 110.00,
        'RefundAmount' => 0,
        'BrandID' => 99999, // Non-existent ProductID
        'Email' => 'test@example.com',
        'Name' => 'Test Customer',
    ];

    $result = $service->import([$orderData]);

    expect($result['skipped'])->toBe(1);
    expect(Order::where('OrderID', 12345)->count())->toBe(0);
});

test('order item import skips items with non-existent product items', function () {
    $service = new \App\Services\Import\OrderItemImportService;
    $order = Order::factory()->create();

    $itemData = [
        'idOrderItem' => 1,
        'ItemID' => 99999, // Non-existent ItemID
        'Price' => 10.00,
        'Qty' => 2,
    ];

    $result = $service->import([$itemData], $order);

    expect($result['skipped'])->toBe(1);
    expect(OrderItem::where('idOrderItem', 1)->count())->toBe(0);
});

test('expense import skips expenses with non-existent products', function () {
    $service = new ExpenseImportService;

    $expenseData = [
        'id' => 1,
        'ProductID' => 99999, // Non-existent ProductID
        'ExpenseID' => $this->expenseType->ExpenseID,
        'ExpenseDate' => '2022-07-02',
        'Expense' => 50.00,
    ];

    $result = $service->import([$expenseData]);

    expect($result['skipped'])->toBe(1);
    expect(Expense::where('id', 1)->count())->toBe(0);
});

test('expense import creates new expense', function () {
    $service = new ExpenseImportService;

    $expenseData = [
        'id' => 1,
        'ProductID' => $this->product->ProductID,
        'ExpenseID' => $this->expenseType->ExpenseID,
        'ExpenseDate' => '2022-07-02',
        'Expense' => 50.00,
    ];

    $result = $service->import([$expenseData]);

    expect($result['created'])->toBe(1);
    expect(Expense::where('ProductID', $this->product->ProductID)->count())->toBe(1);
});

test('expense import is idempotent', function () {
    $service = new ExpenseImportService;

    $expenseData = [
        'id' => 1,
        'ProductID' => $this->product->ProductID,
        'ExpenseID' => $this->expenseType->ExpenseID,
        'ExpenseDate' => '2022-07-02',
        'Expense' => 50.00,
    ];

    // First import
    $result1 = $service->import([$expenseData]);
    expect($result1['created'])->toBe(1);

    // Second import (idempotent)
    $result2 = $service->import([$expenseData]);
    expect($result2['created'])->toBe(0);
    expect($result2['updated'])->toBe(1);
    expect(Expense::where('ProductID', $this->product->ProductID)->count())->toBe(1);
});
