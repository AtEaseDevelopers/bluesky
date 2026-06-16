<?php

namespace App\Services;

use App\Product;

class PublicOrderCartService
{
    public function sessionKey(string $token): string
    {
        return 'public_order_cart.' . $token;
    }

    public function items(string $token): array
    {
        return session($this->sessionKey($token), []);
    }

    public function count(string $token): int
    {
        return count($this->items($token));
    }

    public function add(string $token, Product $product, float $amount, ?string $remark = null): void
    {
        $items = $this->items($token);
        $sellByWeight = $product->sell_in === 'weight' || $product->show_weight;

        if (isset($items[$product->id])) {
            $existing = $items[$product->id];
            $amount += $sellByWeight
                ? (float) ($existing['weight'] ?? $existing['quantity'] ?? 0)
                : (float) ($existing['quantity'] ?? 0);
        }

        $items[$product->id] = [
            'product_id' => $product->id,
            'quantity' => $amount,
            'weight' => $sellByWeight ? $amount : null,
            'remark' => $remark,
        ];

        session([$this->sessionKey($token) => $items]);
    }

    public function update(string $token, int $productId, float $amount): void
    {
        $items = $this->items($token);
        if (!isset($items[$productId])) {
            return;
        }

        $product = Product::find($productId);
        $sellByWeight = $product && ($product->sell_in === 'weight' || $product->show_weight);

        $items[$productId]['quantity'] = $amount;
        $items[$productId]['weight'] = $sellByWeight ? $amount : null;

        session([$this->sessionKey($token) => $items]);
    }

    public function remove(string $token, int $productId): void
    {
        $items = $this->items($token);
        unset($items[$productId]);
        session([$this->sessionKey($token) => $items]);
    }

    public function clear(string $token): void
    {
        session()->forget($this->sessionKey($token));
    }

    public function subtotal(string $token): float
    {
        $total = 0;
        foreach ($this->items($token) as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                continue;
            }
            $qty = (float) ($item['quantity'] ?? 0);
            $total += Product::resolvePrice($product->id) * $qty;
        }

        return $total;
    }
}
