<?php

namespace App\Services;

use App\Order;
use App\OrderProduct;
use App\Product;
use App\ProductStock;
use App\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function stockIn(int $productId, ?float $quantity, ?float $weight, string $movementDate, ?string $remarks, ?int $adminId): StockMovement
    {
        return DB::transaction(function () use ($productId, $quantity, $weight, $movementDate, $remarks, $adminId) {
            $product = Product::findOrFail($productId);
            $stock = $this->getOrCreateStock($productId);
            $quantityBefore = (float) $stock->quantity;
            $weightBefore = (float) ($stock->weight ?? 0);

            if ($product->inventoryTracksWeight()) {
                $amount = (float) $weight;
                if ($amount <= 0) {
                    throw new \InvalidArgumentException('Weight is required for this product.');
                }

                $stock->weight = $weightBefore + $amount;
                $stock->save();

                return $this->createMovement([
                    'product_id' => $productId,
                    'movement_type' => 'stock_in',
                    'quantity_before' => $quantityBefore,
                    'quantity_change' => 0,
                    'quantity_after' => $quantityBefore,
                    'weight' => $amount,
                    'weight_before' => $weightBefore,
                    'weight_change' => $amount,
                    'weight_after' => $stock->weight,
                    'uom_id' => $product->uom_id,
                    'admin_id' => $adminId,
                    'remarks' => $remarks,
                    'movement_date' => $movementDate,
                ]);
            }

            $amount = (float) $quantity;
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Quantity is required for this product.');
            }

            $stock->quantity = $quantityBefore + $amount;
            $stock->save();

            return $this->createMovement([
                'product_id' => $productId,
                'movement_type' => 'stock_in',
                'quantity_before' => $quantityBefore,
                'quantity_change' => $amount,
                'quantity_after' => $stock->quantity,
                'weight' => null,
                'weight_before' => null,
                'weight_change' => null,
                'weight_after' => null,
                'uom_id' => $product->uom_id,
                'admin_id' => $adminId,
                'remarks' => $remarks,
                'movement_date' => $movementDate,
            ]);
        });
    }

    public function stockOut(int $productId, ?float $quantity, ?float $weight, string $reason, ?string $remarks, ?int $adminId, string $movementDate): StockMovement
    {
        return DB::transaction(function () use ($productId, $quantity, $weight, $reason, $remarks, $adminId, $movementDate) {
            $product = Product::findOrFail($productId);
            $stock = $this->getOrCreateStock($productId);
            $quantityBefore = (float) $stock->quantity;
            $weightBefore = (float) ($stock->weight ?? 0);

            if ($product->inventoryTracksWeight()) {
                $amount = (float) $weight;
                if ($amount <= 0) {
                    throw new \InvalidArgumentException('Weight is required for this product.');
                }

                if ($weightBefore < $amount) {
                    throw new \InvalidArgumentException('Insufficient stock. Current balance: ' . number_format($weightBefore, 3) . ' kg');
                }

                $stock->weight = $weightBefore - $amount;
                $stock->save();

                return $this->createMovement([
                    'product_id' => $productId,
                    'movement_type' => 'stock_out',
                    'quantity_before' => $quantityBefore,
                    'quantity_change' => 0,
                    'quantity_after' => $quantityBefore,
                    'weight' => $amount,
                    'weight_before' => $weightBefore,
                    'weight_change' => -$amount,
                    'weight_after' => $stock->weight,
                    'uom_id' => $product->uom_id,
                    'admin_id' => $adminId,
                    'reason' => $reason,
                    'remarks' => $remarks,
                    'movement_date' => $movementDate,
                ]);
            }

            $amount = (float) $quantity;
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Quantity is required for this product.');
            }

            if ($quantityBefore < $amount) {
                throw new \InvalidArgumentException('Insufficient stock. Current balance: ' . number_format($quantityBefore, 3));
            }

            $stock->quantity = $quantityBefore - $amount;
            $stock->save();

            return $this->createMovement([
                'product_id' => $productId,
                'movement_type' => 'stock_out',
                'quantity_before' => $quantityBefore,
                'quantity_change' => -$amount,
                'quantity_after' => $stock->quantity,
                'weight' => null,
                'weight_before' => null,
                'weight_change' => null,
                'weight_after' => null,
                'uom_id' => $product->uom_id,
                'admin_id' => $adminId,
                'reason' => $reason,
                'remarks' => $remarks,
                'movement_date' => $movementDate,
            ]);
        });
    }

    public function deductForOrder(Order $order, ?int $adminId = null): void
    {
        if ($this->orderAlreadyDeducted($order->id)) {
            return;
        }

        DB::transaction(function () use ($order, $adminId) {
            $orderProducts = OrderProduct::where('order_id', $order->id)
                ->where('status', OrderProduct::$status['active'])
                ->get();

            foreach ($orderProducts as $orderProduct) {
                $product = Product::find($orderProduct->product_id);
                if (!$product) {
                    continue;
                }

                $stock = $this->getOrCreateStock($product->id);
                $quantityBefore = (float) $stock->quantity;
                $weightBefore = (float) ($stock->weight ?? 0);
                $qtyDeduction = 0.0;
                $weightDeduction = null;
                $referenceWeight = null;

                if ($product->inventoryTracksQuantity()) {
                    $qtyDeduction = (float) ($orderProduct->quantity ?? 0);

                    if ($product->sell_in === Product::SELL_IN_QTY_BILL_WEIGHT) {
                        $rawWeight = $orderProduct->weight ?? $orderProduct->product_weight;
                        if ($rawWeight !== null && $rawWeight !== '') {
                            $referenceWeight = (float) preg_replace('/[^0-9.]/', '', (string) $rawWeight);
                        }
                    }
                } elseif ($product->inventoryTracksWeight()) {
                    $rawWeight = $orderProduct->weight ?? $orderProduct->product_weight;
                    if ($rawWeight !== null && $rawWeight !== '') {
                        $weightDeduction = (float) preg_replace('/[^0-9.]/', '', (string) $rawWeight);
                    }
                }

                if ($qtyDeduction <= 0 && ($weightDeduction === null || $weightDeduction <= 0)) {
                    continue;
                }

                if ($product->inventoryTracksWeight()) {
                    $stock->weight = max(0, $weightBefore - $weightDeduction);
                } else {
                    $stock->quantity = $quantityBefore - $qtyDeduction;
                }
                $stock->save();

                $this->createMovement([
                    'product_id' => $product->id,
                    'movement_type' => 'sales_deduction',
                    'quantity_before' => $quantityBefore,
                    'quantity_change' => -$qtyDeduction,
                    'quantity_after' => $stock->quantity,
                    'weight' => $referenceWeight ?? $weightDeduction,
                    'weight_before' => $product->inventoryTracksWeight() ? $weightBefore : null,
                    'weight_change' => $product->inventoryTracksWeight() ? -$weightDeduction : null,
                    'weight_after' => $product->inventoryTracksWeight() ? $stock->weight : null,
                    'uom_id' => $product->uom_id,
                    'order_id' => $order->id,
                    'admin_id' => $adminId,
                    'remarks' => 'Order #' . $order->id . ' confirmed',
                    'movement_date' => now()->toDateString(),
                ]);
            }
        });
    }

    public function restoreForOrder(Order $order, ?int $adminId = null): void
    {
        $movements = StockMovement::where('order_id', $order->id)
            ->where('movement_type', 'sales_deduction')
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($movements, $order, $adminId) {
            foreach ($movements as $movement) {
                $product = Product::find($movement->product_id);
                $stock = $this->getOrCreateStock($movement->product_id);
                $quantityBefore = (float) $stock->quantity;
                $weightBefore = (float) ($stock->weight ?? 0);
                $restoreQty = abs((float) $movement->quantity_change);
                $restoreWeight = $movement->weight_change !== null
                    ? abs((float) $movement->weight_change)
                    : null;

                if ($product && $product->inventoryTracksWeight() && $restoreWeight !== null) {
                    $stock->weight = $weightBefore + $restoreWeight;
                } else {
                    $stock->quantity = $quantityBefore + $restoreQty;
                }
                $stock->save();

                $this->createMovement([
                    'product_id' => $movement->product_id,
                    'movement_type' => 'order_amendment',
                    'quantity_before' => $quantityBefore,
                    'quantity_change' => $restoreQty,
                    'quantity_after' => $stock->quantity,
                    'weight' => $movement->weight,
                    'weight_before' => $product && $product->inventoryTracksWeight() ? $weightBefore : null,
                    'weight_change' => $product && $product->inventoryTracksWeight() && $restoreWeight !== null ? $restoreWeight : null,
                    'weight_after' => $product && $product->inventoryTracksWeight() ? $stock->weight : null,
                    'uom_id' => $movement->uom_id,
                    'order_id' => $order->id,
                    'admin_id' => $adminId,
                    'remarks' => 'Order #' . $order->id . ' cancelled — stock restored',
                    'movement_date' => now()->toDateString(),
                ]);
            }

            StockMovement::where('order_id', $order->id)
                ->where('movement_type', 'sales_deduction')
                ->delete();
        });
    }

    public function handleOrderStatusChange(Order $order, string $previousStatus, string $newStatus, ?int $adminId = null): void
    {
        if ($newStatus === Order::$status['in_route'] && $previousStatus !== Order::$status['in_route']) {
            $this->deductForOrder($order, $adminId);
        }

        if ($newStatus === Order::$status['completed']
            && $previousStatus !== Order::$status['completed']
            && $order->isPosOrder()) {
            $this->deductForOrder($order, $adminId);
        }

        if ($newStatus === Order::$status['cancelled'] && $previousStatus !== Order::$status['cancelled']) {
            $this->restoreForOrder($order, $adminId);
        }
    }

    public function getOrCreateStock(int $productId): ProductStock
    {
        return ProductStock::firstOrCreate(
            ['product_id' => $productId],
            ['quantity' => 0, 'weight' => 0]
        );
    }

    public function adjustBalance(
        int $productId,
        float $newQuantity,
        ?float $newWeight,
        ?string $remarks,
        ?int $adminId
    ): ?StockMovement {
        return DB::transaction(function () use ($productId, $newQuantity, $newWeight, $remarks, $adminId) {
            $product = Product::findOrFail($productId);
            $stock = $this->getOrCreateStock($productId);
            $quantityBefore = (float) $stock->quantity;
            $weightBefore = (float) ($stock->weight ?? 0);

            if ($product->inventoryTracksWeight()) {
                $newWeightValue = $newWeight !== null ? (float) $newWeight : $weightBefore;
                $weightChange = $newWeightValue - $weightBefore;
                $weightChanged = abs($weightChange) >= 0.0005;

                if (!$weightChanged) {
                    return null;
                }

                $stock->weight = $newWeightValue;
                $stock->save();

                return $this->createMovement([
                    'product_id' => $productId,
                    'movement_type' => 'manual_adjustment',
                    'quantity_before' => $quantityBefore,
                    'quantity_change' => 0,
                    'quantity_after' => $quantityBefore,
                    'weight' => abs($weightChange),
                    'weight_before' => $weightBefore,
                    'weight_change' => $weightChange,
                    'weight_after' => $stock->weight,
                    'uom_id' => $product->uom_id,
                    'admin_id' => $adminId,
                    'remarks' => $remarks ?? 'Manual stock balance adjustment',
                    'movement_date' => now()->toDateString(),
                ]);
            }

            $quantityChange = $newQuantity - $quantityBefore;
            $quantityChanged = abs($quantityChange) >= 0.0005;

            if (!$quantityChanged) {
                return null;
            }

            $stock->quantity = $newQuantity;
            $stock->save();

            return $this->createMovement([
                'product_id' => $productId,
                'movement_type' => 'manual_adjustment',
                'quantity_before' => $quantityBefore,
                'quantity_change' => $quantityChange,
                'quantity_after' => $stock->quantity,
                'weight' => null,
                'weight_before' => null,
                'weight_change' => null,
                'weight_after' => null,
                'uom_id' => $product->uom_id,
                'admin_id' => $adminId,
                'remarks' => $remarks ?? 'Manual stock balance adjustment',
                'movement_date' => now()->toDateString(),
            ]);
        });
    }

    private function orderAlreadyDeducted(int $orderId): bool
    {
        return StockMovement::where('order_id', $orderId)
            ->where('movement_type', 'sales_deduction')
            ->exists();
    }

    private function createMovement(array $attributes): StockMovement
    {
        return StockMovement::create($attributes);
    }
}
