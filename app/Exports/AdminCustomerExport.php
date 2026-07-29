<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AdminCustomerExport implements FromCollection, WithHeadings
{
    protected $data;
    protected $header;

    public function __construct(Collection $data, array $header)
    {
        $this->data = $data;
        $this->header = $header;
    }

    public function collection()
    {
        return $this->data->values()->map(function ($user, $index) {
            $createdAt = $user->join_date ?? $user->created_at ?? null;
            if ($createdAt instanceof Carbon) {
                $createdAt = $createdAt->format('Y-m-d H:i:s');
            }

            return [
                $index + 1,
                $user->name,
                $user->email,
                $user->category,
                $user->shipping_address,
                $user->shipping_postcode,
                $user->shipping_state,
                $user->remark,
                $user->status,
                $createdAt,
            ];
        });
    }

    public function headings(): array
    {
        return $this->header;
    }
}
