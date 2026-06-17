<?php

namespace App\Services;

use App\AutoCountSyncLog;
use App\Order;

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
}
