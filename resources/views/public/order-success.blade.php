@extends('layouts.public')
@section('title', 'Order Submitted')
@section('content')
    <div class="row mb-5">
        <div class="col-md-8 mx-auto">
            <div class="card shadow no-border">
                <div class="card-body text-center py-5">
                    <h5 class="mb-3">Thank you — your order has been submitted</h5>
                    <p class="text-muted mb-2">Order reference: <strong>#{{ $order->id }}</strong></p>
                    <p class="text-muted mb-0">We will contact you shortly. This ordering link has now expired.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
