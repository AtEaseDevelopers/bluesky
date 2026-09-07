<?php

namespace App\Console\Commands;

use App\Services\BlueskyDebtorsImportService;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportBlueskyDebtors extends Command
{
    protected $signature = 'customers:import-bluesky-debtors
                            {file : Path to BLUESKY DEBTORS LIST xlsx}
                            {--dry-run : Parse and preview without writing}
                            {--update : Update existing customers matched by name; create rows not found}
                            {--skip-existing : Skip customers whose name already exists (create-only)}
                            {--category=restaurant : Customer category slug from customer_categories}
                            {--customer-type=credit : cod or credit}
                            {--payment-term-days=30 : Credit payment term in days}
                            {--password=ecommerce123 : Default login password for newly created customers}
                            {--remark-prefix=Imported from Bluesky debtors list : Prefix for customer remark}';

    protected $description = 'Import or update customers from the Bluesky debtors Excel file (name, address, telephone).';

    public function handle(BlueskyDebtorsImportService $importService): int
    {
        $path = $this->argument('file');

        if (!is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return 1;
        }

        if ($this->option('update') && $this->option('skip-existing')) {
            $this->error('Use either --update or --skip-existing, not both.');

            return 1;
        }

        $customerType = strtolower((string) $this->option('customer-type'));
        if (!in_array($customerType, ['cod', 'credit'], true)) {
            $this->error('Invalid --customer-type. Use cod or credit.');

            return 1;
        }

        $category = trim((string) $this->option('category'));
        if ($category === '') {
            $this->error('Category cannot be empty.');

            return 1;
        }

        if (!DB::table('customer_categories')->where('category', $category)->exists()) {
            $available = DB::table('customer_categories')->orderBy('category')->pluck('category')->implode(', ');
            $this->error("Category \"{$category}\" not found. Available: {$available}");

            return 1;
        }

        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $parsed = $importService->parseSheetRows($rows);

        if ($parsed === []) {
            $this->error('No customer rows found. Expected columns: NAME, ADDRESS, TELEPHONE NO.');

            return 1;
        }

        $this->info('Parsed ' . count($parsed) . ' customer row(s).');

        $options = [
            'category' => $category,
            'customer_type' => $customerType,
            'payment_term_days' => (int) $this->option('payment-term-days'),
            'remark_prefix' => trim((string) $this->option('remark-prefix')),
        ];

        $updateMode = (bool) $this->option('update');
        $customersByName = $updateMode || $this->option('dry-run')
            ? $importService->indexCustomersByName()
            : collect();

        $wouldUpdate = 0;
        $wouldCreate = 0;
        $wouldSkip = 0;
        $previewRows = [];

        foreach ($parsed as $row) {
            $existing = $importService->findExistingCustomer($row['name'], $customersByName);
            $mapped = $importService->mapToCustomer($row, $options);

            if ($existing) {
                if ($updateMode || ($this->option('dry-run') && !$this->option('skip-existing'))) {
                    $wouldUpdate++;
                    if (count($previewRows) < 20) {
                        $previewRows[] = [
                            'update',
                            $row['name'],
                            $existing->sql_customer_code ?: '-',
                            $mapped['attn_contact'],
                            $mapped['billing_address'],
                        ];
                    }
                    continue;
                }

                $wouldSkip++;
                continue;
            }

            $wouldCreate++;
            if (count($previewRows) < 20 && !$this->option('skip-existing')) {
                $previewRows[] = [
                    'create',
                    $row['name'],
                    '-',
                    $mapped['attn_contact'],
                    $mapped['billing_address'],
                ];
            }
        }

        if ($this->option('dry-run')) {
            if ($previewRows !== []) {
                $this->table(
                    ['Action', 'Name', 'AccNo', 'Phone', 'Address'],
                    $previewRows
                );
            }

            if ($updateMode) {
                $this->line("Would update: {$wouldUpdate}, create: {$wouldCreate}");
            } else {
                $this->line("Would create: {$wouldCreate}, skip existing: {$wouldSkip}");
            }

            $missingPhone = 0;
            $placeholderPostcode = 0;
            foreach ($parsed as $row) {
                $mapped = $importService->mapToCustomer($row, $options);
                if ($mapped['attn_contact'] === '') {
                    $missingPhone++;
                }
                if ($mapped['billing_postcode'] === '00000') {
                    $placeholderPostcode++;
                }
            }

            $this->line("Rows without phone: {$missingPhone}");
            $this->line("Rows with placeholder postcode 00000: {$placeholderPostcode}");
            $this->warn('Dry run only — no database changes made.');

            return 0;
        }

        $password = (string) $this->option('password');
        $skipExisting = (bool) $this->option('skip-existing');
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($importService, $parsed, $options, $password, $skipExisting, $updateMode, $customersByName, &$created, &$updated, &$skipped) {
            foreach ($parsed as $row) {
                $existing = $importService->findExistingCustomer($row['name'], $customersByName);

                if ($existing) {
                    if ($updateMode) {
                        $existing->update($importService->mapToCustomerUpdates($row, $options));
                        $updated++;
                        continue;
                    }

                    if ($skipExisting) {
                        $skipped++;
                        continue;
                    }

                    throw new \RuntimeException('Customer already exists: ' . $row['name'] . '. Re-run with --update to refresh from Excel.');
                }

                $mapped = $importService->mapToCustomer($row, $options);
                User::create(array_merge($mapped, [
                    'email' => null,
                    'password' => Hash::make($password),
                    'login_code' => User::generateLoginCode(),
                    'sql_customer_code' => null,
                ]));
                $created++;
            }
        });

        if ($updateMode) {
            $this->info("Import complete. Updated: {$updated}, created: {$created}.");
        } else {
            $this->info("Import complete. Created: {$created}, skipped: {$skipped}.");
        }

        if ($updated > 0) {
            $this->line('Updated customers are queued as pending_sync for AutoCount.');
            $this->line('In Admin → Customers, select them and click Sync to AutoCount, or let the AutoCount plugin pull them.');
        }

        if ($created > 0) {
            $this->line("Default login password for new customers: {$password}");
        }

        return 0;
    }
}
