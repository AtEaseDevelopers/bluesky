@extends('layouts.admin')
@section('title', 'Order Summary')
@section('content')

    <div class="row mb-5">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <h4>Order Summary</h4>
                <div>
                    <a href="{{ route('admin.orders') }}" class="btn btn-secondary">
                        <i class="fa fa-chevron-circle-left" aria-hidden="true"></i> {{ __('Back To Order List') }}
                    </a>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Action
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                @if (Auth::guard('web_admin')->user()->role == 'superadmin' && $order->status != Order::$status['completed'])
                                    <a href="{{ route('admin.orders.edit', encrypt($order->id)) }}" class="dropdown-item">
                                        Edit Order
                                    </a>
                                @endif
                            </li>
                            <li>
                                @if ($order->status != Order::$status['cancelled'] && $order->status != Order::$status['completed'])
                                    <a href="javascript:void(0);" class="dropdown-item" id="cancelOrderBtn">
                                        Cancel Order
                                    </a>
                                @endif
                            </li>
                            <li>
                                @if ($order->status == Order::$status['processing'])
                                    <a href="javascript:void(0);" class="dropdown-item" id="completedOrderBtn">
                                        Order Delivered & Completed
                                    </a>
                                @endif
                            </li>
                            <li>
                                @if ($order->status == Order::$status['completed'])
                                    <a href="javascript:void(0);" class="dropdown-item" id="processingOrderBtn">
                                        Change Order back to Processing
                                    </a>
                                @endif
                            </li>
                            <li>
                                <a href="{{ route('admin.order.invoice', $order->id) }}#toolbar=0"
                                data-url="{{ route('admin.order.invoice', $order->id) }}"
                                class="dropdown-item view-pdf">
                                    View Invoice
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.order.invoice2', $order->id) }}#toolbar=0"
                                data-url="{{ route('admin.order.invoice2', $order->id) }}"
                                class="dropdown-item view-pdf">
                                    View Invoice without price
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('admin.order.delivery-order', $order->id) }}#toolbar=0"
                                data-url="{{ route('admin.order.delivery-order', $order->id) }}"
                                class="dropdown-item view-pdf">
                                    View DO
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
    
            <div class="order-summary">
                <p><strong>Order ID:</strong> {{ $order->id }}</p>
                <p><strong>Order Date:</strong> {{ Carbon\Carbon::parse($order->created_at)->format('Y-m-d h:i a') }}</p>
                <p><strong>Order Weight:</strong> {{ $order->order_weight }}</p>
                <p><strong>Attn:</strong><br/>
                    {{ $order->attn_name }}<br/>
                    {{ $order->attn_contact }}<br/>
                </p>
                <p><strong>Area:</strong> {{ $order->area }}</p>
                <p>
                    <strong>Billing Address:</strong><br/>
                    {!! $order->billing_address !!}
                </p>
                <p>
                    <strong>Shipping Address:</strong><br/>
                    {!! $order->shipping_address !!}
                </p>
            </p>
            <h3>Status: <strong>{{ __('order.status.'.$order->status) }}</strong></h3>
            <p><strong>Last Updated Date:</strong> {{ Carbon\Carbon::parse($order->updated_at)->format('Y-m-d h:i a') }}</p>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Price (RM)</th>
                                <th>Quantity</th>
                                <th>Weight</th>
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
                                        @if ($product->remark)
                                            Customer Remark: {{ $product->remark }}<br />
                                        @endif
                                    </td>
                                    <td>
                                        {{ number_format($product->unit_price, 2) }}
                                    </td>
                                    <td>{{ $product->show_qty == true ? $product->quantity : '' }}</td>
                                    <td>{{ $product->show_weight == true ? (($product->quantity != null && $product->product_weight != null ? $product->product_weight * $product->quantity : $product->weight) . ' KG') : '' }}</td>
                                    <td>
                                        {{ number_format($product->price, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="4"><strong>Grand Total</strong></td>
                                <td colspan="2"><strong>{{ number_format($order->total_price, 2) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <!--<p><strong>Payment Method:</strong> {{ __('user.payment_method.'.$order->payment_method) }}</p>-->
                    @if($order->payment_method == User::$payment_method['bank-transfer'])
                    <p><strong>Transfer Slip:</strong></p>
                    <div class="card p-3">
                        <img style="width: 70%;" src="{{ $product->transfer_slip_url }}" />
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">PDF Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="pdfFrame" style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <a id="downloadLink" class="btn btn-primary" href="#" download>
                        <i class="fa fa-download"></i> Download PDF
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script src="{{ asset('assets/js/sweetalert.min.js') }}"></script>
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

            $("#processingOrderBtn").click(function(){
                // Display SweetAlert confirmation
                Swal.fire({
                    title: 'Processing Order',
                    text: 'Please confirm to change the order status.',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            type: "POST",
                            url: "{{ url('/admin/order/update-status/'.$product->order_id) }}", // Use the URL from the data attribute
                            data: {
                                _token: "{{ csrf_token() }}",
                                status:"{{ Order::$status['processing'] }}"
                            },
                            success: function (response) {
                                data = $.parseJSON(response);
                                if(data.success){
                                    Swal.fire('Order Updated', 'The order status has been updated successfully', 'success').then(function(){
                                        window.location.reload();
                                    });
                                }else{
                                    Swal.fire('Error', 'An error occurred while updating the order status', 'error');
                                }
                            },
                            error: function (error) {
                                // Handle errors, e.g., show an error message
                                Swal.fire('Error', 'An error occurred while updating the order status', 'error');
                            }
                        });
                    }
                });
            });
            
            $("#completedOrderBtn").click(function(){
                // Display SweetAlert confirmation
                Swal.fire({
                    title: 'Order Completed',
                    text: 'Please confirm to change the order status.',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            type: "POST",
                            url: "{{ url('/admin/order/update-status/'.$product->order_id) }}", // Use the URL from the data attribute
                            data: {
                                _token: "{{ csrf_token() }}",
                                status:"{{ Order::$status['completed'] }}"
                            },
                            success: function (response) {
                                data = $.parseJSON(response);
                                if(data.success){
                                    Swal.fire('Order Updated', 'The order status has been updated successfully', 'success').then(function(){
                                        window.location.reload();
                                    });
                                }else{
                                    Swal.fire('Error', 'An error occurred while updating the order status', 'error');
                                }
                            },
                            error: function (error) {
                                // Handle errors, e.g., show an error message
                                Swal.fire('Error', 'An error occurred while updating the order status', 'error');
                            }
                        });
                    }
                });
            });

            $("#cancelOrderBtn").click(function(){
                // Display SweetAlert confirmation
                Swal.fire({
                    title: 'Cancel Order',
                    text: 'Confirm to cancel this order? Kindly noted that this action cannot be undo.',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#28a745',
                    confirmButtonText: 'Yes'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            type: "POST",
                            url: "{{ url('/admin/order/update-status/'.$product->order_id) }}", // Use the URL from the data attribute
                            data: {
                                _token: "{{ csrf_token() }}",
                                status:"{{ Order::$status['cancelled'] }}"
                            },
                            success: function (response) {
                                data = $.parseJSON(response);
                                if(data.success){
                                    Swal.fire('Order Updated', 'The order has been cancelled.', 'success').then(function(){
                                        window.location.reload();
                                    });
                                }else{
                                    Swal.fire('Error', 'An error occurred while updating the order status', 'error');
                                }
                            },
                            error: function (error) {
                                // Handle errors, e.g., show an error message
                                Swal.fire('Error', 'An error occurred while updating the order status', 'error');
                            }
                        });
                    }
                });
            });

            // Handle click on .view-pdf buttons
            $(".view-pdf").click(function(e) {
                e.preventDefault();
                
                // Get the PDF URL from the link's href (remove #toolbar=0 for download)
                var pdfUrl = $(this).attr("href").replace('#toolbar=0', '');
                var downloadUrl = $(this).data('url').replace('#toolbar=0', '');
                
                // Set the PDF source in the iframe
                $("#pdfFrame").attr("src", $(this).attr("href"));
                $("#downloadLink").attr("href", downloadUrl + '/download');
                
                // Show the PDF modal
                $("#pdfModal").modal("show");
            });

            // Clean up when modal is closed
            $("#pdfModal").on('hidden.bs.modal', function() {
                $("#pdfFrame").attr("src", '');
            });
        });
    </script>

@endsection
