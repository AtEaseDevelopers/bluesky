<?php

namespace App\Exports;

use App\Area;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class SqlDoExportReport implements FromCollection, WithHeadings, WithMapping
{
    protected $req;

    public function __construct($req) {
        $this->req = $req;
    }

    public function collection() {
        $request = $this->req;
        $orderId = $request->id;
        $fdate = $request->fdate;
        $tdate = $request->tdate;
        $status = $request->status;
        $driver = $request->driver;
        $customer = $request->customer;
        $area = Area::orderFilterValue($request->area);

        // format current date or from and to date
        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $startDate = min($startDate, $endDate);

        $orders = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->join('products', 'products.id', '=', 'order_products.product_id')
            ->select(
                'orders.id AS id',
                'orders.do_no AS do_no',
                'orders.created_at AS doc_date',
                'users.name AS customer_name',
                'users.sql_customer_code AS customer_code',
                'users.area',
                'users.billing_address',
                'users.billing_city',
                'users.billing_postcode',
                'users.billing_state',
                'users.attn_contact',
                'order_products.product_name',
                'products.sku AS product_sku',
                'products.description AS product_desc',
                'order_products.quantity AS qty',
                'order_products.weight AS weight',
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
            ->orderBy('orders.id', 'desc')
            ->get();

        $seq = 0;
        $order_id = null;
        for ($i=0; $i < count($orders); $i++) { 
            if ($order_id != null && $orders[$i]->id != $order_id) {
                $seq = 0;
            }
            $order_id = $orders[$i]->id;

            $seq++;
            $orders[$i]->seq = $seq;
        }

        return $orders;
    }

    public function map($order): array
    {
        return [
            $order->doc_date,
            $order->do_no,
            $order->customer_code, // customer's code -> add sql customer code in customer there
            null,
            null,
            null,
            null,
            $order->customer_name,
            $order->area,
            $order->billing_address,
            $order->billing_city,
            null,
            null,
            null,
            $order->billing_postcode,
            null,
            $order->billing_state,
            null,
            $order->attn_contact,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $order->seq,
            null,
            $order->product_sku,
            $order->product_desc,
            $order->qty ?? $order->weight,
            $order->qty == null ? 'KG' : 'Qty',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'Penang',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ];
    }

    public function headings(): array
    {
        return [
            'DocDate',
            'DocNo(20)',
            'Code(10)',
            'EIVDATETIME',
            'IRBM_UUID',
            'IRBM_LONGID',
            'IRBM_STATUS',
            'CompanyName(100)',
            'Area',
            'ADDRESS1(60)',
            'ADDRESS2(60)',
            'ADDRESS3(60)',
            'ADDRESS4(60)',
            'ADDRESS5(60)',
            'POSTCODE(10)',
            'CITY(50)',
            'STATE(50)',
            'COUNTRY(2)',
            'PHONE1(200)',
            'Agent(10)',
            'TERMS(10)',
            'Description_HDR(200)',
            'Project_HDR(20)',
            'CC(200)',
            'SALESTAXNO(25)',
            'SERVICETAXNO(25)',
            'TIN(14)',
            'IDTYPE',
            'IDNO(20)',
            'TOURISMNO(17)',
            'SIC(10)',
            'INCOTERMS(3)',
            'SUBMISSIONTYPE',
            'SEQ', // 1,2,3 refresh, every new order -> order product 
            'ACCOUNT(10)',
            'ItemCode(30)',
            'Description_DTL(200)',
            'Qty',
            'UOM(10)',
            'UnitPrice',
            'DISC(20)',
            'Tax(10)',
            'TaxInclusive',
            'TaxAmt',
            'Amount',
            'IRBM_CLASSIFICATION(3)',
            'TAXEXEMPTIONREASON(300)',
            'Location(20)',
            'Batch(30)',
            'Project_DTL(20)',
            'Remark1(200)',
            'Remark2(200)',
            'FromDocType(2)',
            'FromDocNo(20)',
            'FromSeqNo',
        ];
    }
}
