<?php

namespace App\Exports;

use App\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AdminCustomerExport implements FromCollection, WithHeadings
{
    public function __construct(protected Collection $users)
    {
    }

    public function collection()
    {
        return $this->users->values()->map(function ($user, $index) {
            /** @var User $user */
            return [
                $index + 1,
                $user->name,
                $user->isCreditCustomer()
                    ? __('customers.customer_type_credit')
                    : __('customers.customer_type_cod'),
                $user->isCreditCustomer()
                    ? $user->paymentTermLabel()
                    : __('customers.payment_term_not_applicable'),
                $user->category,
                $user->billing_address,
                $user->shipping_address,
                $user->attn_contact,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            __('customers.name'),
            __('customers.customer_type'),
            __('customers.payment_term'),
            __('customers.category'),
            __('customers.billing_address'),
            __('customers.shipping_address'),
            __('customers.attn_contact'),
        ];
    }
}
