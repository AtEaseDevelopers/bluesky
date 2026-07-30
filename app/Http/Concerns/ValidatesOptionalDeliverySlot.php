<?php

namespace App\Http\Concerns;

use App\DeliveryBlackout;
use App\DeliverySlot;

trait ValidatesOptionalDeliverySlot
{
    protected function validateOptionalDelivery(array $data): ?array
    {
        $hasDate = !empty($data['delivery_date']);
        $hasSlot = !empty($data['delivery_slot_id']);

        if ($hasDate xor $hasSlot) {
            return [
                'error' => true,
                'field_err' => ['delivery_date' => [__('orders.delivery_date_and_slot_required')]],
            ];
        }

        if (!$hasDate) {
            return null;
        }

        if (DeliveryBlackout::isDateBlocked($data['delivery_date'])) {
            return [
                'error' => true,
                'field_err' => ['delivery_date' => ['Delivery is not available on the selected date.']],
            ];
        }

        $slot = DeliverySlot::find($data['delivery_slot_id']);
        if (!$slot || !$slot->isAvailableForDate($data['delivery_date'])) {
            return [
                'error' => true,
                'field_err' => ['delivery_slot_id' => [__('orders.delivery_slot_unavailable')]],
            ];
        }

        return null;
    }

    protected function deliveryFieldsFromValidated(array $data): array
    {
        if (empty($data['delivery_date'])) {
            return [
                'delivery_slot_id' => null,
                'delivery_date' => null,
                'delivery_time_slot' => null,
            ];
        }

        $slot = DeliverySlot::find($data['delivery_slot_id']);

        return [
            'delivery_slot_id' => $slot->id,
            'delivery_date' => $data['delivery_date'],
            'delivery_time_slot' => $slot->time_label,
        ];
    }
}
