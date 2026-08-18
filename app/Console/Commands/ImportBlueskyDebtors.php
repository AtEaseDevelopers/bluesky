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
                            {--skip-existing : Skip customers whose name already exists}
                            {--category=restaurant : Customer category slug from customer_categories}
                            {--customer-type=credit : cod or credit}
                            {--payment-term-days=30 : Credit payment term in days}
                            {--password=ecommerce123 : Default login password}
                            {--remark-prefix=Imported from Bluesky debtors list : Prefix for customer remark}';

    protected $description = 'Import customers from the Bluesky debtors Excel file (name, address, telephone).';

    public function handle(BlueskyDebtorsImportService $importService): int
    {
        $path = $this->argument('file');

        if (!is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

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

        $mapped = array_map(
            fn (array $row) => $importService->mapToCustomer($row, $options),
            $parsed
        );

        if ($this->option('dry-run')) {
            $this->table(
                ['Name', 'Phone', 'Address', 'Postcode', 'State'],
                array_map(fn (array $row) => [
                    $row['name'],
                    $row['attn_contact'],
                    $row['billing_address'],
                    $row['billing_postcode'],
                    $row['billing_state'],
                ], array_slice($mapped, 0, 20))
            );

            if (count($mapped) > 20) {
                $this->line('... and ' . (count($mapped) - 20) . ' more row(s).');
            }

            $missingPhone = count(array_filter($mapped, fn ($row) => $row['attn_contact'] === ''));
            $placeholderPostcode = count(array_filter($mapped, fn ($row) => $row['billing_postcode'] === '00000'));
            $this->line("Rows without phone: {$missingPhone}");
            $this->line("Rows with placeholder postcode 00000: {$placeholderPostcode}");
            $this->warn('Dry run only — no database changes made.');

            return 0;
        }

        $password = (string) $this->option('password');
        $skipExisting = (bool) $this->option('skip-existing');
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($mapped, $password, $skipExisting, &$created, &$skipped) {
            foreach ($mapped as $row) {
                $existing = User::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($row['name'])])
                    ->first();

                if ($existing) {
                    if ($skipExisting) {
                        $skipped++;
                        continue;
                    }

                    throw new \RuntimeException('Customer already exists: ' . $row['name']);
                }

                User::create(array_merge($row, [
                    'email' => null,
                    'password' => Hash::make($password),
                    'login_code' => User::generateLoginCode(),
                    'sql_customer_code' => null,
                ]));

                $created++;
            }
        });

        $this->info("Import complete. Created: {$created}, skipped: {$skipped}.");
        $this->line("Default login password: {$password}");

        return 0;
    }
}
