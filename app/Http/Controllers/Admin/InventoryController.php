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

        return redirect(route('admin.inventory.movements'))->with('success', __('inventory.stock_in_success'));
    }

    public function stockOutCreate()
    {
        return view('admin.inventory.stock-out.create', [
            'products' => $this->activeProducts(),
            'reasons' => StockMovement::stockOutReasonLabels(),
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
            ? __('inventory.stock_out_reasons.other') . ': ' . ($data['reason_other'] ?? '')
            : (StockMovement::stockOutReasonLabels()[$data['reason']] ?? $data['reason']);

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

        return redirect(route('admin.inventory.movements'))->with('success', __('inventory.stock_out_success'));
    }

    public function movements()
    {
        return view('admin.inventory.movements.index', [
            'movement_types' => StockMovement::movementTypeLabels(),
        ]);
    }

    public function fetch_balances(Request $request)
    {
        $columns = ['products.id', 'options', 'products.name', 'products.sku', 'uom_name', 'price', 'quantity', 'weight', 'updated_at'];
        $query = DB::table('products')
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->leftJoin('uoms', 'products.uom_id', '=', 'uoms.id')
            ->where('products.status', '!=', 'removed')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.price',
                'products.images',
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
            $quantity = (float) $record->quantity;
            $weight = (float) $record->weight;
            $price = (float) $record->price;
            $uomName = $record->uom_name ?: 'KG';
            $imageUrl = Product::resolveImageUrl($record);

            $data[] = [
                'id' => $record->id,
                'options' => '<a href="' . route('admin.inventory.stock-in.create') . '?product_id=' . $record->id . '" class="btn btn-sm btn-success me-1" title="' . e(__('inventory.stock_in_action')) . '"><i class="fa fa-plus"></i></a>'
                    . '<a href="' . route('admin.inventory.stock-out.create') . '?product_id=' . $record->id . '" class="btn btn-sm btn-warning me-1" title="' . e(__('inventory.stock_out')) . '"><i class="fa fa-minus"></i></a>'
                    . '<button type="button" class="btn btn-sm btn-primary btn-edit-stock" title="' . e(__('inventory.edit_stock_action')) . '"'
                    . ' data-product-id="' . $record->id . '"'
                    . ' data-name="' . e($record->name) . '"'
                    . ' data-sku="' . e($record->sku ?: '-') . '"'
                    . ' data-price="' . number_format($price, 2, '.', '') . '"'
                    . ' data-quantity="' . number_format($quantity, 3, '.', '') . '"'
                    . ' data-weight="' . number_format($weight, 3, '.', '') . '"'
                    . ' data-uom="' . e($uomName) . '"'
                    . ' data-image-url="' . e($imageUrl) . '">'
                    . '<i class="fa fa-edit"></i></button>',
                'name' => '<div class="d-flex align-items-center gap-2">'
                    . '<img src="' . e($imageUrl) . '" alt="" class="rounded" style="width:40px;height:40px;object-fit:cover" onerror="this.src=\'' . asset('assets/images/product-default.jpg') . '\'">'
                    . '<span>' . e($record->name) . '</span></div>',
                'sku' => $record->sku ?: '-',
                'uom_name' => $uomName,
                'price' => Product::formatUnitPrice($price, $uomName),
                'quantity' => number_format($quantity, 3),
                'weight' => number_format($weight, 3) . ' kg',
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

    public function updateStock(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'images' => array_merge(Product::$attribute_rules['images'], []),
        ]);

        $product = Product::with('uom')->findOrFail($data['product_id']);
        $product->update(['price' => $data['price']]);

        if ($request->hasFile('images')) {
            $filename = Product::storeUploadedImage($product->id, $request->file('images'));
            $product->update(['images' => json_encode([$filename])]);
        }

        $this->stockService->adjustBalance(
            (int) $data['product_id'],
            (float) $data['quantity'],
            array_key_exists('weight', $data) && $data['weight'] !== null && $data['weight'] !== ''
                ? (float) $data['weight']
                : null,
            'Updated from stock balance',
            Auth::guard('web_admin')->id()
        );

        $stock = $this->stockService->getOrCreateStock((int) $data['product_id']);
        $uomName = optional($product->uom)->uom_name ?: 'KG';

        return response()->json([
            'success' => true,
            'message' => 'Stock details updated successfully.',
            'price' => Product::formatUnitPrice((float) $product->price, $uomName),
            'quantity' => number_format((float) $stock->quantity, 3),
            'weight' => number_format((float) ($stock->weight ?? 0), 3) . ' kg',
            'updated_at' => $stock->updated_at ? date('d-m-Y H:i', strtotime($stock->updated_at)) : now()->format('d-m-Y H:i'),
            'image_url' => Product::resolveImageUrl($product->fresh()),
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
                'movement_type' => StockMovement::movementTypeLabel($record->movement_type),
                'quantity_before' => number_format((float) $record->quantity_before, 3),
                'quantity_change' => '<span class="' . ($change >= 0 ? 'text-success' : 'text-danger') . '">' . $changeFormatted . '</span>',
                'quantity_after' => number_format((float) $record->quantity_after, 3),
                'weight' => $record->weight !== null ? number_format((float) $record->weight, 3) . ' kg' : '-',
                'reason' => $record->reason ?: '-',
                'admin_name' => $record->admin_name ?: __('inventory.system'),
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
