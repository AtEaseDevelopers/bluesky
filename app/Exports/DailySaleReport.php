<?php

namespace App\Exports;

use App\Services\DailySalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class DailySaleReport implements FromCollection, WithHeadings, WithEvents, WithColumnWidths
{
    protected int $i = 2;

    protected Collection $orders;

    protected array $paymentSummary;

    public function __construct(
        protected Request $request,
        protected DailySalesReportService $reportService
    ) {
        $this->orders = $this->reportService->salesLines($this->request);
        $this->paymentSummary = $this->reportService->paymentCollectionSummary($this->request);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                $totalSalesCount = 0;
                $totalQuantitySold = 0;
                $totalSales = 0.0;
                $colNo = 1;
                $preOrderId = null;
                $no = $this->i;

                foreach ($this->orders as $order) {
                    $no = $this->i;

                    if ($preOrderId == $order->id) {
                        $sheet->setCellValue('A' . $no, '');
                        $sheet->setCellValue('B' . $no, '');
                        $sheet->setCellValue('C' . $no, '');
                        $sheet->setCellValue('D' . $no, '');
                    } else {
                        $totalSalesCount++;
                        $sheet->setCellValue('A' . $no, $colNo++);
                        $sheet->setCellValue('B' . $no, $order->created_at);
                        $sheet->setCellValue('C' . $no, $order->name);
                        $sheet->setCellValue('D' . $no, __('order.status.' . $order->status));
                    }

                    $sheet->setCellValue('E' . $no, $order->product_name);
                    $sheet->setCellValue('F' . $no, $order->sku);
                    $sheet->setCellValue('G' . $no, $order->quantity);
                    $sheet->setCellValue('H' . $no, $order->unit_price);
                    $sheet->setCellValue('I' . $no, $order->price);
                    $sheet->setCellValue('J' . $no, $this->reportService->paymentMethodLabel($order->payment_method));

                    if ($preOrderId == $order->id) {
                        $sheet->setCellValue('K' . $no, '');
                        $sheet->setCellValue('L' . $no, '');
                        $sheet->setCellValue('M' . $no, '');
                        $sheet->setCellValue('N' . $no, '');
                    } else {
                        $sheet->setCellValue('K' . $no, $order->area);
                        $sheet->setCellValue(
                            'L' . $no,
                            trim($order->billing_address . ' ' . $order->billing_city . ' ' . $order->billing_postcode . ' ' . $order->billing_state)
                        );
                        $sheet->setCellValue(
                            'M' . $no,
                            trim($order->shipping_address . ' ' . $order->shipping_city . ' ' . $order->shipping_postcode . ' ' . $order->shipping_state)
                        );
                        $sheet->setCellValue('N' . $no, $order->updated_at);
                    }

                    $this->i++;
                    $totalQuantitySold += $order->quantity;
                    $totalSales += (float) $order->price;
                    $preOrderId = $order->id;
                }

                $no = max($no, 1) + 3;

                $sheet->setCellValue('A' . $no, 'TOTAL SALES COUNT:');
                $sheet->setCellValue('B' . $no, $totalSalesCount);
                $sheet->setCellValue('F' . $no, 'TOTAL QUANTITY SOLD:');
                $sheet->setCellValue('G' . $no, $totalQuantitySold);
                $sheet->setCellValue('H' . $no, 'TOTAL SALES:');
                $sheet->setCellValue('I' . $no, $totalSales);

                $no += 3;
                $sheet->setCellValue('A' . $no, 'Payment Collection Summary');
                $event->sheet->getDelegate()->getStyle('A' . $no)->getFont()->setBold(true);
                $no++;

                $sheet->setCellValue('A' . $no, 'Payment Method');
                $sheet->setCellValue('B' . $no, 'Payment Count');
                $sheet->setCellValue('C' . $no, 'Total Collected (RM)');
                $event->sheet->getDelegate()->getStyle('A' . $no . ':C' . $no)->getFont()->setBold(true);
                $no++;

                foreach ($this->reportService->summaryCategoryLabels() as $key => $label) {
                    $sheet->setCellValue('A' . $no, $label);
                    $sheet->setCellValue('B' . $no, $this->paymentSummary[$key]['count'] ?? 0);
                    $sheet->setCellValue('C' . $no, number_format($this->paymentSummary[$key]['total'] ?? 0, 2, '.', ''));
                    $no++;
                }

                $sheet->setCellValue('A' . $no, $this->paymentSummary['grand_total']['label'] ?? 'Grand Total');
                $sheet->setCellValue('B' . $no, $this->paymentSummary['grand_total']['count'] ?? 0);
                $sheet->setCellValue('C' . $no, number_format($this->paymentSummary['grand_total']['total'] ?? 0, 2, '.', ''));
                $event->sheet->getDelegate()->getStyle('A' . $no . ':C' . $no)->getFont()->setBold(true);

                $event->sheet->getDelegate()->getStyle('A1:N1')->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle('A1:N1')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('dee0bb');
                $event->sheet->getDelegate()->getStyle('A1:N1')->getFont()->getColor()->setARGB('000000');
            },
        ];
    }

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return [
            [
                'No',
                'Order At',
                'Customer',
                'Order Status',
                'Item Name',
                'Item SKU',
                'Item Quantity',
                'Item Unit Price',
                'Item Total Price',
                'Payment Method',
                'Area',
                'Billing Address',
                'Shipping Address',
                'Last Updated At',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 20,
            'C' => 20,
            'D' => 15,
            'E' => 25,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 15,
            'J' => 15,
            'K' => 15,
            'L' => 25,
            'M' => 25,
            'N' => 20,
        ];
    }
}
