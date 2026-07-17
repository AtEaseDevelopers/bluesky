<?php

namespace App\Exports;

use App\Area;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use DB;

class DailySummaryReport implements FromCollection, WithHeadings, WithEvents, WithColumnWidths
{
    protected $i;
    protected $orders;

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $sheet = $event->sheet;

                $pre_order_id = null;
                $col_no = 1;

                foreach ($this->orders as $key => $order) {
                    $no = $this->i;

                    if ($pre_order_id == $order->id) {
                        $sheet->setCellValue('A' . $no, '');
                        $sheet->setCellValue('B' . $no, '');
                        $sheet->setCellValue('C' . $no, '');
                    } else {
                        $sheet->setCellValue('A' . $no, $col_no++);
                        $sheet->setCellValue('B' . $no, $order->created_at);
                        $sheet->setCellValue('C' . $no, $order->name);
                    }

                    $sheet->setCellValue('D' . $no, $order->product_name);
                    $sheet->setCellValue('E' . $no, $order->sku);
                    $sheet->setCellValue('F' . $no, '');
                    $sheet->setCellValue('G' . $no, $order->quantity);
                    $sheet->setCellValue('H' . $no, $order->weight);
                    $sheet->setCellValue('I' . $no, $order->order_weight);

                    $this->i++;

                    $pre_order_id = $order->id;
                }

                // Make row bold
                $event->sheet->getDelegate()->getStyle('A1:I1')->getFont()->setBold(true);

                // Set BG color
                $event->sheet->getDelegate()->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('dee0bb');

                // Set Font color
                $event->sheet->getDelegate()->getStyle('A1:I1')->getFont()->getColor()->setARGB('000000');
            }
        ];
    }

    public function collection()
    {
        $this->i = 2;

        $request = request();
        $orderId = $request->id;
        $fdate = $request->fdate;
        $tdate = $request->tdate;
        $status = $request->status;
        $driver = $request->driver;
        $customer = $request->customer;
        $area = $request->area;

        // format current date or from and to date
        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $startDate = min($startDate, $endDate);

        $this->orders = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->join('products', 'products.id', '=', 'order_products.product_id')
            ->select(
                'orders.id',
                'orders.created_at',
                'users.name',
                'order_products.product_name',
                'products.sku',
                'order_products.quantity',
                'order_products.weight',
                'orders.order_weight',
            )
            ->whereBetween('orders.created_at', [$startDate, $endDate . " 23:59:59"])
            ->when(
                $request->id, function ($q) {
                    return $q->where('orders.id', request()->id);
                }
            )
            ->when(
                $request->status, function ($q) {
                    return $q->where('orders.status', request()->status);
                }
            )
            ->when(
                $request->driver, function ($q) {
                    return $q->where('orders.driver_id', request()->driver);
                }
            )
            ->when(
                $request->customer, function ($q) {
                    return $q->where('orders.user_id', request()->customer);
                }
            )
            ->when(
                $areaFilter = Area::orderFilterValue($request->input('area')), function ($q) use ($areaFilter) {
                    return $q->where('orders.area', $areaFilter);
                }
            )
            ->get();

        return $this->orders;
    }

    public function headings(): array
    {
        return [
            [
                'No',
                'Order At',
                'Customer',
                'Item Name',
                'Item SKU',
                'Item Category',
                'Item Quantity',
                'Unit Weight',
                'Total Weight',
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 20,
            'C' => 15,
            'D' => 25,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 15,
        ];
    }
}
