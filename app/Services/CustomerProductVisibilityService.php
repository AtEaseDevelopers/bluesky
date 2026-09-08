<?php

namespace App\Services;

use App\CustomerCategory;
use App\CustomerCategoryProduct;
use App\ProductVisibility;
use App\User;

class CustomerProductVisibilityService
{
    public function replaceFromCategory(User $customer, ?string $category): void
    {
        ProductVisibility::where('user_id', $customer->id)->delete();

        $category = trim((string) $category);
        if ($category === '') {
            return;
        }

        $categoryRecord = CustomerCategory::where('category', $category)->first()
            ?? CustomerCategory::whereRaw('LOWER(category) = ?', [strtolower($category)])->first();

        if (!$categoryRecord) {
            return;
        }

        $productIds = CustomerCategoryProduct::where('customer_category_id', $categoryRecord->id)
            ->pluck('product_id')
            ->all();

        $this->replaceFromProductIds($customer, $productIds);
    }

    public function replaceFromProductIds(User $customer, array $productIds): void
    {
        ProductVisibility::where('user_id', $customer->id)->delete();

        foreach (collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique() as $productId) {
            ProductVisibility::create([
                'user_id' => $customer->id,
                'product_id' => $productId,
            ]);
        }
    }
}
