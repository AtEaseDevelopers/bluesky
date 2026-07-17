<?php

namespace App\Http\Controllers\Admin;

use App\DeliverySlot;
use App\Http\Controllers\Controller;
use App\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliverySlotController extends Controller
{
    public function index()
    {
        return view('admin.delivery-slots.index');
    }

    public function create()
    {
        return view('admin.delivery-slots.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'time_start' => 'required',
            'time_end' => 'required|after:time_start',
            'max_orders' => 'nullable|integer|min:1',
            'is_enabled' => 'nullable|boolean',
        ]);

        DeliverySlot::create([
            'time_start' => $data['time_start'],
            'time_end' => $data['time_end'],
            'max_orders' => $data['max_orders'] ?? null,
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);

        return redirect(route('admin.delivery-slots.index'))
            ->with('success', __('delivery_slots.slot_created'));
    }

    public function edit($id)
    {
        return view('admin.delivery-slots.edit', [
            'slot' => DeliverySlot::findOrFail(decrypt($id)),
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'time_start' => 'required',
            'time_end' => 'required',
            'max_orders' => 'nullable|integer|min:1',
            'is_enabled' => 'nullable|boolean',
        ]);

        DeliverySlot::where('id', decrypt($id))->update([
            'time_start' => $data['time_start'],
            'time_end' => $data['time_end'],
            'max_orders' => $data['max_orders'] ?? null,
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);

        return redirect(route('admin.delivery-slots.index'))
            ->with('success', __('delivery_slots.slot_updated'));
    }

    public function destroy($id)
    {
        DeliverySlot::where('id', decrypt($id))->delete();

        return redirect(route('admin.delivery-slots.index'))
            ->with('success', __('delivery_slots.slot_deleted'));
    }

    public function slotsForDate(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        $slots = DeliverySlot::slotsAvailableForDate($data['date']);

        if (!empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
            if ($order
                && $order->delivery_slot_id
                && $order->delivery_date
                && $order->delivery_date->toDateString() === $data['date']) {
                $currentSlot = DeliverySlot::find($order->delivery_slot_id);
                if ($currentSlot
                    && $currentSlot->is_enabled
                    && !$slots->contains('id', $currentSlot->id)) {
                    $slots->push($currentSlot);
                    $slots = $slots->sortBy('time_start')->values();
                }
            }
        }

        $slots = $slots
            ->map(fn (DeliverySlot $slot) => [
                'id' => $slot->id,
                'label' => $slot->time_label,
            ])
            ->values();

        return response()->json(['slots' => $slots]);
    }

    public function fetch_delivery_slots(Request $request)
    {
        $columns = ['id', 'options', 'time_label', 'max_orders', 'orders_count', 'is_enabled', 'created_at'];
        $totalRecords = DB::table('delivery_slots')->count();
        $totalFiltered = $totalRecords;

        if ($request->input('length') == -1) {
            $limit = $totalRecords;
        } else {
            $limit = $request->input('length');
        }

        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'time_start';
        $dir = $request->input('order.0.dir') ?? 'asc';

        $query = DB::table('delivery_slots')
            ->select(
                'delivery_slots.*',
                DB::raw('(SELECT COUNT(*) FROM orders WHERE orders.delivery_slot_id = delivery_slots.id AND orders.status != "cancelled") as orders_count')
            );

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('time_start', 'LIKE', "%{$search}%")
                    ->orWhere('time_end', 'LIKE', "%{$search}%");
            });
            $totalFiltered = $query->count();
        }

        if ($order === 'time_label') {
            $order = 'time_start';
        } elseif ($order === 'orders_count') {
            $order = 'delivery_slots.time_start';
        } elseif (!str_contains($order, '.')) {
            $order = 'delivery_slots.' . $order;
        }

        $records = $query->offset($start)->limit($limit)->orderBy($order, $dir)->get();
        $data = [];

        foreach ($records as $record) {
            $timeLabel = date('H:i', strtotime($record->time_start)) . ' - ' . date('H:i', strtotime($record->time_end));
            $data[] = [
                'id' => $record->id,
                'options' => '<a href="' . route('admin.delivery-slots.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary me-1"><i class="fa fa-edit"></i></a>'
                    . '<button type="button" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#delete" data-action="' . route('admin.delivery-slots.destroy', encrypt($record->id)) . '"><i class="fa fa-trash"></i></button>',
                'time_label' => $timeLabel,
                'max_orders' => $record->max_orders ?: __('delivery_slots.unlimited'),
                'orders_count' => $record->orders_count,
                'is_enabled' => $record->is_enabled
                    ? '<span class="badge bg-success">' . __('delivery_slots.enabled') . '</span>'
                    : '<span class="badge bg-secondary">' . __('delivery_slots.disabled') . '</span>',
                'created_at' => $record->created_at ? date('d-m-Y', strtotime($record->created_at)) : '-',
            ];
        }

        echo json_encode([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalRecords),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }
}
