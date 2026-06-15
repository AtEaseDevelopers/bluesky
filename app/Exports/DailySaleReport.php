<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use DB;

class DailySaleReport implements FromCollection, WithHeadings, WithEvents, WithColumnWidths
{
    protected $i;
    protected $orders;

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $sheet = $event->sheet;

                $total_sales_count = 0;
                $total_quantity_sold = 0;
                $total_sales = 0;
                $col_no = 1;
                $pre_order_id = null;

                foreach ($this->orders as $key => $order) {
                    $no = $this->i;

                    if ($pre_order_id == $order->id) {
                        $sheet->setCellValue('A' . $no, '');
                        $sheet->setCellValue('B' . $no, '');
                        $sheet->setCellValue('C' . $no, '');
                    } else {
                        $total_sales_count++;
                        $sheet->setCellValue('A' . $no, $col_no++);
                        $sheet->setCellValue('B' . $no, $order->created_at);
                        $sheet->setCellValue('C' . $no, $order->name);
                    }

                    $sheet->setCellValue('D' . $no, $order->product_name);
                    $sheet->setCellValue('E' . $no, $order->sku);
                    $sheet->setCellValue('F' . $no, '');
                    $sheet->setCellValue('G' . $no, $order->quantity);
                    $sheet->setCellValue('H' . $no, $order->unit_price);
                    $sheet->setCellValue('I' . $no, $order->price);
                    $sheet->setCellValue('J' . $no, $order->payment_method);

                    if ($pre_order_id == $order->id) {
                        $sheet->setCellValue('K' . $no, '');
                        $sheet->setCellValue('L' . $no, '');
                        $sheet->setCellValue('M' . $no, '');
                        $sheet->setCellValue('N' . $no, '');
                    } else {
                        $sheet->setCellValue('K' . $no, $order->area);
                        $sheet->setCellValue('L' . $no, $order->billing_address);
                        $sheet->setCellValue('M' . $no, $order->shipping_address);
                        $sheet->setCellValue('N' . $no, $order->updated_at);
                    }

                    $this->i++;

                    $total_quantity_sold += $order->quantity;
                    $total_sales += $order->price;
                    $pre_order_id = $order->id;
                }

                $no = $no + 3;

                $sheet->setCellValue('A' . $no, 'TOTAL SALES COUNT:');
                $sheet->setCellValue('B' . $no, $total_sales_count);

                $sheet->setCellValue('F' . $no, 'TOTAL QUANTITY SOLD:');
                $sheet->setCellValue('G' . $no, $total_quantity_sold);

                $sheet->setCellValue('H' . $no, 'TOTAL SALES:');
                $sheet->setCellValue('I' . $no, $total_sales);

                // Make row bold
                $event->sheet->getDelegate()->getStyle('A1:N1')->getFont()->setBold(true);

                // Set BG color
                $event->sheet->getDelegate()->getStyle('A1:N1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('dee0bb');

                // Set Font color
                $event->sheet->getDelegate()->getStyle('A1:N1')->getFont()->getColor()->setARGB('000000');
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
                'order_products.unit_price',
                'order_products.price',
                'orders.payment_method',
                'orders.area',
                DB::raw(
                    "CONCAT(
                        orders.billing_address, ' ', 
                        orders.billing_city, ' ', 
                        orders.billing_postcode, ' ', 
                        orders.billing_state
                    ) AS billing_address"
                ),
                DB::raw(
                    "CONCAT(
                    orders.shipping_address, ' ', 
                    orders.shipping_city, ' ', 
                    orders.shipping_postcode, ' ', 
                    orders.shipping_state
                ) AS shipping_address"
                ),
                'orders.updated_at',
            )
            ->whereBetween('orders.created_at', [$startDate, $endDate . " 23:59:59"])
            ->when(
                    $orderId, function ($q) use ($orderId) {
                        return $q->where('orders.id', $orderId);
                }
            )
            ->when(
                $status, function ($q) use ($status) {
                    return $q->where('orders.status', $status);
                }
            )
            ->when(
                $driver, function ($q) use ($driver) {
                    return $q->where('orders.driver_id', $driver);
                }
            )
            ->when(
                $customer, function ($q) use ($customer) {
                    return $q->where('orders.user_id', $customer);
                }
            )
            ->when(
                $area, function ($q) use ($area) {
                    return $q->where('orders.area', $area);
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
                'Item Unit Price',
                'Item Total Price',
                'Payment Method',
                'Area',
                'Billing Address',
                'Shipping Address',
                'Last Updated At',
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
            'J' => 15,
            'K' => 15,
            'L' => 15,
            'M' => 20,
            'N' => 20,
        ];
    }
}
