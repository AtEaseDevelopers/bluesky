<?php

namespace App\Services;

use App\System;

class BlueskyDebtorsImportService
{
    /**
     * @return list<array{name:string,address_lines:list<string>,phones:list<string>}>
     */
    public function parseSheetRows(array $rows): array
    {
        $customers = [];
        $current = null;

        foreach ($rows as $index => $row) {
            $name = $this->cell($row, 0);
            $address = $this->cell($row, 1);
            $phone = $this->cell($row, 2);

            if ($this->isHeaderRow($name, $address, $phone, $index)) {
                continue;
            }

            if ($name !== '') {
                if ($current !== null) {
                    $customers[] = $current;
                }

                $current = [
                    'name' => $name,
                    'address_lines' => [],
                    'phones' => [],
                ];
            }

            if ($current === null) {
                continue;
            }

            if ($address !== '' && $address !== '·') {
                $current['address_lines'][] = $address;
            }

            if ($phone !== '') {
                $current['phones'][] = $phone;
            }
        }

        if ($current !== null) {
            $customers[] = $current;
        }

        return $this->dedupeNames($customers);
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>}  $row
     * @return array<string, mixed>
     */
    public function mapToCustomer(array $row, array $options): array
    {
        $address = $this->parseAddress($row['address_lines']);
        $phones = array_values(array_filter(array_map([$this, 'normalizePhone'], $row['phones'])));
        $primaryPhone = $phones[0] ?? '';

        $remarkParts = [];
        if ($address['full'] !== '' && mb_strlen($address['full']) > 100) {
            $remarkParts[] = 'Full address: ' . $address['full'];
        }
        if (count($phones) > 1) {
            $remarkParts[] = 'Other phones: ' . implode(', ', array_slice($phones, 1));
        }
        if ($options['remark_prefix'] !== '') {
            array_unshift($remarkParts, trim($options['remark_prefix']));
        }

        $customerType = $options['customer_type'];
        $paymentMethods = $customerType === 'credit'
            ? [\App\User::$payment_method['term']]
            : [\App\User::$payment_method['cod']];

        return [
            'name' => mb_substr($row['name'], 0, 100),
            'category' => $options['category'],
            'customer_type' => $customerType,
            'payment_term_days' => $customerType === 'credit' ? (int) $options['payment_term_days'] : null,
            'credit_balance' => 0,
            'attn_contact' => mb_substr($primaryPhone, 0, 30),
            'billing_address' => $address['billing_address'],
            'billing_city' => mb_substr($address['city'], 0, 50),
            'billing_postcode' => mb_substr($address['postcode'] ?: '00000', 0, 5),
            'billing_state' => mb_substr($address['state'] ?: 'Kuala Lumpur', 0, 30),
            'shipping_address' => $address['billing_address'],
            'shipping_city' => mb_substr($address['city'], 0, 50),
            'shipping_postcode' => mb_substr($address['postcode'] ?: '00000', 0, 5),
            'shipping_state' => mb_substr($address['state'] ?: 'Kuala Lumpur', 0, 30),
            'payment_method' => json_encode($paymentMethods),
            'remark' => mb_substr(implode(' | ', array_filter($remarkParts)), 0, 500),
            'price_permission' => 1,
            'invoice_visibility' => 1,
            'invoice_price_permission' => 1,
            'status' => \App\User::$user_status['active'],
            'registration_completed_at' => now(),
            'autocount_sync_status' => 'pending_sync',
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return array{billing_address:string,city:string,postcode:string,state:string,full:string}
     */
    public function parseAddress(array $lines): array
    {
        $full = preg_replace('/\s+/u', ' ', trim(implode(', ', array_filter($lines))));
        $full = str_replace(['，', '。'], [', ', ''], $full);

        $postcode = '';
        if ($full !== '' && preg_match('/(?<!\d)(\d{5})(?!\d)/', $full, $matches)) {
            $postcode = $matches[1];
        }

        $state = $this->detectState($full);
        $city = $this->detectCity($full, $state, $postcode);

        return [
            'billing_address' => mb_substr($full !== '' ? $full : '-', 0, 100),
            'city' => $city,
            'postcode' => $postcode,
            'state' => $state,
            'full' => $full,
        ];
    }

    private function detectState(string $full): string
    {
        if ($full === '') {
            return '';
        }

        foreach (System::$country_state['MY'] as $stateName) {
            if (stripos($full, $stateName) !== false) {
                return $stateName;
            }
        }

        $aliases = [
            '/\bK\.?\s*L\.?\b/i' => 'Kuala Lumpur',
            '/\bKUALA\s*LUMPUR\b/i' => 'Kuala Lumpur',
            '/\bP\.?\s*J\.?\b/i' => 'Selangor',
            '/\bPETALING\s*JAYA\b/i' => 'Selangor',
            '/\bSUBANG\s*JAYA\b/i' => 'Selangor',
            '/\bSHAH\s*ALAM\b/i' => 'Selangor',
            '/\bKLANG\b/i' => 'Selangor',
            '/\bPAHANG\b/i' => 'Pahang',
            '/\bPENANG\b/i' => 'Pulau Pinang',
            '/\bJOHOR\s*BAHRU\b/i' => 'Johor',
            '/\bJB\b/i' => 'Johor',
            '/\bKUANTAN\b/i' => 'Pahang',
            '/\bMELAKA\b/i' => 'Melaka',
            '/\bMALACCA\b/i' => 'Melaka',
        ];

        foreach ($aliases as $pattern => $stateName) {
            if (preg_match($pattern, $full)) {
                return $stateName;
            }
        }

        return '';
    }

    private function detectCity(string $full, string $state, string $postcode): string
    {
        $cities = [
            'Petaling Jaya',
            'Kuala Lumpur',
            'Shah Alam',
            'Subang Jaya',
            'Klang',
            'Kuantan',
            'Johor Bahru',
            'Georgetown',
            'Ipoh',
            'Melaka',
            'Seremban',
            'Kuchai Lama',
            'Brickfield',
            'Happy Garden',
        ];

        foreach ($cities as $city) {
            if (stripos($full, $city) !== false) {
                return $city;
            }
        }

        if ($postcode !== '') {
            $beforePostcode = trim(preg_replace('/\b' . preg_quote($postcode, '/') . '\b.*$/', '', $full));
            $parts = array_map('trim', explode(',', $beforePostcode));
            $candidate = end($parts);
            if ($candidate !== false && $candidate !== '' && mb_strlen($candidate) <= 50) {
                return $candidate;
            }
        }

        return $state;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/u', ' ', trim($phone));

        return $phone ?? '';
    }

    private function isHeaderRow(string $name, string $address, string $phone, int $index): bool
    {
        if ($index === 0 && stripos($name, 'DEBTORS') !== false) {
            return true;
        }

        if (strcasecmp($name, 'NAME') === 0 || strcasecmp($address, 'ADDRESS') === 0) {
            return true;
        }

        return strcasecmp($phone, 'TELEPHONE NO.') === 0;
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>}>  $customers
     * @return list<array{name:string,address_lines:list<string>,phones:list<string>}>
     */
    private function dedupeNames(array $customers): array
    {
        $seen = [];

        foreach ($customers as &$customer) {
            $key = mb_strtoupper(trim($customer['name']));
            $seen[$key] = ($seen[$key] ?? 0) + 1;

            if ($seen[$key] > 1) {
                $suffix = ' (' . $seen[$key] . ')';
                $customer['name'] = mb_substr($customer['name'], 0, 100 - mb_strlen($suffix)) . $suffix;
            }
        }
        unset($customer);

        return $customers;
    }

    private function cell(array $row, int $index): string
    {
        $value = $row[$index] ?? '';

        return trim((string) $value);
    }
}
