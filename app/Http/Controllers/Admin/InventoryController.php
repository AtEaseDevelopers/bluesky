<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Product;
use App\Services\StockService;
use App\StockMovement;
use App\Uom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->middleware('auth_admin');
        $this->stockService = $stockService;
    }

    public function index()
    {
        return view('admin.inventory.balance.index');
    }

    public function stockInCreate()
    {
        return view('admin.inventory.stock-in.create', [
            'products' => $this->activeProducts(),
            'uoms' => Uom::orderBy('uom_name')->get(),
        ]);
    }

    public function stockInStore(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.001',
            'weight' => 'nullable|numeric|min:0',
            'movement_date' => 'required|date',
            'remarks' => 'nullable|string|max:500',
        ]);

        $this->stockService->stockIn(
            (int) $data['product_id'],
            (float) $data['quantity'],
            isset($data['weight']) ? (float) $data['weight'] : null,
            $data['movement_date'],
            $data['remarks'] ?? null,
            Auth::guard('web_admin')->id()
        );

        return redirect(route('admin.inventory.movements'))->with('success', 'Stock in recorded successfully.');
    }

    public function stockOutCreate()
    {
        return view('admin.inventory.stock-out.create', [
            'products' => $this->activeProducts(),
            'reasons' => StockMovement::$stock_out_reasons,
        ]);
    }

    public function stockOutStore(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.001',
            'weight' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:100',
            'reason_other' => 'nullable|required_if:reason,other|string|max:255',
            'movement_date' => 'required|date',
            'remarks' => 'nullable|string|max:500',
        ]);

        $reason = $data['reason'] === 'other'
            ? 'Other: ' . ($data['reason_other'] ?? '')
            : (StockMovement::$stock_out_reasons[$data['reason']] ?? $data['reason']);

        try {
            $this->stockService->stockOut(
                (int) $data['product_id'],
                (float) $data['quantity'],
                isset($data['weight']) ? (float) $data['weight'] : null,
                $reason,
                $data['remarks'] ?? null,
                Auth::guard('web_admin')->id(),
                $data['movement_date']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect(route('admin.inventory.movements'))->with('success', 'Stock out recorded successfully.');
    }

    public function movements()
    {
        return view('admin.inventory.movements.index', [
            'movement_types' => StockMovement::$movement_types,
        ]);
    }

    public function fetch_balances(Request $request)
    {
        $columns = ['products.id', 'options', 'products.name', 'products.sku', 'uom_name', 'quantity', 'weight', 'updated_at'];
        $query = DB::table('products')
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->leftJoin('uoms', 'products.uom_id', '=', 'uoms.id')
            ->where('products.status', '!=', 'removed')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'uoms.uom_name',
                DB::raw('COALESCE(product_stocks.quantity, 0) as quantity'),
                DB::raw('COALESCE(product_stocks.weight, 0) as weight'),
                'product_stocks.updated_at'
            );

        $totalRecords = (clone $query)->count();
        $search = $request->input('search.value');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'LIKE', "%{$search}%")
                    ->orWhere('products.sku', 'LIKE', "%{$search}%");
            });
        }

        $totalFiltered = (clone $query)->count();

        if ($request->input('length') == -1) {
            $limit = $totalFiltered;
        } else {
            $limit = $request->input('length');
        }

        $start = $request->input('start');
        $orderCol = $columns[$request->input('order.0.column')] ?? 'products.name';
        $dir = $request->input('order.0.dir') ?? 'asc';

        if ($orderCol === 'options') {
            $orderCol = 'products.name';
        }

        $records = $query->offset($start)->limit($limit)->orderBy($orderCol, $dir)->get();

        $data = [];
        foreach ($records as $record) {
            $data[] = [
                'id' => $record->id,
                'options' => '<a href="' . route('admin.inventory.stock-in.create') . '?product_id=' . $record->id . '" class="btn btn-sm btn-success me-1" title="Stock In"><i class="fa fa-plus"></i></a>'
                    . '<a href="' . route('admin.inventory.stock-out.create') . '?product_id=' . $record->id . '" class="btn btn-sm btn-warning" title="Stock Out"><i class="fa fa-minus"></i></a>',
                'name' => $record->name,
                'sku' => $record->sku ?: '-',
                'uom_name' => $record->uom_name ?: '-',
                'quantity' => number_format((float) $record->quantity, 3),
                'weight' => number_format((float) $record->weight, 3) . ' kg',
                'updated_at' => $record->updated_at ? date('d-m-Y H:i', strtotime($record->updated_at)) : '-',
            ];
        }

        echo json_encode([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalRecords),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    public function fetch_movements(Request $request)
    {
        $columns = ['stock_movements.id', 'movement_date', 'product_name', 'movement_type', 'quantity_before', 'quantity_change', 'quantity_after', 'weight', 'reason', 'admin_name', 'remarks'];
        $query = DB::table('stock_movements')
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->leftJoin('admins', 'stock_movements.admin_id', '=', 'admins.id')
            ->select(
                'stock_movements.*',
                'products.name as product_name',
                'admins.name as admin_name'
            );

        if ($request->filled('filter_type')) {
            $query->where('stock_movements.movement_type', $request->input('filter_type'));
        }

        if ($request->filled('filter_product_id')) {
            $query->where('stock_movements.product_id', $request->input('filter_product_id'));
        }

        $totalRecords = DB::table('stock_movements')->count();
        $totalFiltered = (clone $query)->count();

        if ($request->input('length') == -1) {
            $limit = $totalFiltered;
        } else {
            $limit = $request->input('length');
        }

        $start = $request->input('start');
        $orderCol = $columns[$request->input('order.0.column')] ?? 'stock_movements.id';
        $dir = $request->input('order.0.dir') ?? 'desc';

        if ($orderCol === 'product_name') {
            $orderCol = 'products.name';
        } elseif ($orderCol === 'admin_name') {
            $orderCol = 'admins.name';
        } elseif (!str_contains($orderCol, '.')) {
            $orderCol = 'stock_movements.' . $orderCol;
        }

        $records = $query->offset($start)->limit($limit)->orderBy($orderCol, $dir)->get();

        $data = [];
        foreach ($records as $record) {
            $change = (float) $record->quantity_change;
            $changeFormatted = ($change >= 0 ? '+' : '') . number_format($change, 3);

            $data[] = [
                'id' => $record->id,
                'movement_date' => date('d-m-Y', strtotime($record->movement_date)),
                'product_name' => $record->product_name,
                'movement_type' => StockMovement::$movement_types[$record->movement_type] ?? $record->movement_type,
                'quantity_before' => number_format((float) $record->quantity_before, 3),
                'quantity_change' => '<span class="' . ($change >= 0 ? 'text-success' : 'text-danger') . '">' . $changeFormatted . '</span>',
                'quantity_after' => number_format((float) $record->quantity_after, 3),
                'weight' => $record->weight !== null ? number_format((float) $record->weight, 3) . ' kg' : '-',
                'reason' => $record->reason ?: '-',
                'admin_name' => $record->admin_name ?: 'System',
                'remarks' => $record->remarks ?: '-',
            ];
        }

        echo json_encode([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalRecords),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    private function activeProducts()
    {
        return Product::where('status', Product::$status['active'])
            ->orderBy('name')
            ->get();
    }
}
