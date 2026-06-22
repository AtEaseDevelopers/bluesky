<?php

namespace App\Services;

use App\AutoCountSyncLog;
use App\Order;
use App\User;

class AutoCountSyncService
{
    /**
     * Queue invoice sync to AutoCount after order is paid and completed.
     * Integration endpoint to be wired when AutoCount credentials are available.
     */
    public function syncIfEligible(Order $order, ?int $adminId = null): AutoCountSyncLog
    {
        if ($order->payment_status !== Order::$payment_status['paid']) {
            return $this->log($order, 'skipped', 'Invoice not paid — sync not allowed.', null, $adminId);
        }

        if ($order->status !== Order::$status['delivered']) {
            return $this->log($order, 'skipped', 'Order must be delivered before sync.', null, $adminId);
        }

        if (!$order->invoice_number) {
            app(OrderService::class)->generateInvoiceNumber($order->fresh());
            $order = $order->fresh();
        }

        // Placeholder until AutoCount API is connected.
        $order->update([
            'autocount_sync_status' => 'pending_sync',
        ]);

        return $this->log(
            $order,
            'pending_sync',
            'Invoice queued for AutoCount sync. Connect AutoCount API to complete integration.',
            null,
            $adminId
        );
    }

    public function log(
        Order $order,
        string $status,
        ?string $response = null,
        ?string $error = null,
        ?int $adminId = null
    ): AutoCountSyncLog {
        $log = AutoCountSyncLog::create([
            'order_id' => $order->id,
            'invoice_number' => $order->invoice_number,
            'sync_status' => $status,
            'response_message' => $response,
            'error_message' => $error,
            'admin_id' => $adminId,
        ]);

        $order->update([
            'autocount_sync_status' => $status,
            'autocount_synced_at' => in_array($status, ['synced', 'synced_successfully'], true) ? now() : null,
        ]);

        return $log;
    }

    /**
     * @param  array<int|string>  $orderIds
     * @return array{synced: int, skipped: int, errors: array<int, string>}
     */
    public function syncOrders(array $orderIds, ?int $adminId = null): array
    {
        $synced = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_unique($orderIds) as $orderId) {
            try {
                $order = Order::findOrFail($orderId);
                $log = $this->syncIfEligible($order, $adminId);

                if (in_array($log->sync_status, ['pending_sync', 'synced', 'synced_successfully'], true)) {
                    $synced++;
                } else {
                    $skipped++;
                    if ($log->response_message) {
                        $errors[] = __('orders.js.sync_autocount_order_skipped', [
                            'order' => $order->id,
                            'reason' => $log->response_message,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = $e->getMessage();
            }
        }

        return compact('synced', 'skipped', 'errors');
    }

    /**
     * Integration endpoint to be wired when AutoCount credentials are available.
     */
    public function syncCustomer(User $user, ?int $adminId = null): User
    {
        if (!$user->hasCompletedRegistration()) {
            throw new \InvalidArgumentException('Customer must complete registration before AutoCount sync.');
        }

        $user->update([
            'autocount_sync_status' => 'pending_sync',
            'autocount_synced_at' => null,
        ]);

        return $user->fresh();
    }

    /**
     * @param  array<int|string>  $customerIds
     * @return array{synced: int, skipped: int, errors: array<int, string>}
     */
    public function syncCustomers(array $customerIds, ?int $adminId = null): array
    {
        $synced = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_unique($customerIds) as $customerId) {
            try {
                $user = User::findOrFail($customerId);
                $this->syncCustomer($user, $adminId);
                $synced++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = $e->getMessage();
            }
        }

        return compact('synced', 'skipped', 'errors');
    }
}
