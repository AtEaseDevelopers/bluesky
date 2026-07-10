<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AutoCountSyncLog extends Model
{
    protected $table = 'autocount_sync_logs';

    protected $fillable = [
        'order_id',
        'invoice_number',
        'sync_status',
        'response_message',
        'error_message',
        'admin_id',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
