<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use DB;

class DailySummaryStockReport implements FromCollection, WithHeadings, WithEvents, WithColumnWidths
{
    protected $i;
    protected $orders;

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $sheet = $event->sheet;

                foreach ($this->orders as $key => $order) {
                    $no = $this->i;

                    $sheet->setCellValue('A' . $no, $order->product_name);
                    $sheet->setCellValue('B' . $no, $order->sku);
                    $sheet->setCellValue('C' . $no, '');
                    $sheet->setCellValue('D' . $no, $order->quantity);

                    $this->i++;
                }

                // Make row bold
                $event->sheet->getDelegate()->getStyle('A1:D1')->getFont()->setBold(true);

                // Set BG color
                $event->sheet->getDelegate()->getStyle('A1:D1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('dee0bb');

                // Set Font color
                $event->sheet->getDelegate()->getStyle('A1:D1')->getFont()->getColor()->setARGB('000000');
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
            ->join('products', 'products.id', '=', 'order_products.product_id')
            ->select(
                'order_products.order_id',
                'order_products.product_name',
                'products.sku',
                DB::raw('SUM(order_products.quantity) AS quantity'),
            )
            ->whereBetween('order_products.created_at', [$startDate, $endDate . " 23:59:59"])
            ->when($request->id, function ($q) {
                return $q->where('order_products.order_id', request()->id);
            })
            ->when($request->status, function ($q) {
                return $q->where('orders.status', request()->status);
            })
            ->when($request->driver, function ($q) {
                return $q->where('orders.driver_id', request()->driver);
            })
            ->when($request->customer, function ($q) {
                return $q->where('orders.user_id', request()->customer);
            })
            ->when($request->area, function ($q) {
                return $q->where('orders.area', request()->area);
            })
            ->groupBy('products.sku')
            ->get();
        
        return $this->orders;
    }

    public function headings(): array
    {
        return [
            [
                'Item Name',
                'Item SKU',
                'Item Category',
                'Item Quantity',
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 20,
            'C' => 20,
            'D' => 20,
        ];
    }
}
