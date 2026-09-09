<?php

namespace App\Console\Commands;

use App\Services\BlueskyDebtorsImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportBlueskyDebtors extends Command
{
    protected $signature = 'customers:import-bluesky-debtors
                            {file : Path to BLUESKY DEBTORS LIST xlsx}
                            {--dry-run : Parse and preview without writing}
                            {--list : Export parsed preview spreadsheet without importing}
                            {--checklist : Export AutoCount cleanup checklist (OMS vs Excel) without importing}
                            {--checklist-output= : Output path for --checklist (default: storage/app/bluesky-autocount-checklist.xlsx)}
                            {--list-output= : Output path for --list (default: storage/app/bluesky-debtors-preview.xlsx)}
                            {--list-csv : With --list, write CSV instead of XLSX}
                            {--multi-only : With --list, only rows where the same company has multiple accounts}
                            {--update : Update existing customers matched by name; create rows not found; remove stale import duplicates}
                            {--no-prune : With --update, keep old import customers that are not in the revised list}
                            {--skip-existing : Skip rows that already exist in OMS (e.g. synced from AutoCount) matched by name, company-outlet, or phone}
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

        if ($this->option('list')) {
            return $this->writePreviewList($importService, $parsed, $options);
        }

        if ($this->option('checklist')) {
            return $this->writeReconciliationChecklist($importService, $parsed, $options);
        }

        $updateMode = (bool) $this->option('update');
        $skipExisting = (bool) $this->option('skip-existing');
        $pruneStale = $updateMode && !$this->option('no-prune');

        if ($this->option('dry-run')) {
            $preview = $importService->previewSync($parsed, $options, $updateMode, $skipExisting, $pruneStale);

            if ($updateMode) {
                $this->line(sprintf(
                    'Would update: %d, create: %d, deactivate: %d, remove: %d',
                    $preview['updated'],
                    $preview['created'],
                    count($preview['deactivated'] ?? []),
                    count($preview['removed'])
                ));
            } else {
                $this->line("Would create: {$preview['created']}, skip existing: {$preview['skipped']}");
                if ($skipExisting) {
                    $this->comment('Skipped rows match existing OMS customers by outlet name, company-outlet name, or phone (AutoCount sync).');
                }
            }

            if (($preview['deactivated'] ?? []) !== []) {
                $this->newLine();
                $this->comment('Would mark inactive in OMS and queue inactive in AutoCount:');
                foreach (array_slice($preview['deactivated'], 0, 30) as $name) {
                    $this->line('  - ' . $name);
                }
                if (count($preview['deactivated']) > 30) {
                    $this->line('  ... and ' . (count($preview['deactivated']) - 30) . ' more');
                }
            }

            if ($preview['removed'] !== []) {
                $this->newLine();
                $this->comment('Would remove stale customer account(s):');
                foreach (array_slice($preview['removed'], 0, 30) as $name) {
                    $this->line('  - ' . $name);
                }
                if (count($preview['removed']) > 30) {
                    $this->line('  ... and ' . (count($preview['removed']) - 30) . ' more');
                }
            }

            if ($preview['skipped_delete'] !== []) {
                $this->newLine();
                $this->warn('Skipped actions:');
                foreach ($preview['skipped_delete'] as $message) {
                    $this->line('  - ' . $message);
                }
            }

            if ($updateMode) {
                $this->line('Active customers in Excel will be queued as pending_sync for AutoCount.');
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
        $result = $importService->syncCustomers(
            $parsed,
            $options,
            $password,
            $updateMode,
            $skipExisting,
            $pruneStale
        );

        if ($updateMode) {
            $this->info(sprintf(
                'Import complete. Updated: %d, created: %d, deactivated: %d, removed: %d.',
                $result['updated'],
                $result['created'],
                count($result['deactivated'] ?? []),
                count($result['removed'])
            ));
        } else {
            $this->info("Import complete. Created: {$result['created']}, skipped: {$result['skipped']}.");
            if ($skipExisting && $result['skipped'] > 0) {
                $this->comment('Skipped rows already exist in OMS (likely synced from AutoCount).');
            }
        }

        if (($result['deactivated'] ?? []) !== []) {
            $this->newLine();
            $this->comment('Marked inactive and queued for AutoCount deactivation:');
            foreach (array_slice($result['deactivated'], 0, 30) as $name) {
                $this->line('  - ' . $name);
            }
            if (count($result['deactivated']) > 30) {
                $this->line('  ... and ' . (count($result['deactivated']) - 30) . ' more');
            }
        }

        if ($result['removed'] !== []) {
            $this->newLine();
            $this->comment('Removed stale customer account(s):');
            foreach (array_slice($result['removed'], 0, 30) as $name) {
                $this->line('  - ' . $name);
            }
            if (count($result['removed']) > 30) {
                $this->line('  ... and ' . (count($result['removed']) - 30) . ' more');
            }
        }

        if ($result['skipped_delete'] !== []) {
            $this->newLine();
            $this->warn('Could not remove (protected by existing orders):');
            foreach ($result['skipped_delete'] as $message) {
                $this->line('  - ' . $message);
            }
        }

        if ($result['updated'] > 0 || $result['created'] > 0) {
            $this->line('Active customers in Excel are queued as pending_sync for AutoCount (active).');
        }

        if (($result['deactivated'] ?? []) !== []) {
            $this->line('Inactive customers are queued as pending_inactive — AutoCount plugin should call GET /api/customers/inactive-pending.');
        }

        if ($result['updated'] > 0 || $result['created'] > 0 || ($result['deactivated'] ?? []) !== []) {
            $this->line('Let the AutoCount plugin pull pending create/update/inactive queues.');
        }

        if ($result['created'] > 0) {
            $this->line("Default login password for new customers: {$password}");
        }

        return 0;
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     */
    protected function writePreviewList(BlueskyDebtorsImportService $importService, array $parsed, array $options): int
    {
        $report = $importService->buildPreviewReport($parsed, $options);

        if ($this->option('multi-only')) {
            $report = array_values(array_filter(
                $report,
                fn (array $row) => (int) $row['accounts_for_company'] > 1
            ));
        }

        $outputPath = trim((string) $this->option('list-output'));
        if ($outputPath === '') {
            $outputPath = storage_path(
                $this->option('list-csv')
                    ? 'app/bluesky-debtors-preview.csv'
                    : 'app/bluesky-debtors-preview.xlsx'
            );
        }

        $fullReport = $importService->buildPreviewReport($parsed, $options);
        $multiAccountSummary = $importService->summarizeMultiAccountCompanies($fullReport);

        if (str_ends_with(strtolower($outputPath), '.csv') || $this->option('list-csv')) {
            $importService->writePreviewCsv($report, $outputPath);
        } else {
            $importService->writePreviewXlsx($report, $multiAccountSummary, $outputPath);
        }

        $this->info('Preview file written to: ' . $outputPath);
        $this->line('Rows in file: ' . count($report));
        $this->line('Companies with multiple accounts: ' . count($multiAccountSummary));

        if ($multiAccountSummary !== []) {
            $this->newLine();
            $this->comment('Companies split into multiple customer accounts:');
            $this->table(
                ['Company', 'Accounts', 'Customer Names'],
                array_map(
                    fn (array $group) => [$group['company_name'], $group['accounts'], $group['customer_names']],
                    array_slice($multiAccountSummary, 0, 40)
                )
            );

            if (count($multiAccountSummary) > 40) {
                $this->line('... and ' . (count($multiAccountSummary) - 40) . ' more company group(s) in the CSV.');
            }
        } else {
            $this->info('No companies were split into multiple accounts.');
        }

        if ($this->option('multi-only')) {
            $this->comment('File contains only rows flagged with Multiple Accounts = Yes.');
        } else {
            $this->comment('Open sheet "Parsed Customers" and filter Multiple Accounts = Yes to review split companies.');
        }

        return 0;
    }

    /**
     * @param  list<array{name:string,address_lines:list<string>,phones:list<string>,legal_name?:string,extra_aliases?:list<string>}>  $parsed
     */
    protected function writeReconciliationChecklist(BlueskyDebtorsImportService $importService, array $parsed, array $options): int
    {
        $checklist = $importService->buildReconciliationChecklist($parsed, $options);

        $outputPath = trim((string) $this->option('checklist-output'));
        if ($outputPath === '') {
            $outputPath = storage_path('app/bluesky-autocount-checklist.xlsx');
        }

        $importService->writeReconciliationChecklistXlsx($checklist, $outputPath);

        $this->info('AutoCount cleanup checklist written to: ' . $outputPath);
        $this->line('Total rows: ' . count($checklist));

        $summary = $importService->summarizeReconciliationChecklist($checklist);
        if ($summary !== []) {
            $this->newLine();
            $this->table(['Recommended Action', 'Count'], collect($summary)->map(
                fn (int $count, string $action) => [$action, $count]
            )->values()->all());
        }

        $this->newLine();
        $this->comment('Open the "Deactivate" sheet first — work through AutoCount Debtor Maintenance row by row.');
        $this->comment('Use "Done in AutoCount" and "Notes" columns to track progress.');

        return 0;
    }
}
