@extends('layouts.member')
@section('title', 'Order Summary')
@section('css')

    <style>
        /* Existing styles here */

        /* New styles for order summary */
        .order-summary {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .order-summary h5 {
            margin-bottom: 20px;
        }

        .order-summary ul {
            list-style: none;
            padding: 0;
        }

        .order-summary li {
            margin-bottom: 10px;
        }
    </style>

@endsection
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h4 class="mb-4">Order Summary</h4>
                <div>
                    <a href="{{ route('member.orders') }}" class="btn btn-outline-primary me-3 mb-1">
                        <i class="fa fa-chevron-circle-left" aria-hidden="true"></i> {{ __('Go To Order List') }}
                    </a>
                    @if (in_array($order->status, ['cancelled', 'delivering', 'completed']))
                        @if ($customer->invoice_visibility)
                            <a href="{{ $invoice_url }}" class="btn btn-primary view-pdf mb-1">
                                <i class="fa fa-eye"></i> {{ __('order.file.invoice') }}
                            </a>
                        @endif
                        <a href="{{ $delivery_order_url }}#toolbar=0" data-url="{{ $delivery_order_download_url }}" class="btn btn-primary mb-1 view-pdf">
                            <i class="fa fa-car"></i> {{ __('order.file.delivery-order') }}
                        </a>
                    @endif
                </div>
            </div>
            <div class="card no-border shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <p><strong>Order ID:</strong> {{ $order->id }}</p>
                            <p><strong>Order Date:</strong> {{ $order->created_at }}</p>
                            <p><strong>Order Weight:</strong> {{ $order->order_weight }}</p>
                            <p>
                                <strong>Attn:</strong><br/>
                                {{ $order->attn_name }}<br/>
                                {{ $order->attn_contact }}
                            </p>
                            <p><strong>Status:</strong> {{ __('order.status.'.$order->status) }}</p>
                        </div>
                        <div>
                            <p>
                                <strong>Billing Address:</strong><br/>
                                {!! $order->billing_address !!}
                            </p>
                            <p>
                                <strong>Shipping Address:</strong><br/>
                                {!! $order->shipping_address !!}
                            </p>
                        </div>
                    </div>
                    <div class="row mb-5">
                        <div class="col-md-12">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-right">Price (RM)</th>
                                        <th>Quantity/Weight</th>
                                        <th class="text-right">Total (RM)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($products as $product)
                                        <tr>
                                            <td>
                                                <strong>{{ $product->name }}</strong><br />
                                                @foreach($product->options as $opt => $opt_itm)
                                                    {{ $opt }}: {{ $opt_itm }}<br />
                                                @endforeach
                                                @if($product->remark)
                                                    Remark: {{ $product->remark }}<br />
                                                @endif
                                            </td>
                                            <td align="right">
                                                @if ($customer->price_permission)
                                                    {{ number_format($product->unit_price, 2) }}
                                                @else
                                                -
                                                @endif
                                            </td>
                                            <td>{{ $product->quantity ?? ($product->weight . ' KG') }}</td>
                                            <td align="right">
                                                @if ($customer->price_permission)
                                                    {{ number_format($product->price, 2) }}
                                                @else
                                                -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                @if ($customer->price_permission)
                                    <tfoot>
                                        <tr>
                                            <td><strong>Grand Total</strong></td>
                                            <td align="right" colspan="3"><strong>{{ number_format($order->total_price, 2) }}</strong></td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                            <!--<div class="d-flex justify-content-end">-->
                            <!--    <p><strong>Payment Method:</strong> {{ __('user.payment_method.'.$order->payment_method) }}</p>-->
                            <!--</div>-->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="pdfModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PDF Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <iframe id="pdfFrame" style="width: 100%; height: 80vh;"></iframe>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script>
        $(document).ready(function() {
            // Function to show/hide transfer_slip based on payment_method
            function toggleTransferSlip() {
                var paymentMethod = $('#payment_method').val();

                // Show transferSlipGroup only for 'bank-transfer' payment method
                if (paymentMethod === 'bank-transfer') {
                    $('#transferSlipGroup').show().attr('required', true);
                    $('#transfer_slip').attr('required', true);
                } else {
                    $('#transferSlipGroup').hide().removeAttr('required');
                    $('#transfer_slip').removeAttr('required');
                }
            }

            // Call toggleTransferSlip on page load
            toggleTransferSlip();

            // Bind toggleTransferSlip to payment_method change event
            $('#payment_method').change(function() {
                toggleTransferSlip();
            });

            // Handle click on .view-pdf buttons
            $(".view-pdf").click(function() {
                // Get the PDF URL from the button's data attribute
                var pdfUrl = $(this).attr("href");

                // Set the PDF source in the iframe
                $("#pdfFrame").attr("src", pdfUrl);

                // Show the PDF modal
                $("#pdfModal").modal("show");

                // Prevent the default behavior of the link
                return false;
            });
        });
    </script>

@endsection
