<?php

namespace App\Http\Controllers\Member;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use App\PdfHelper;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function download(Request $request, $folder, $id, $filename)
    {
        $this->guardOrderDocumentAccess($folder, $id, $filename);

        $path = "$folder/$id/$filename";
        if (!Storage::disk('local')->exists($path)) {
            $this->ensureOrderDocumentExists($folder, $id, $filename);
        }

        if (Storage::disk('local')->exists($path)) {
            $mime = Storage::disk('local')->mimeType($path);
            $file = Storage::disk('local')->get($path);

            return response($file)->header('Content-Type', $mime);
        }

        abort(404, 'File not found');
    }

    public function downloadAndUpdateStatus(Request $request, $folder, $id, $filename)
    {
        $this->guardOrderDocumentAccess($folder, $id, $filename);

        $path = "$folder/$id/$filename";
        if (!Storage::disk('local')->exists($path)) {
            $this->ensureOrderDocumentExists($folder, $id, $filename);
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'File not found');
        }

        if ($folder == Order::$path) {
            $order = Order::find($id);
            if ($order && $order->status == Order::$status['in_route']) {
                try {
                    app(OrderStatusService::class)->transition($order, Order::$status['delivered']);
                } catch (\InvalidArgumentException $e) {
                    // Keep download working even if status transition fails.
                }
            }
        }

        $mime = Storage::disk('local')->mimeType($path);
        $file = Storage::disk('local')->get($path);

        return response($file, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function guardOrderDocumentAccess(string $folder, $id, string $filename): void
    {
        if ($folder !== Order::$path) {
            return;
        }

        $isInvoice = str_contains($filename, 'invoice');
        $isDeliveryOrder = str_contains($filename, 'delivery-order');

        if (!$isInvoice && !$isDeliveryOrder) {
            return;
        }

        $order = Order::find($id);
        if (!$order) {
            abort(404, 'File not found');
        }

        $user = Auth::guard('web')->user();
        if ($user && (int) $order->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($isInvoice && !$order->canShowInvoiceToCustomer($user)) {
            abort(403, 'Invoice is available after payment has been collected.');
        }

        if ($isDeliveryOrder && (!$order->canShowDeliveryOrder() || !$user)) {
            abort(403, 'Delivery order is available once the order is in route for delivery.');
        }
    }

    private function ensureOrderDocumentExists(string $folder, $id, string $filename): void
    {
        if ($folder !== Order::$path) {
            return;
        }

        $order = Order::find($id);
        if (!$order) {
            return;
        }

        if (str_contains($filename, 'delivery-order') && $order->canShowDeliveryOrder()) {
            PdfHelper::GenerateDeliveryOrder($order);
            return;
        }

        if (str_contains($filename, 'invoice') && $order->canShowInvoice()) {
            PdfHelper::GenerateOrderInvoice($order);
        }
    }
}
