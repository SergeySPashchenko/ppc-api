<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

/**
 * Service for importing and syncing addresses from order data.
 * Handles billing/shipping logic, deduplication, and address type determination.
 */
final class AddressImportService
{
    /**
     * Import addresses from order data and link to customer and order.
     *
     * @param  array<string, mixed>  $orderData
     * @return array<int, Address>
     */
    public function importFromOrder(array $orderData, Customer $customer, int $orderId): array
    {
        $billingData = $this->extractBillingAddress($orderData);
        $shippingData = $this->extractShippingAddress($orderData);

        $addresses = [];

        // Case 1: Only billing fields exist
        if ($this->hasAddressData($billingData) && ! $this->hasAddressData($shippingData)) {
            $address = $this->findOrCreateAddress($billingData, 'billing', $customer);
            $addresses[] = $address;
        }
        // Case 2: Only shipping fields exist
        elseif (! $this->hasAddressData($billingData) && $this->hasAddressData($shippingData)) {
            $address = $this->findOrCreateAddress($shippingData, 'shipping', $customer);
            $addresses[] = $address;
        }
        // Case 3: Both exist
        elseif ($this->hasAddressData($billingData) && $this->hasAddressData($shippingData)) {
            // Check if billing == shipping
            if ($this->addressesEqual($billingData, $shippingData)) {
                $address = $this->findOrCreateAddress($billingData, 'both', $customer);
                $addresses[] = $address;
            } else {
                // Create two separate addresses
                $billingAddress = $this->findOrCreateAddress($billingData, 'billing', $customer);
                $shippingAddress = $this->findOrCreateAddress($shippingData, 'shipping', $customer);
                $addresses[] = $billingAddress;
                $addresses[] = $shippingAddress;
            }
        }

        // Link addresses to order
        foreach ($addresses as $address) {
            $address->orders()->syncWithoutDetaching([$orderId]);
        }

        return $addresses;
    }

    /**
     * Extract billing address data from order.
     *
     * @param  array<string, mixed>  $orderData
     * @return array<string, mixed>
     */
    private function extractBillingAddress(array $orderData): array
    {
        return [
            'name' => $orderData['Name'] ?? null,
            'address' => $orderData['BillingAddress'] ?? null,
            'address2' => $orderData['BillingAddress2'] ?? null,
            'city' => $orderData['BillingCity'] ?? null,
            'state' => $orderData['BillingState'] ?? null,
            'zip' => $orderData['BillingZip'] ?? null,
            'country' => $orderData['BillingCountry'] ?? null,
            'phone' => $orderData['BillingPhone'] ?? $orderData['Phone'] ?? null,
        ];
    }

    /**
     * Extract shipping address data from order.
     *
     * @param  array<string, mixed>  $orderData
     * @return array<string, mixed>
     */
    private function extractShippingAddress(array $orderData): array
    {
        return [
            'name' => $orderData['ShippingName'] ?? null,
            'address' => $orderData['ShippingAddress'] ?? null,
            'address2' => $orderData['ShippingAddress2'] ?? null,
            'city' => $orderData['ShippingCity'] ?? null,
            'state' => $orderData['ShippingState'] ?? null,
            'zip' => $orderData['ShippingZip'] ?? null,
            'country' => $orderData['ShippingCountry'] ?? null,
            'phone' => $orderData['ShippingPhone'] ?? null,
        ];
    }

    /**
     * Check if address data has meaningful content.
     *
     * @param  array<string, mixed>  $addressData
     */
    private function hasAddressData(array $addressData): bool
    {
        $fields = ['address', 'city', 'zip'];

        foreach ($fields as $field) {
            if (! empty($addressData[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if two addresses are equal (for deduplication).
     *
     * @param  array<string, mixed>  $address1
     * @param  array<string, mixed>  $address2
     */
    private function addressesEqual(array $address1, array $address2): bool
    {
        $fields = ['address', 'address2', 'city', 'state', 'zip', 'country'];

        foreach ($fields as $field) {
            $val1 = trim((string) ($address1[$field] ?? ''));
            $val2 = trim((string) ($address2[$field] ?? ''));

            if (strtolower($val1) !== strtolower($val2)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find existing address or create new one.
     * Uses address_hash for deduplication.
     *
     * @param  array<string, mixed>  $addressData
     */
    private function findOrCreateAddress(array $addressData, string $type, Customer $customer): Address
    {
        $addressHash = $this->generateAddressHash($addressData);

        // Try to find existing address by hash and customer
        $address = Address::query()
            ->where('address_hash', $addressHash)
            ->where('customer_id', $customer->id)
            ->first();

        if ($address !== null) {
            // Update type if needed (e.g., billing -> both)
            if ($address->type !== $type && $address->type !== 'both') {
                if ($type === 'both' || ($address->type === 'billing' && $type === 'shipping') || ($address->type === 'shipping' && $type === 'billing')) {
                    $address->type = 'both';
                    $address->save();
                    Log::info('Updated address type to both', ['address_id' => $address->id]);
                }
            }

            return $address;
        }

        // Create new address
        $address = Address::create([
            'type' => $type,
            'name' => $addressData['name'],
            'address' => $addressData['address'],
            'address2' => $addressData['address2'],
            'city' => $addressData['city'],
            'state' => $addressData['state'],
            'zip' => $addressData['zip'],
            'country' => $addressData['country'],
            'phone' => $addressData['phone'],
            'address_hash' => $addressHash,
            'customer_id' => $customer->id,
        ]);

        Log::info('Created new address', ['address_id' => $address->id, 'type' => $type]);

        return $address;
    }

    /**
     * Generate hash for address deduplication.
     *
     * @param  array<string, mixed>  $addressData
     */
    private function generateAddressHash(array $addressData): string
    {
        $normalized = [
            'address' => strtolower(trim((string) ($addressData['address'] ?? ''))),
            'address2' => strtolower(trim((string) ($addressData['address2'] ?? ''))),
            'city' => strtolower(trim((string) ($addressData['city'] ?? ''))),
            'state' => strtolower(trim((string) ($addressData['state'] ?? ''))),
            'zip' => strtolower(trim((string) ($addressData['zip'] ?? ''))),
            'country' => strtolower(trim((string) ($addressData['country'] ?? ''))),
        ];

        return md5(serialize($normalized));
    }
}
