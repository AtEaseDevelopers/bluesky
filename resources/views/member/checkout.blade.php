@extends('layouts.member')
@section('title', __('orders.member.checkout.title'))
@section('css')

@endsection
@section('content')

    <div class="row mb-5">
        <div class="col-md-8">
            <div class="card no-border shadow">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('orders.member.checkout.title') }}</h5>
                    <form action="" method="POST" enctype="multipart/form-data" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_name">{{ __('orders.attn_name') }} @if($isGuest ?? false)<span class="text-danger ml-1">*</span>@endif</label>
                                    <input type="text" class="form-control" name="attn_name" id="attn_name" value="{{ old('attn_name')? : $customer->attn_name }}" placeholder="{{ ($isGuest ?? false) ? __('orders.member.checkout.attn_name_placeholder_required') : __('orders.attn_name_placeholder') }}" @if($isGuest ?? false) required @endif>
                                    @error('attn_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('attn_name') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="attn_contact">{{ __('orders.attn_contact') }} @if($isGuest ?? false)<span class="text-danger ml-1">*</span>@endif</label>
                                    <input type="text" class="form-control" name="attn_contact" id="attn_contact" value="{{ old('attn_contact')? : $customer->attn_contact }}" placeholder="{{ ($isGuest ?? false) ? __('orders.member.checkout.attn_contact_placeholder_required') : __('orders.attn_contact_placeholder') }}" @if($isGuest ?? false) required @endif>
                                    @error('attn_contact')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('attn_contact') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        @php
                            $selectedContactMethod = old('contact_method', $customer->contact_method ?? 'whatsapp');
                        @endphp
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="mb-2 d-block">{{ __('orders.contact_using') }}</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="contact_method" id="contact_method_whatsapp" value="whatsapp" {{ $selectedContactMethod === 'whatsapp' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="contact_method_whatsapp">{{ __('orders.contact_method.whatsapp') }}</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="contact_method" id="contact_method_wechat" value="wechat" {{ $selectedContactMethod === 'wechat' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="contact_method_wechat">{{ __('orders.contact_method.wechat') }}</label>
                                    </div>
                                    @error('contact_method')
                                        <span class="text-danger d-block" role="alert">
                                            <strong>{{ $errors->first('contact_method') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row" id="wechatIdGroup" style="{{ $selectedContactMethod === 'wechat' ? '' : 'display:none;' }}">
                            <div class="col-md-6">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="wechat_id">{{ __('orders.wechat_id') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="wechat_id" id="wechat_id" value="{{ old('wechat_id', $customer->wechat_id) }}" placeholder="{{ __('orders.wechat_id_placeholder') }}">
                                    @error('wechat_id')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('wechat_id') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <h6 class="card-subtitle my-3 text-body-secondary">{{ ($isGuest ?? false) ? __('customers.pos.delivery_info') : __('orders.member.checkout.billing_info') }}</h6>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="billing_address">{{ ($isGuest ?? false) ? __('customers.pos.delivery_address') : __('orders.billing_address') }}<span class="text-danger ml-1">*</span></label>
                                    <textarea id="billing_address" name="billing_address" value="{{ old('billing_address')? : $customer->billing_address }}" class="form-control" rows="3" placeholder="{{ __('orders.billing_address_placeholder') }}" required>{{ old('billing_address')? : $customer->billing_address }}</textarea>
                                    @error('billing_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('billing_address') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        @unless($isGuest ?? false)
                        <h6 class="card-subtitle my-3 text-body-secondary">{{ __('orders.member.checkout.payment_heading') }}</h6>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-info mb-3">
                                    <i class="fa fa-money" aria-hidden="true"></i>
                                    {{ $user->isCreditCustomer() ? __('orders.member.checkout.credit_delivery_intro') : __('orders.member.checkout.cod_intro') }}
                                </div>
                                <div class="form-group mb-4">
                                    <label class="mb-2 d-block">{{ __('orders.member.checkout.cod_payment_label') }} <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach ($codDeliveryMethods ?? \App\OrderPayment::codDeliveryPreferenceOptions() as $methodKey => $methodLabel)
                                            @php
                                                $checkoutLabelKey = 'orders.member.checkout.cod_methods.' . $methodKey;
                                                $displayLabel = __($checkoutLabelKey);
                                                if ($displayLabel === $checkoutLabelKey) {
                                                    $displayLabel = $methodLabel;
                                                }
                                            @endphp
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="payment_method" id="cod_payment_{{ $methodKey }}" value="{{ $methodKey }}" {{ old('payment_method', 'cash') === $methodKey ? 'checked' : '' }} required>
                                                <label class="form-check-label" for="cod_payment_{{ $methodKey }}">{{ $displayLabel }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <small class="text-muted d-block mt-2">{{ $user->isCreditCustomer() ? __('orders.member.checkout.credit_delivery_payment_help') : __('orders.member.checkout.cod_payment_help') }}</small>
                                    @error('payment_method')
                                        <span class="text-danger d-block" role="alert">
                                            <strong>{{ $errors->first('payment_method') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        @endunless

                        @unless($isGuest ?? false)
                        <h6 class="card-subtitle my-3 text-body-secondary">{{ __('orders.delivery_slot') }}</h6>
                        <div class="row">
                            <div class="col-md-12">
                                @if (empty($deliveryDates))
                                    <div class="alert alert-warning">
                                        {{ __('orders.no_delivery_dates') }}
                                    </div>
                                @else
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-4">
                                            <label class="mb-2" for="delivery_date">{{ __('orders.delivery_date') }} <span class="text-danger">*</span></label>
                                            <select name="delivery_date" id="delivery_date" class="form-select" required>
                                                <option value="">{{ __('orders.choose_delivery_date') }}</option>
                                                @foreach ($deliveryDates as $date)
                                                    <option value="{{ $date }}" {{ old('delivery_date') == $date ? 'selected' : '' }}>
                                                        {{ \Carbon\Carbon::parse($date)->format('d M Y (l)') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('delivery_date')
                                                <span class="text-danger"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-4">
                                            <label class="mb-2" for="delivery_slot_id">{{ __('orders.delivery_time') }} <span class="text-danger">*</span></label>
                                            <select name="delivery_slot_id" id="delivery_slot_id" class="form-select" required disabled>
                                                <option value="">{{ __('orders.choose_delivery_time') }}</option>
                                            </select>
                                            @error('delivery_slot_id')
                                                <span class="text-danger"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <h6 class="card-subtitle my-3 text-body-secondary">{{ __('orders.member.checkout.shipping_info') }}</h6>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-4">
                                    <label class="mb-2" for="shipping_address">{{ __('orders.shipping_address') }}</label>
                                    <textarea id="shipping_address" name="shipping_address" value="{{ old('shipping_address')? : $customer->shipping_address }}" class="form-control" rows="3" placeholder="{{ __('orders.shipping_address_placeholder') }}" >{{ old('shipping_address')? : $customer->shipping_address }}</textarea>
                                    @error('shipping_address')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $errors->first('shipping_address') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        @endunless
                        @if($isGuest ?? false)
                            <div class="alert alert-info mb-3">
                                <i class="fa fa-money" aria-hidden="true"></i>
                                {{ __('orders.member.checkout.cod_guest_intro') }}
                            </div>
                            <div class="form-group mb-4">
                                <label class="mb-2 d-block">{{ __('orders.member.checkout.cod_payment_label') }} <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-3">
                                    @foreach (\App\OrderPayment::codDeliveryPreferenceOptions() as $methodKey => $methodLabel)
                                        @php
                                            $checkoutLabelKey = 'orders.member.checkout.cod_methods.' . $methodKey;
                                            $displayLabel = __($checkoutLabelKey);
                                            if ($displayLabel === $checkoutLabelKey) {
                                                $displayLabel = $methodLabel;
                                            }
                                        @endphp
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="guest_cod_payment_{{ $methodKey }}" value="{{ $methodKey }}" {{ old('payment_method', 'cash') === $methodKey ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="guest_cod_payment_{{ $methodKey }}">{{ $displayLabel }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <small class="text-muted d-block mt-2">{{ __('orders.member.checkout.cod_payment_help') }}</small>
                                @error('payment_method')
                                    <span class="text-danger d-block" role="alert">
                                        <strong>{{ $errors->first('payment_method') }}</strong>
                                    </span>
                                @enderror
                            </div>
                        @endif
                        <div class="alert alert-light border mt-3 mb-0">
                            @include('partials.subject_to_availability')
                            <span class="d-block mt-1 small text-muted">{{ __('ui.storefront.subject_to_availability_note') }}</span>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ $portal['cart_url'] }}" class="btn btn-outline-primary me-3 mb-1 px-3">{{ __('orders.member.checkout.my_cart') }}</a>
                                    <button type="submit" class="btn btn-primary mb-1 px-3" {{ (!($isGuest ?? false) && isset($deliverySlots) && $deliverySlots->isEmpty()) ? 'disabled' : '' }}>
                                        {{ __('customers.pos.place_order') }}
                                        <div class="spinner-border spinner-border-sm d-none" role="status">
                                            <span class="visually-hidden">{{ __('orders.loading') }}</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card no-border shadow">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('orders.order_summary') }}</h5>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ __('orders.product') }}</th>
                                <th>{{ __('orders.member.checkout.price_rm') }}</th>
                                <th>{{ __('ui.storefront.cart.quantity_weight') }}</th>
                                <th>{{ __('orders.member.checkout.total_rm') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                <tr>
                                    <td>
                                        <strong>{{ $product->name }}<br /></strong>
                                        @foreach($product->options as $opt => $opt_itm)
                                            {{ $opt }}: {{ $opt_itm }}<br />
                                        @endforeach
                                        @if($product->remark)
                                            {{ __('orders.remark') }}: {{ $product->remark }}<br />
                                        @endif
    
                                    </td>
                                    <td align="right">
                                        @if ($user->price_permission)
                                            {{ $product->unit_price }}
                                        @else
                                        -
                                        @endif
                                    </td>
                                    <td>{{ $product->quantity ?? ($product->weight . ' KG') }}</td>
                                    <td align="right">
                                        @if ($user->price_permission)
                                            {{ number_format($product->unit_price * ($product->quantity ?? $product->weight), 2) }}
                                        @else
                                        -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($user->price_permission)
                            <tfoot>
                                @if ($user->isCreditCustomer() && $available_credit > 0)
                                    <tr>
                                        <td colspan="4">
                                            <span class="badge bg-success">{{ __('orders.member.checkout.credit_balance_applied', ['amount' => number_format($available_credit, 2)]) }}</span>
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>{{ __('orders.total') }}</td>
                                    <td colspan="3" align="right"><strong><span id="total-price-value">{{ number_format($total, 2) }}</span></strong></td>
                                </tr>
                                @if ($user->isCreditCustomer() && $available_credit > 0)
                                    <tr>
                                        <td colspan="3">{{ __('orders.member.checkout.est_after_credit') }}</td>
                                        <td align="right"><strong>RM {{ number_format(max(0, $total - $available_credit), 2) }}</strong></td>
                                    </tr>
                                @endif
                            </tfoot>
                        @endif
                    </table>
                    <div class="mt-3 pt-2 border-top">
                        @include('partials.subject_to_availability')
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')

    <script>
        $(document).ready(function() {
            function toggleWechatIdField() {
                if ($('#contact_method_wechat').is(':checked')) {
                    $('#wechatIdGroup').show();
                    $('#wechat_id').attr('required', true);
                } else {
                    $('#wechatIdGroup').hide();
                    $('#wechat_id').removeAttr('required');
                }
            }

            $('input[name="contact_method"]').on('change', toggleWechatIdField);
            toggleWechatIdField();

            var deliverySlotsUrl = @json($deliverySlotsUrl ?? '');
            var oldSlotId = @json(old('delivery_slot_id'));

            function loadDeliverySlots(date) {
                var $slotSelect = $('#delivery_slot_id');
                $slotSelect.prop('disabled', true).html('<option value="">{{ __('orders.choose_delivery_time') }}</option>');

                if (!date || !deliverySlotsUrl) {
                    return;
                }

                $.get(deliverySlotsUrl, { date: date }, function (response) {
                    if (!response.slots || !response.slots.length) {
                        $slotSelect.html('<option value="">{{ __('orders.no_delivery_slots_for_date') }}</option>');
                        return;
                    }

                    var html = '<option value="">{{ __('orders.choose_delivery_time') }}</option>';
                    response.slots.forEach(function (slot) {
                        var selected = oldSlotId && String(oldSlotId) === String(slot.id) ? ' selected' : '';
                        html += '<option value="' + slot.id + '"' + selected + '>' + slot.label + '</option>';
                    });
                    $slotSelect.html(html).prop('disabled', false);
                });
            }

            $('#delivery_date').on('change', function () {
                oldSlotId = null;
                loadDeliverySlots($(this).val());
            });

            if ($('#delivery_date').val()) {
                loadDeliverySlots($('#delivery_date').val());
            }
        });
    </script>

@endsection
