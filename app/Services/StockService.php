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
    public function stockIn(int $productId, float $quantity, ?float $weight, string $movementDate, ?string $remarks, ?int $adminId): StockMovement
    {
        return DB::transaction(function () use ($productId, $quantity, $weight, $movementDate, $remarks, $adminId) {
            $product = Product::findOrFail($productId);
            $stock = $this->getOrCreateStock($productId);
            $quantityBefore = (float) $stock->quantity;

            $stock->quantity = $quantityBefore + $quantity;
            if ($weight !== null) {
                $stock->weight = ($stock->weight ?? 0) + $weight;
            }
            $stock->save();

            return StockMovement::create([
                'product_id' => $productId,
                'movement_type' => 'stock_in',
                'quantity_before' => $quantityBefore,
                'quantity_change' => $quantity,
                'quantity_after' => $stock->quantity,
                'weight' => $weight,
                'uom_id' => $product->uom_id,
                'admin_id' => $adminId,
                'remarks' => $remarks,
                'movement_date' => $movementDate,
            ]);
        });
    }

    public function stockOut(int $productId, float $quantity, ?float $weight, string $reason, ?string $remarks, ?int $adminId, string $movementDate): StockMovement
    {
        return DB::transaction(function () use ($productId, $quantity, $weight, $reason, $remarks, $adminId, $movementDate) {
            $product = Product::findOrFail($productId);
            $stock = $this->getOrCreateStock($productId);
            $quantityBefore = (float) $stock->quantity;

            if ($quantityBefore < $quantity) {
                throw new \InvalidArgumentException('Insufficient stock. Current balance: ' . $quantityBefore);
            }

            $stock->quantity = $quantityBefore - $quantity;
            if ($weight !== null && $stock->weight !== null) {
                $stock->weight = max(0, (float) $stock->weight - $weight);
            }
            $stock->save();

            return StockMovement::create([
                'product_id' => $productId,
                'movement_type' => 'stock_out',
                'quantity_before' => $quantityBefore,
                'quantity_change' => -$quantity,
                'quantity_after' => $stock->quantity,
                'weight' => $weight,
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
                $quantity = $this->resolveOrderLineQuantity($orderProduct);
                if ($quantity <= 0) {
                    continue;
                }

                $product = Product::find($orderProduct->product_id);
                if (!$product) {
                    continue;
                }

                $stock = $this->getOrCreateStock($product->id);
                $quantityBefore = (float) $stock->quantity;
                $weight = $this->resolveOrderLineWeight($orderProduct);

                $stock->quantity = $quantityBefore - $quantity;
                if ($weight !== null && $stock->weight !== null) {
                    $stock->weight = max(0, (float) $stock->weight - $weight);
                } elseif ($weight !== null) {
                    $stock->weight = max(0, 0 - $weight);
                }
                $stock->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => 'sales_deduction',
                    'quantity_before' => $quantityBefore,
                    'quantity_change' => -$quantity,
                    'quantity_after' => $stock->quantity,
                    'weight' => $weight,
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
                $stock = $this->getOrCreateStock($movement->product_id);
                $quantityBefore = (float) $stock->quantity;
                $restoreQty = abs((float) $movement->quantity_change);

                $stock->quantity = $quantityBefore + $restoreQty;
                if ($movement->weight !== null) {
                    $stock->weight = ($stock->weight ?? 0) + (float) $movement->weight;
                }
                $stock->save();

                StockMovement::create([
                    'product_id' => $movement->product_id,
                    'movement_type' => 'order_amendment',
                    'quantity_before' => $quantityBefore,
                    'quantity_change' => $restoreQty,
                    'quantity_after' => $stock->quantity,
                    'weight' => $movement->weight,
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
        if ($newStatus === Order::$status['delivering'] && $previousStatus !== Order::$status['delivering']) {
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

    private function orderAlreadyDeducted(int $orderId): bool
    {
        return StockMovement::where('order_id', $orderId)
            ->where('movement_type', 'sales_deduction')
            ->exists();
    }

    private function resolveOrderLineQuantity(OrderProduct $orderProduct): float
    {
        return (float) $orderProduct->quantity;
    }

    private function resolveOrderLineWeight(OrderProduct $orderProduct): ?float
    {
        $weight = $orderProduct->product_weight ?? $orderProduct->weight;

        if ($weight === null || $weight === '') {
            return null;
        }

        return (float) preg_replace('/[^0-9.]/', '', (string) $weight);
    }
}
