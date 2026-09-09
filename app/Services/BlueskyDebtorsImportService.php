<?php

namespace App\Services;

use App\Order;
use App\ProductVisibility;
use App\System;
use App\User;
use Illuminate\Support\Facades\DB;

class BlueskyDebtorsImportService
{
    /**
     * @return list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>
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

            if ($this->isBlankRow($name, $address, $phone)) {
                if ($current !== null) {
                    $customers[] = $this->finalizeCustomerBlock($current);
                    $current = null;
                }

                continue;
            }

            if ($name !== '') {
                if ($current === null) {
                    $current = $this->newCustomerBlock($name);
                } else {
                    $current['alias_names'][] = $name;
                }
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
            $customers[] = $this->finalizeCustomerBlock($current);
        }

        return $this->dedupeNames($customers);
    }

    /**
     * @return array{name:string,alias_names:list<string>,address_lines:list<string>,phones:list<string>}
     */
    private function newCustomerBlock(string $name): array
    {
        return [
            'name' => $name,
            'alias_names' => [],
            'address_lines' => [],
            'phones' => [],
        ];
    }

    /**
     * @param  array{name:string,alias_names:list<string>,address_lines:list<string>,phones:list<string>}  $current
     * @return array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}
     */
    private function finalizeCustomerBlock(array $current): array
    {
        $name = $current['name'];
        $aliases = array_values(array_filter($current['alias_names']));
        $legalName = null;
        $extraAliases = [];

        if (count($aliases) === 1 && $this->shouldPreferAliasAsCustomerName($name, $aliases[0])) {
            $legalName = $name;
            $name = $aliases[0];
        } elseif ($aliases !== []) {
            $extraAliases = $aliases;
        }

        $customer = [
            'name' => $name,
            'address_lines' => $current['address_lines'],
            'phones' => $current['phones'],
        ];

        if ($legalName !== null) {
            $customer['legal_name'] = $legalName;
        }

        if ($extraAliases !== []) {
            $customer['extra_aliases'] = $extraAliases;
        }

        return $customer;
    }

    private function shouldPreferAliasAsCustomerName(string $legalName, string $alias): bool
    {
        if (!$this->looksLikeLegalEntity($legalName)) {
            return false;
        }

        $alias = trim($alias);
        if ($alias === '') {
            return false;
        }

        if (preg_match('/\b(restaurant|restoran|cafe|coffee|steamboat|seafood|kitchen|dining|bistro|bar|hotel|enterprise|sdn|bhd)\b/i', $alias)) {
            return true;
        }

        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $alias) && mb_strlen($alias) <= 4) {
            return false;
        }

        return mb_strlen($alias) >= 8;
    }

    private function looksLikeLegalEntity(string $name): bool
    {
        return (bool) preg_match('/\b(SDN\.?\s*BHD|BHD|ENTERPRISE|\(M\))\b/i', $name);
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>}  $row
     * @return array<string, mixed>
     */
    public function mapToCustomerUpdates(array $row, array $options): array
    {
        $mapped = $this->mapToCustomer($row, $options);

        return [
            'category' => $mapped['category'],
            'customer_type' => $mapped['customer_type'],
            'payment_term_days' => $mapped['payment_term_days'],
            'attn_contact' => $mapped['attn_contact'],
            'billing_address' => $mapped['billing_address'],
            'billing_city' => $mapped['billing_city'],
            'billing_postcode' => $mapped['billing_postcode'],
            'billing_state' => $mapped['billing_state'],
            'shipping_address' => $mapped['shipping_address'],
            'shipping_city' => $mapped['shipping_city'],
            'shipping_postcode' => $mapped['shipping_postcode'],
            'shipping_state' => $mapped['shipping_state'],
            'payment_method' => $mapped['payment_method'],
            'remark' => $mapped['remark'],
            'status' => User::$user_status['active'],
            'autocount_sync_status' => 'pending_sync',
            'autocount_synced_at' => null,
        ];
    }

    public static function normalizeMatchName(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $name = preg_replace('/\s+\(\d+\)$/', '', $name);

        return mb_strtolower($name);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $customersByName
     */
    public function findExistingCustomer(string $name, $customersByName): ?\App\User
    {
        $keys = array_unique(array_filter([
            self::normalizeMatchName($name),
        ]));

        foreach ($keys as $key) {
            if ($customersByName->has($key)) {
                return $customersByName->get($key);
            }
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<string, \App\User>
     */
    public function indexCustomersByName(): \Illuminate\Support\Collection
    {
        $indexed = collect();

        foreach (\App\User::query()->get(['id', 'name', 'sql_customer_code']) as $user) {
            $key = self::normalizeMatchName($user->name);
            if (!$indexed->has($key)) {
                $indexed->put($key, $user);
            }
        }

        return $indexed;
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
        if (!empty($row['legal_name'])) {
            $remarkParts[] = 'Company: ' . $row['legal_name'];
        }
        if (!empty($row['extra_aliases'])) {
            $remarkParts[] = 'Also known as: ' . implode(', ', $row['extra_aliases']);
        }
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

    private function isBlankRow(string $name, string $address, string $phone): bool
    {
        if ($name !== '' || $phone !== '') {
            return false;
        }

        return $address === '' || $address === '·';
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

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return list<array<string, string|int>>
     */
    public function buildPreviewReport(array $parsed, array $options): array
    {
        $companyCounts = [];
        foreach ($parsed as $row) {
            $key = $this->companyKey($row);
            $companyCounts[$key] = ($companyCounts[$key] ?? 0) + 1;
        }

        $report = [];
        foreach ($parsed as $index => $row) {
            $mapped = $this->mapToCustomer($row, $options);
            $companyKey = $this->companyKey($row);
            $accountsForCompany = $companyCounts[$companyKey];

            $report[] = [
                'no' => $index + 1,
                'customer_name' => $row['name'],
                'company_name' => $this->displayCompanyName($row),
                'company_key' => $companyKey,
                'accounts_for_company' => $accountsForCompany,
                'multiple_accounts' => $accountsForCompany > 1 ? 'Yes' : 'No',
                'phone' => $mapped['attn_contact'],
                'address' => $mapped['billing_address'],
                'aliases' => !empty($row['extra_aliases']) ? implode('; ', $row['extra_aliases']) : '',
            ];
        }

        return $report;
    }

    /**
     * @param  list<array<string, string|int>>  $report
     * @return list<array{company_name:string,accounts:int,customer_names:string}>
     */
    public function summarizeMultiAccountCompanies(array $report): array
    {
        $groups = [];

        foreach ($report as $row) {
            if ((int) $row['accounts_for_company'] <= 1) {
                continue;
            }

            $key = (string) $row['company_key'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'company_name' => (string) $row['company_name'],
                    'accounts' => (int) $row['accounts_for_company'],
                    'customer_names' => [],
                ];
            }

            $groups[$key]['customer_names'][] = (string) $row['customer_name'];
        }

        usort($groups, fn ($a, $b) => $b['accounts'] <=> $a['accounts'] ?: strcmp($a['company_name'], $b['company_name']));

        foreach ($groups as &$group) {
            $group['customer_names'] = implode(' | ', array_unique($group['customer_names']));
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * @param  list<array<string, string|int>>  $report
     * @param  list<array{company_name:string,accounts:int,customer_names:string}>  $multiAccountSummary
     */
    public function writePreviewXlsx(array $report, array $multiAccountSummary, string $path): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $customersSheet = $spreadsheet->getActiveSheet();
        $customersSheet->setTitle('Parsed Customers');
        $customersSheet->fromArray([
            ['No', 'Customer Name', 'Company Name', 'Accounts For Company', 'Multiple Accounts', 'Phone', 'Address', 'Other Names'],
        ]);

        $rowIndex = 2;
        foreach ($report as $row) {
            $customersSheet->fromArray([[
                $row['no'],
                $row['customer_name'],
                $row['company_name'],
                $row['accounts_for_company'],
                $row['multiple_accounts'],
                $row['phone'],
                $row['address'],
                $row['aliases'],
            ]], null, 'A' . $rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'H') as $column) {
            $customersSheet->getColumnDimension($column)->setAutoSize(true);
        }
        $customersSheet->freezePane('A2');

        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Multi-Account Companies');
        $summarySheet->fromArray([
            ['Company Name', 'Accounts', 'Customer Names'],
        ]);

        $summaryRow = 2;
        foreach ($multiAccountSummary as $group) {
            $summarySheet->fromArray([[
                $group['company_name'],
                $group['accounts'],
                $group['customer_names'],
            ]], null, 'A' . $summaryRow);
            $summaryRow++;
        }

        foreach (range('A', 'C') as $column) {
            $summarySheet->getColumnDimension($column)->setAutoSize(true);
        }
        $summarySheet->freezePane('A2');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }

    /**
     * @param  list<array<string, string|int>>  $report
     */
    public function writePreviewCsv(array $report, string $path): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Unable to write preview CSV: ' . $path);
        }

        fputcsv($handle, [
            'No',
            'Customer Name',
            'Company Name',
            'Accounts For Company',
            'Multiple Accounts',
            'Phone',
            'Address',
            'Other Names',
        ]);

        foreach ($report as $row) {
            fputcsv($handle, [
                $row['no'],
                $row['customer_name'],
                $row['company_name'],
                $row['accounts_for_company'],
                $row['multiple_accounts'],
                $row['phone'],
                $row['address'],
                $row['aliases'],
            ]);
        }

        fclose($handle);
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}  $row
     */
    public function displayCompanyName(array $row): string
    {
        if (!empty($row['legal_name'])) {
            return $row['legal_name'];
        }

        return preg_replace('/\s+\(\d+\)$/', '', trim($row['name']));
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}  $row
     */
    public function companyKey(array $row): string
    {
        $name = $row['legal_name'] ?? $row['name'];
        $name = preg_replace('/\s+\(\d+\)$/', '', trim($name));

        return self::normalizeMatchName($name);
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return array{created:int,updated:int,removed:list<string>,skipped:list<string>}
     */
    public function syncCustomers(
        array $parsed,
        array $options,
        string $password,
        bool $updateMode,
        bool $skipExisting,
        bool $pruneStale
    ): array {
        if (!$updateMode && !$skipExisting) {
            return $this->createCustomers($parsed, $options, $password);
        }

        $customersByName = $this->indexCustomersByName();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $reconcile = ['removed' => [], 'deactivated' => [], 'skipped' => []];

        DB::transaction(function () use (
            $parsed,
            $options,
            $password,
            $updateMode,
            $skipExisting,
            $pruneStale,
            &$customersByName,
            &$created,
            &$updated,
            &$skipped,
            &$reconcile
        ) {
            foreach ($parsed as $row) {
                $existing = $this->findExistingCustomer($row['name'], $customersByName);

                if ($existing) {
                    if ($updateMode) {
                        $existing->update($this->mapToCustomerUpdates($row, $options));
                        $updated++;
                        continue;
                    }

                    if ($skipExisting) {
                        $skipped++;
                        continue;
                    }

                    throw new \RuntimeException('Customer already exists: ' . $row['name'] . '. Re-run with --update to refresh from Excel.');
                }

                $mapped = $this->mapToCustomer($row, $options);
                $user = User::create(array_merge($mapped, [
                    'email' => null,
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'login_code' => User::generateLoginCode(),
                    'sql_customer_code' => null,
                ]));
                $customersByName->put(self::normalizeMatchName($user->name), $user);
                $created++;
            }

            if ($updateMode && $pruneStale) {
                $reconcile = $this->reconcileStaleCustomers($parsed, $options, $customersByName);
            }

            if ($updateMode) {
                $this->queueActiveAutoCountSyncForParsed($parsed, $customersByName);
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'removed' => $reconcile['removed'],
            'deactivated' => $reconcile['deactivated'] ?? [],
            'skipped_delete' => $reconcile['skipped'] ?? [],
        ];
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return array{created:int,updated:int,removed:list<string>,skipped:list<string>,skipped_delete:list<string>}
     */
    public function previewSync(
        array $parsed,
        array $options,
        bool $updateMode,
        bool $skipExisting,
        bool $pruneStale
    ): array {
        $customersByName = $this->indexCustomersByName();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($parsed as $row) {
            $existing = $this->findExistingCustomer($row['name'], $customersByName);

            if ($existing) {
                if ($updateMode) {
                    $updated++;
                    continue;
                }

                $skipped++;
                continue;
            }

            $created++;
        }

        $removed = [];
        $deactivated = [];
        $skippedDelete = [];
        if ($updateMode && $pruneStale) {
            foreach ($this->findStaleCustomers($parsed, $options, $customersByName) as $user) {
                if (!$this->canDeleteCustomer($user) || trim((string) $user->sql_customer_code) !== '') {
                    $deactivated[] = $user->name;
                    continue;
                }

                $removed[] = $user->name;
            }
        }

        return compact('created', 'updated', 'skipped') + [
            'removed' => $removed,
            'deactivated' => $deactivated,
            'skipped_delete' => $skippedDelete,
        ];
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return array{created:int,removed:list<string>,skipped:list<string>}
     */
    protected function createCustomers(array $parsed, array $options, string $password): array
    {
        $created = 0;

        DB::transaction(function () use ($parsed, $options, $password, &$created) {
            foreach ($parsed as $row) {
                $existing = User::query()
                    ->whereRaw('LOWER(name) = ?', [self::normalizeMatchName($row['name'])])
                    ->exists();

                if ($existing) {
                    throw new \RuntimeException('Customer already exists: ' . $row['name'] . '. Re-run with --update to refresh from Excel.');
                }

                $mapped = $this->mapToCustomer($row, $options);
                User::create(array_merge($mapped, [
                    'email' => null,
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'login_code' => User::generateLoginCode(),
                    'sql_customer_code' => null,
                ]));
                $created++;
            }
        });

        return [
            'created' => $created,
            'updated' => 0,
            'skipped' => 0,
            'removed' => [],
            'skipped_delete' => [],
        ];
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return array{removed:list<string>,deactivated:list<string>,skipped:list<string>}
     */
    protected function reconcileStaleCustomers(array $parsed, array $options, $customersByName): array
    {
        $removed = [];
        $deactivated = [];
        $skipped = [];

        foreach ($this->findStaleCustomers($parsed, $options, $customersByName) as $user) {
            $this->migrateCustomerCodeBeforeDelete($user, $parsed, $customersByName);

            if (!$this->canDeleteCustomer($user) || trim((string) $user->sql_customer_code) !== '') {
                $this->markCustomerInactiveForAutoCount($user);
                $customersByName->forget(self::normalizeMatchName($user->name));
                $deactivated[] = $user->name;
                continue;
            }

            $this->deleteCustomer($user);
            $customersByName->forget(self::normalizeMatchName($user->name));
            $removed[] = $user->name;
        }

        return compact('removed', 'deactivated', 'skipped');
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     */
    protected function queueActiveAutoCountSyncForParsed(array $parsed, $customersByName): void
    {
        foreach ($parsed as $row) {
            $user = $this->findExistingCustomer($row['name'], $customersByName);
            if (!$user) {
                continue;
            }

            $user->update([
                'status' => User::$user_status['active'],
                'autocount_sync_status' => 'pending_sync',
                'autocount_synced_at' => null,
            ]);
        }
    }

    protected function markCustomerInactiveForAutoCount(User $user): void
    {
        $updates = [
            'status' => User::$user_status['inactive'],
        ];

        if (trim((string) $user->sql_customer_code) !== '') {
            $updates['autocount_sync_status'] = 'pending_inactive';
            $updates['autocount_synced_at'] = null;
        }

        $user->update($updates);
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return list<User>
     */
    protected function findStaleCustomers(array $parsed, array $options, $customersByName): array
    {
        $keepKeys = [];
        $legalStubKeys = [];
        $mergedAliasKeys = [];

        foreach ($parsed as $row) {
            $keepKeys[self::normalizeMatchName($row['name'])] = true;

            if (!empty($row['legal_name'])) {
                $legalKey = self::normalizeMatchName($row['legal_name']);
                $customerKey = self::normalizeMatchName($row['name']);
                if ($legalKey !== $customerKey) {
                    $legalStubKeys[$legalKey] = true;
                }
            }

            foreach ($row['extra_aliases'] ?? [] as $alias) {
                $aliasKey = self::normalizeMatchName($alias);
                if (!isset($keepKeys[$aliasKey])) {
                    $mergedAliasKeys[$aliasKey] = true;
                }
            }
        }

        $prefix = trim((string) $options['remark_prefix']);
        $stale = [];

        foreach (User::query()->get() as $user) {
            $key = self::normalizeMatchName($user->name);
            if (isset($keepKeys[$key])) {
                continue;
            }

            $isLegalStub = isset($legalStubKeys[$key]);
            $isMergedAlias = isset($mergedAliasKeys[$key]);
            $isStaleImport = $prefix !== ''
                && str_starts_with((string) $user->remark, $prefix);

            if ($isLegalStub || $isMergedAlias || $isStaleImport) {
                $stale[] = $user;
            }
        }

        return $stale;
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     */
    protected function migrateCustomerCodeBeforeDelete(User $user, array $parsed, $customersByName): void
    {
        $accNo = trim((string) $user->sql_customer_code);
        if ($accNo === '') {
            return;
        }

        $recipient = $this->findAccNoMigrationTarget($user, $parsed, $customersByName);
        if (!$recipient || trim((string) $recipient->sql_customer_code) !== '') {
            return;
        }

        $recipient->update(['sql_customer_code' => $accNo]);
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     */
    protected function findAccNoMigrationTarget(User $user, array $parsed, $customersByName): ?User
    {
        $userKey = self::normalizeMatchName($user->name);
        $userPhone = self::normalizeMatchName(trim((string) $user->attn_contact));

        if ($userPhone !== '') {
            foreach ($parsed as $row) {
                $target = $this->findExistingCustomer($row['name'], $customersByName);
                if (!$target || trim((string) $target->sql_customer_code) !== '') {
                    continue;
                }

                $phones = array_values(array_filter(array_map([$this, 'normalizePhone'], $row['phones'])));
                foreach ($phones as $phone) {
                    if (self::normalizeMatchName($phone) === $userPhone) {
                        return $target;
                    }
                }
            }
        }

        foreach ($parsed as $row) {
            $keepKey = self::normalizeMatchName($row['name']);
            if ($keepKey === $userKey) {
                continue;
            }

            $aliasKeys = array_map(
                [self::class, 'normalizeMatchName'],
                array_merge([$row['name']], $row['extra_aliases'] ?? [])
            );

            if (!in_array($userKey, $aliasKeys, true)) {
                continue;
            }

            $target = $this->findExistingCustomer($row['name'], $customersByName);
            if ($target && trim((string) $target->sql_customer_code) === '') {
                return $target;
            }
        }

        foreach ($parsed as $row) {
            if (empty($row['legal_name'])) {
                continue;
            }

            if (self::normalizeMatchName($row['legal_name']) !== $userKey) {
                continue;
            }

            $outlet = $this->findExistingCustomer($row['name'], $customersByName);
            if ($outlet && trim((string) $outlet->sql_customer_code) === '') {
                return $outlet;
            }
        }

        return null;
    }

    protected function canDeleteCustomer(User $user): bool
    {
        return !Order::query()->where('user_id', $user->id)->exists();
    }

    protected function deleteCustomer(User $user): void
    {
        ProductVisibility::query()->where('user_id', $user->id)->delete();
        DB::table('customer_drivers')->where('user_id', $user->id)->delete();
        DB::table('carts')->where('user_id', $user->id)->delete();
        DB::table('customer_credit_logs')->where('user_id', $user->id)->delete();
        $user->delete();
    }

    private function cell(array $row, int $index): string
    {
        $value = $row[$index] ?? '';

        return trim((string) $value);
    }
}
