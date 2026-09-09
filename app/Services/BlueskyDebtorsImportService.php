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
            'name' => $mapped['name'],
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
        // OMS uses "COMPANY - OUTLET - 中文"; Excel list often omits dashes — treat as equivalent.
        $name = preg_replace('/\s*-\s*/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', trim($name));

        return mb_strtolower($name);
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}|string  $rowOrName
     * @param  \Illuminate\Support\Collection<string, \App\User>  $customersByName
     */
    public function findExistingCustomer(array|string $rowOrName, $customersByName): ?\App\User
    {
        if (is_string($rowOrName)) {
            $key = self::normalizeMatchName($rowOrName);
            if ($customersByName->has($key)) {
                return $customersByName->get($key);
            }

            return null;
        }

        foreach ($this->customerMatchKeys($rowOrName) as $key) {
            if ($customersByName->has($key)) {
                return $customersByName->get($key);
            }
        }

        return $this->findExistingCustomerByPhone($rowOrName);
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}  $row
     * @return list<string>
     */
    public function customerMatchKeys(array $row): array
    {
        $outletKey = self::normalizeMatchName($row['name']);
        $formattedKey = self::normalizeMatchName($this->formatCustomerName($row));
        $keys = [];

        if (!empty($row['legal_name'])) {
            $legalKey = self::normalizeMatchName($row['legal_name']);
            if ($legalKey !== $outletKey) {
                $keys[] = $legalKey . '|' . $outletKey;
            }
        }

        $keys[] = $formattedKey;
        $keys[] = $outletKey;

        foreach ($this->displayAliases($row) as $alias) {
            $keys[] = self::normalizeMatchName($alias);
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}  $row
     * @return list<string>
     */
    public function displayAliases(array $row): array
    {
        $seen = [
            self::normalizeMatchName($row['name']),
            self::normalizeMatchName($row['legal_name'] ?? ''),
        ];
        $aliases = [];

        foreach ($row['extra_aliases'] ?? [] as $alias) {
            $alias = preg_replace('/\s+/u', ' ', trim((string) $alias));
            if ($alias === '') {
                continue;
            }

            $key = self::normalizeMatchName($alias);
            if (in_array($key, $seen, true)) {
                continue;
            }

            $seen[] = $key;
            $aliases[] = $alias;
        }

        return $aliases;
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}  $row
     */
    public function formatCustomerName(array $row): string
    {
        $outlet = preg_replace('/\s+/u', ' ', trim($row['name']));
        $legal = preg_replace('/\s+/u', ' ', trim($row['legal_name'] ?? ''));
        $parts = [];

        if ($legal !== '' && self::normalizeMatchName($legal) !== self::normalizeMatchName($outlet)) {
            $parts[] = $legal;
            $parts[] = $outlet;
        } else {
            $parts[] = $outlet;
        }

        foreach ($this->displayAliases($row) as $alias) {
            $parts[] = $alias;
        }

        return mb_substr(implode(' - ', $parts), 0, 100);
    }

    /**
     * @return \Illuminate\Support\Collection<string, \App\User>
     */
    public function indexCustomersByName(): \Illuminate\Support\Collection
    {
        $indexed = collect();

        foreach (User::query()->get(['id', 'name', 'sql_customer_code', 'attn_contact']) as $user) {
            $this->indexCustomerByName($indexed, $user);
        }

        return $indexed;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \App\User>  $indexed
     */
    protected function indexCustomerByName(\Illuminate\Support\Collection $indexed, User $user): void
    {
        $keys = [self::normalizeMatchName($user->name)];

        if (str_contains($user->name, ' - ')) {
            $segments = array_values(array_filter(array_map('trim', explode(' - ', $user->name))));
            foreach ($segments as $segment) {
                $keys[] = self::normalizeMatchName($segment);
            }
            if (count($segments) >= 2) {
                $keys[] = self::normalizeMatchName($segments[0]) . '|' . self::normalizeMatchName($segments[1]);
            }
        }

        foreach (array_unique(array_filter($keys)) as $key) {
            if (!$indexed->has($key)) {
                $indexed->put($key, $user);
            }
        }
    }

    /**
     * @param  array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}  $row
     */
    protected function findExistingCustomerByPhone(array $row): ?User
    {
        $phoneKeys = array_values(array_unique(array_filter(array_map(
            [$this, 'normalizePhoneDigits'],
            $row['phones'] ?? []
        ))));

        if ($phoneKeys === []) {
            return null;
        }

        $users = User::query()
            ->whereNotNull('sql_customer_code')
            ->where('sql_customer_code', '!=', '')
            ->get(['id', 'name', 'sql_customer_code', 'attn_contact']);

        foreach ($users as $user) {
            $userPhoneKey = $this->normalizePhoneDigits((string) $user->attn_contact);
            if ($userPhoneKey !== '' && in_array($userPhoneKey, $phoneKeys, true)) {
                return $user;
            }
        }

        return null;
    }

    protected function normalizePhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '60') && strlen($digits) > 9) {
            $digits = substr($digits, 2);
        }

        return ltrim($digits, '0');
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

        $customerType = $options['customer_type'];
        $paymentMethods = $customerType === 'credit'
            ? [\App\User::$payment_method['term']]
            : [\App\User::$payment_method['cod']];

        return [
            'name' => $this->formatCustomerName($row),
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
            'remark' => '',
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
                'customer_name' => $this->formatCustomerName($row),
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
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return list<array<string, string>>
     */
    public function buildReconciliationChecklist(array $parsed, array $options): array
    {
        $customersByName = $this->indexCustomersByName();
        $keepKeys = $this->buildExcelKeepKeys($parsed);
        $staleUsers = collect($this->findStaleCustomers($parsed, $options, $customersByName))
            ->keyBy(fn (User $user) => $user->id);
        $orderUserIds = Order::query()->distinct()->pluck('user_id')->flip();
        $prefix = trim((string) $options['remark_prefix']);
        $rows = [];

        foreach (User::query()->orderBy('name')->get() as $user) {
            $key = self::normalizeMatchName($user->name);
            $inExcel = isset($keepKeys[$key]);
            $isStale = $staleUsers->has($user->id);
            $hasAccNo = trim((string) $user->sql_customer_code) !== '';
            $isImport = $prefix !== '' && str_starts_with((string) $user->remark, $prefix);

            if (!$hasAccNo && !$inExcel && !$isStale && !$isImport) {
                continue;
            }

            [$action, $reason] = $this->reconciliationRecommendation(
                $user,
                $inExcel,
                $isStale,
                $hasAccNo,
                $parsed,
                $options,
                $customersByName
            );

            $rows[] = $this->formatReconciliationRow(
                $user,
                $inExcel,
                $hasAccNo,
                $orderUserIds->has($user->id),
                $action,
                $reason
            );
        }

        foreach ($parsed as $row) {
            if ($this->findExistingCustomer($row, $customersByName)) {
                continue;
            }

            $rows[] = [
                'acc_no' => '',
                'oms_name' => $row['name'],
                'phone' => $this->normalizePhone($row['phones'][0] ?? ''),
                'in_excel' => 'Yes',
                'oms_status' => '',
                'sync_status' => '',
                'has_orders' => 'No',
                'recommended_action' => 'Create in OMS',
                'reason' => 'On Excel list but not found in OMS yet',
                'done_in_autocount' => '',
                'notes' => '',
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $actionOrder = [
                'Deactivate in AutoCount' => 1,
                'Review' => 2,
                'Keep Active' => 3,
                'Create in OMS' => 4,
                'Leave alone' => 5,
            ];

            $actionCompare = ($actionOrder[$a['recommended_action']] ?? 99)
                <=> ($actionOrder[$b['recommended_action']] ?? 99);
            if ($actionCompare !== 0) {
                return $actionCompare;
            }

            return strcasecmp($a['oms_name'], $b['oms_name']);
        });

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $checklist
     */
    public function summarizeReconciliationChecklist(array $checklist): array
    {
        $counts = [];

        foreach ($checklist as $row) {
            $action = $row['recommended_action'];
            $counts[$action] = ($counts[$action] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  list<array<string, string>>  $checklist
     */
    public function writeReconciliationChecklistXlsx(array $checklist, string $path): void
    {
        $headers = [
            'AccNo',
            'OMS Name',
            'Phone',
            'In Excel',
            'OMS Status',
            'Sync Status',
            'Has Orders',
            'Recommended Action',
            'Reason',
            'Done in AutoCount',
            'Notes',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->fromArray([
            ['Recommended Action', 'Count'],
        ]);

        $summaryRow = 2;
        foreach ($this->summarizeReconciliationChecklist($checklist) as $action => $count) {
            $summarySheet->fromArray([[$action, $count]], null, 'A' . $summaryRow);
            $summaryRow++;
        }

        $summarySheet->fromArray([
            ['', ''],
            ['Total rows', count($checklist)],
            ['', ''],
            ['How to use', 'Work through "Deactivate" and "Review" sheets in AutoCount Debtor Maintenance.'],
            ['', 'Set unwanted debtors to Inactive (do not delete if they have invoices/orders).'],
            ['', 'Tick "Done in AutoCount" when finished.'],
        ], null, 'A' . ($summaryRow + 1));

        foreach (range('A', 'B') as $column) {
            $summarySheet->getColumnDimension($column)->setAutoSize(true);
        }

        $this->appendReconciliationSheet($spreadsheet, 'Checklist', $headers, $checklist);
        $this->appendReconciliationSheet(
            $spreadsheet,
            'Deactivate',
            $headers,
            array_values(array_filter(
                $checklist,
                fn (array $row) => $row['recommended_action'] === 'Deactivate in AutoCount'
            ))
        );
        $this->appendReconciliationSheet(
            $spreadsheet,
            'Keep Active',
            $headers,
            array_values(array_filter(
                $checklist,
                fn (array $row) => $row['recommended_action'] === 'Keep Active'
            ))
        );
        $this->appendReconciliationSheet(
            $spreadsheet,
            'Review',
            $headers,
            array_values(array_filter(
                $checklist,
                fn (array $row) => $row['recommended_action'] === 'Review'
            ))
        );

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return array<string, true>
     */
    protected function buildExcelKeepKeys(array $parsed): array
    {
        $keepKeys = [];

        foreach ($parsed as $row) {
            $keepKeys[self::normalizeMatchName($this->formatCustomerName($row))] = true;
            $keepKeys[self::normalizeMatchName($row['name'])] = true;
        }

        return $keepKeys;
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     * @return array{0:string,1:string}
     */
    protected function reconciliationRecommendation(
        User $user,
        bool $inExcel,
        bool $isStale,
        bool $hasAccNo,
        array $parsed,
        array $options,
        $customersByName
    ): array {
        if ($inExcel) {
            return [
                'Keep Active',
                $hasAccNo
                    ? 'On Bluesky Excel list — keep active in AutoCount'
                    : 'On Bluesky Excel list — sync/create in AutoCount when ready',
            ];
        }

        if ($isStale) {
            return [
                'Deactivate in AutoCount',
                $this->staleCustomerReason($user, $parsed, $options),
            ];
        }

        if ($hasAccNo) {
            return [
                'Review',
                'Has AutoCount AccNo but not on Excel list — pre-existing or manual customer',
            ];
        }

        return [
            'Leave alone',
            'Bluesky import account not on Excel — no AccNo yet; confirm before changing',
        ];
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     */
    protected function staleCustomerReason(User $user, array $parsed, array $options): string
    {
        $key = self::normalizeMatchName($user->name);

        foreach ($parsed as $row) {
            if (!empty($row['legal_name']) && self::normalizeMatchName($row['legal_name']) === $key) {
                return 'Company header row merged into outlet account — deactivate duplicate company debtor';
            }

            foreach ($row['extra_aliases'] ?? [] as $alias) {
                if (self::normalizeMatchName($alias) === $key) {
                    return 'Old outlet name merged into another account — deactivate duplicate debtor';
                }
            }
        }

        $prefix = trim((string) $options['remark_prefix']);
        if ($prefix !== '' && str_starts_with((string) $user->remark, $prefix)) {
            return 'Bluesky import duplicate not on Excel list — deactivate in AutoCount';
        }

        return 'Not on Excel list — deactivate duplicate debtor';
    }

    protected function formatReconciliationRow(
        User $user,
        bool $inExcel,
        bool $hasAccNo,
        bool $hasOrders,
        string $action,
        string $reason
    ): array {
        return [
            'acc_no' => $hasAccNo ? trim((string) $user->sql_customer_code) : '',
            'oms_name' => $user->name,
            'phone' => trim((string) $user->attn_contact),
            'in_excel' => $inExcel ? 'Yes' : 'No',
            'oms_status' => (string) $user->status,
            'sync_status' => (string) ($user->autocount_sync_status ?: 'pending'),
            'has_orders' => $hasOrders ? 'Yes' : 'No',
            'recommended_action' => $action,
            'reason' => $reason,
            'done_in_autocount' => '',
            'notes' => '',
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string>>  $rows
     */
    protected function appendReconciliationSheet(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        string $title,
        array $headers,
        array $rows
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray([$headers]);

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['acc_no'],
                $row['oms_name'],
                $row['phone'],
                $row['in_excel'],
                $row['oms_status'],
                $row['sync_status'],
                $row['has_orders'],
                $row['recommended_action'],
                $row['reason'],
                $row['done_in_autocount'],
                $row['notes'],
            ]], null, 'A' . $rowIndex);
            $rowIndex++;
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
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
                $existing = $this->findExistingCustomer($row, $customersByName);

                if ($existing) {
                    if ($updateMode) {
                        $existing->update($this->mapToCustomerUpdates($row, $options));
                        $existing->refresh();
                        $this->indexCustomerByName($customersByName, $existing);
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
                $this->indexCustomerByName($customersByName, $user);
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
            $existing = $this->findExistingCustomer($row, $customersByName);

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
            $customersByName = $this->indexCustomersByName();

            foreach ($parsed as $row) {
                if ($this->findExistingCustomer($row, $customersByName)) {
                    throw new \RuntimeException('Customer already exists: ' . $this->formatCustomerName($row) . '. Re-run with --update to refresh from Excel.');
                }

                $mapped = $this->mapToCustomer($row, $options);
                $user = User::create(array_merge($mapped, [
                    'email' => null,
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'login_code' => User::generateLoginCode(),
                    'sql_customer_code' => null,
                ]));
                $this->indexCustomerByName($customersByName, $user);
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
            $user = $this->findExistingCustomer($row, $customersByName);
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
            $keepKeys[self::normalizeMatchName($this->formatCustomerName($row))] = true;
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
                $target = $this->findExistingCustomer($row, $customersByName);
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

            $target = $this->findExistingCustomer($row, $customersByName);
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

            $outlet = $this->findExistingCustomer($row, $customersByName);
            if ($outlet && trim((string) $outlet->sql_customer_code) === '') {
                return $outlet;
            }
        }

        return null;
    }

    protected function canDeleteCustomer(User $user): bool
    {
        return app(CustomerLifecycleService::class)->canDelete($user);
    }

    protected function deleteCustomer(User $user): void
    {
        app(CustomerLifecycleService::class)->delete($user);
    }

    protected function markCustomerInactiveForAutoCount(User $user): void
    {
        $user->update(app(CustomerLifecycleService::class)->buildStatusUpdates($user, 'inactive'));
    }

    private function cell(array $row, int $index): string
    {
        $value = $row[$index] ?? '';

        return trim((string) $value);
    }
}
