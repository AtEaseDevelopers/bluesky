<?php

namespace App\Console\Commands;

use App\Services\CustomerBundleService;
use Illuminate\Console\Command;

class CustomerBundleCommand extends Command
{
    protected $signature = 'customers:bundle
                            {action : export or import}
                            {path? : Output/input JSON path}
                            {--ids= : Comma-separated user IDs for export}
                            {--skip-existing : Import only; do not update existing customers}';

    protected $description = 'Export or import selected customers as a JSON bundle (for local → live migration).';

    public function handle(CustomerBundleService $service): int
    {
        $action = strtolower((string) $this->argument('action'));

        if ($action === 'export') {
            return $this->export($service);
        }

        if ($action === 'import') {
            return $this->import($service);
        }

        $this->error('Action must be "export" or "import".');

        return 1;
    }

    protected function export(CustomerBundleService $service): int
    {
        $ids = collect(explode(',', (string) $this->option('ids')))
            ->map(static fn ($id) => trim($id))
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            $this->error('Provide --ids=1566,1567,... for export.');

            return 1;
        }

        $path = $this->argument('path') ?: storage_path('app/customer-import-bundle.json');
        $bundle = $service->export($ids);

        if ($bundle['customers'] === []) {
            $this->error('No customers found for the given IDs.');

            return 1;
        }

        file_put_contents($path, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Exported ' . count($bundle['customers']) . ' customer(s) to ' . $path);
        $this->line('AccNo is not included — live AutoCount will assign customer codes on sync.');
        foreach ($bundle['customers'] as $customer) {
            $name = data_get($customer, 'attributes.name');
            $skuCount = count($customer['product_skus'] ?? []);
            $this->line("  - {$name} | {$skuCount} product(s)");
        }

        return 0;
    }

    protected function import(CustomerBundleService $service): int
    {
        $path = $this->argument('path') ?: storage_path('app/customer-import-bundle.json');

        if (!is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return 1;
        }

        $bundle = json_decode(file_get_contents($path), true);
        if (!is_array($bundle)) {
            $this->error('Invalid JSON bundle file.');

            return 1;
        }

        $result = $service->import($bundle, !$this->option('skip-existing'));

        $this->info("Created: {$result['created']}, updated: {$result['updated']}, skipped: {$result['skipped']}");

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? 0 : 1;
    }
}
