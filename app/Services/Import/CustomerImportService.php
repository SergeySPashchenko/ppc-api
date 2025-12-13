<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;

/**
 * Service for importing and syncing customers from external orders data.
 * Handles normalization, deduplication, and Amazon FBA orders.
 */
final class CustomerImportService
{
    /**
     * Import or update customer from order data.
     *
     * @param  array<string, mixed>  $orderData
     */
    public function importFromOrder(array $orderData): ?Customer
    {
        $email = $this->extractEmail($orderData);
        $name = $this->extractName($orderData);
        $phone = $this->extractPhone($orderData);

        // Amazon FBA / marketplace orders without email
        if (empty($email)) {
            return $this->handleAnonymousCustomer($orderData, $name, $phone);
        }

        // Find or create customer by email
        $customer = Customer::query()
            ->where('email', $email)
            ->first();

        if ($customer === null) {
            $customer = $this->createCustomer($email, $name, $phone);
            Log::info('Created new customer', ['customer_id' => $customer->id, 'email' => $email]);
        } else {
            $updated = $this->updateCustomerIfChanged($customer, $name, $phone);
            if ($updated) {
                Log::info('Updated customer', ['customer_id' => $customer->id, 'email' => $email]);
            }
        }

        return $customer;
    }

    /**
     * Extract email from order data.
     *
     * @param  array<string, mixed>  $orderData
     */
    private function extractEmail(array $orderData): ?string
    {
        $email = $orderData['Email'] ?? null;

        return $email ? trim((string) $email) : null;
    }

    /**
     * Extract name from order data (prefer billing name, fallback to shipping).
     *
     * @param  array<string, mixed>  $orderData
     */
    private function extractName(array $orderData): ?string
    {
        $name = $orderData['Name'] ?? $orderData['ShippingName'] ?? null;

        return $name ? trim((string) $name) : null;
    }

    /**
     * Extract phone from order data (prefer billing phone, fallback to shipping).
     *
     * @param  array<string, mixed>  $orderData
     */
    private function extractPhone(array $orderData): ?string
    {
        $phone = $orderData['Phone'] ?? $orderData['BillingPhone'] ?? $orderData['ShippingPhone'] ?? null;

        return $phone ? trim((string) $phone) : null;
    }

    /**
     * Handle anonymous/marketplace customers (Amazon FBA, etc.).
     *
     * @param  array<string, mixed>  $orderData
     */
    private function handleAnonymousCustomer(array $orderData, ?string $name, ?string $phone): ?Customer
    {
        // For anonymous orders, we still need a customer record
        // Use a special email format or create without email
        // Since email is nullable in our schema, we can create without it
        $customer = Customer::query()
            ->whereNull('email')
            ->where('name', $name ?? 'Anonymous')
            ->where('phone', $phone)
            ->first();

        if ($customer === null) {
            $customer = Customer::create([
                'email' => null,
                'name' => $name ?? 'Anonymous Customer',
                'phone' => $phone,
            ]);
            Log::info('Created anonymous customer', ['customer_id' => $customer->id]);
        } else {
            $updated = $this->updateCustomerIfChanged($customer, $name, $phone);
            if ($updated) {
                Log::info('Updated anonymous customer', ['customer_id' => $customer->id]);
            }
        }

        return $customer;
    }

    /**
     * Create new customer.
     */
    private function createCustomer(?string $email, ?string $name, ?string $phone): Customer
    {
        return Customer::create([
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
        ]);
    }

    /**
     * Update customer if data has changed.
     */
    private function updateCustomerIfChanged(Customer $customer, ?string $name, ?string $phone): bool
    {
        $changed = false;

        if ($name !== null && $customer->name !== $name) {
            $customer->name = $name;
            $changed = true;
        }

        if ($phone !== null && $customer->phone !== $phone) {
            $customer->phone = $phone;
            $changed = true;
        }

        if ($changed) {
            $customer->save();
        }

        return $changed;
    }
}
