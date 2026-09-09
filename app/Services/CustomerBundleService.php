<?php

namespace App\Services;

use App\Product;
use App\ProductVisibility;
use App\User;
use Illuminate\Support\Facades\DB;

class CustomerBundleService
{
    /**
     * @param  array<int|string>  $userIds
     * @return array{exported_at: string, customers: list<array<string, mixed>>}
     */
    public function export(array $userIds): array
    {
        $customers = [];

        foreach (array_unique($userIds) as $userId) {
            $user = User::query()->find($userId);
            if (!$user) {
                continue;
            }

            $productSkus = ProductVisibility::query()
                ->join('products', 'products.id', '=', 'product_visibilities.product_id')
                ->where('product_visibilities.user_id', $user->id)
                ->orderBy('products.sku')
                ->pluck('products.sku')
                ->filter()
                ->values()
                ->all();

            $customers[] = [
                'local_id' => $user->id,
                'attributes' => collect($user->getAttributes())
                    ->except([
                        'id',
                        'remember_token',
                        'created_at',
                        'updated_at',
                        'email_verified_at',
                        'sql_customer_code',
                    ])
                    ->all(),
                'product_skus' => $productSkus,
            ];
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'customers' => $customers,
        ];
    }

    /**
     * @param  array{customers?: list<array<string, mixed>>}  $bundle
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(array $bundle, bool $updateExisting = true): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($bundle['customers'] ?? [] as $row) {
            if (!is_array($row)) {
                $result['skipped']++;
                continue;
            }

            try {
                $outcome = DB::transaction(function () use ($row, $updateExisting) {
                    return $this->importOne($row, $updateExisting);
                });
            } catch (\Throwable $e) {
                $name = data_get($row, 'attributes.name', '(unknown)');
                $result['errors'][] = "{$name}: {$e->getMessage()}";
                continue;
            }

            $result[$outcome]++;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function importOne(array $row, bool $updateExisting): string
    {
        $attributes = collect($row['attributes'] ?? [])
            ->only((new User())->getFillable())
            ->except(['sql_customer_code'])
            ->all();

        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Customer name is required.');
        }

        $existing = $this->findExisting($name);

        if ($existing && !$updateExisting) {
            return 'skipped';
        }

        if (!empty($attributes['registration_completed_at'])) {
            $attributes['autocount_sync_status'] = 'pending_sync';
            $attributes['autocount_synced_at'] = null;
        }

        if ($existing) {
            unset($attributes['sql_customer_code']);
            $existing->fill($attributes)->save();
            $user = $existing->fresh();
            $outcome = 'updated';
        } else {
            $attributes['sql_customer_code'] = null;
            $attributes['login_code'] = $this->uniqueLoginCode($attributes['login_code'] ?? null);
            $user = User::query()->create($attributes);
            $outcome = 'created';
        }

        $this->syncProductVisibilities($user, $row['product_skus'] ?? []);

        return $outcome;
    }

    protected function findExisting(string $name): ?User
    {
        return User::query()->where('name', $name)->first();
    }

    protected function uniqueLoginCode(?string $preferred): string
    {
        $preferred = trim((string) $preferred);
        if ($preferred !== '' && !User::query()->where('login_code', $preferred)->exists()) {
            return $preferred;
        }

        return User::generateLoginCode();
    }

    /**
     * @param  array<int, mixed>  $productSkus
     */
    protected function syncProductVisibilities(User $user, array $productSkus): void
    {
        ProductVisibility::query()->where('user_id', $user->id)->delete();

        if ($productSkus === []) {
            return;
        }

        $productIds = Product::query()
            ->whereIn('sku', $productSkus)
            ->pluck('id')
            ->all();

        foreach ($productIds as $productId) {
            ProductVisibility::query()->create([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
        }
    }
}
