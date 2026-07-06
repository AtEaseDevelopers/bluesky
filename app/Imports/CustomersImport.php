<?php

namespace App\Imports;

use App\Helper;
use App\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\DB;
use Exception;

class CustomersImport implements ToCollection
{
    public function collection(Collection $collection)
    {
        $status = User::$user_status['active'];

        // Start the transaction
        DB::beginTransaction();
        try {
            foreach ($collection as $key => $row) {
                if ($key > 0) {
                    $login_code = User::generateLoginCode();
                    // Check for duplicate email or customer code (add your own checks here)
                    if ($row[2] == '') {
                         $duplicateUser = User::where('sql_customer_code', $row[1])->first();
                    } else {
                         $duplicateUser = User::where('email', $row[2])->orWhere('sql_customer_code', $row[1])->first();
                    }
                    
                    if ($duplicateUser) {
                        // If duplicate is found, throw an exception to trigger the rollback
                        throw new Exception("Duplicate user found: " . $row[0]);
                    }

                    // Payment methods
                    $methods = explode(',', $row[18]);

                    // Create the user
                    User::create(
                        [
                            'name' => $row[0],
                            'sql_customer_code' => $row[1],
                            'email' => $row[2] == '' ? null : $row[2],
                            'password' => \Hash::make($row[20]),
                            'category' => $row[3],
                            'attn_name' => $row[4],
                            'attn_contact' => $row[5],
                            "login_code" => $login_code,
                            'area' => $row[9],
                            'billing_address' => $row[10],
                            'billing_city' => $row[11],
                            'billing_postcode' => $row[12],
                            'billing_state' => $row[13],
                            'shipping_address' => $row[14],
                            'shipping_city' => $row[15],
                            'shipping_postcode' => $row[16],
                            'shipping_state' => $row[17],
                            'payment_method' => json_encode($methods),
                            'remark' => $row[19],
                            'status' => $status,
                            'price_permission' => (strtolower($row[6]) == 'yes') ? 1 : 0,
                            'invoice_visibility' => (strtolower($row[7]) == 'yes') ? 1 : 0,
                            'invoice_price_permission' => (strtolower($row[8]) == 'yes') ? 1 : 0,
                        ]
                    );
                }
            }

            // If everything is fine, commit the transaction
            DB::commit();
            // Import the file with transaction handling inside the import
            return back()->with('success', 'Customers imported successfully!');
        } catch (Exception $e) {
            // Rollback the transaction if there is any error
            DB::rollBack();

            // Return or log the error message
            return back()->with('warning', 'Import failed: ' . $e->getMessage());
        }
    }
}
