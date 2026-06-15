<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AdminOrderExport implements FromCollection, WithHeadings
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
        return $this->data;
    }

    public function headings(): array
    {
        return $this->header;
    }
}
