@extends('layouts.admin')
@section('title', __('orders.qr.title'))
@section('css')
<style>
    .rmq-card { max-width: 520px; margin: 0 auto; border: 1px solid #e3e9f0; border-radius: 1rem; box-shadow: 0 10px 30px rgba(2,62,125,.08); }
    .rmq-amount { font-weight: 800; font-size: 2.6rem; color: #023e7d; line-height: 1; }
    .rmq-caption { color: #4a6072; font-weight: 600; }
    .rmq-frame { position: relative; width: 260px; max-width: 78vw; aspect-ratio: 1; padding: 14px; margin: 0 auto;
        background: #fff; border: 1px solid #d6e2ec; border-radius: 1rem; box-shadow: 0 8px 26px rgba(2,62,125,.10); }
    .rmq-frame img { width: 100%; height: 100%; object-fit: contain; display: block; }
    .rmq-corner { position: absolute; width: 22px; height: 22px; border: 3px solid #ff5d3b; }
    .rmq-corner.tl { top: 8px; left: 8px; border-right: 0; border-bottom: 0; border-top-left-radius: 6px; }
    .rmq-corner.tr { top: 8px; right: 8px; border-left: 0; border-bottom: 0; border-top-right-radius: 6px; }
    .rmq-corner.bl { bottom: 8px; left: 8px; border-right: 0; border-top: 0; border-bottom-left-radius: 6px; }
    .rmq-corner.br { bottom: 8px; right: 8px; border-left: 0; border-top: 0; border-bottom-right-radius: 6px; }
    .rmq-pulse-dot { width: 11px; height: 11px; border-radius: 50%; background: #ff5d3b; box-shadow: 0 0 0 0 rgba(255,93,59,.55); animation: rmqPulse 1.4s infinite; display: inline-block; }
    @keyframes rmqPulse { 0% { box-shadow: 0 0 0 0 rgba(255,93,59,.55); } 70% { box-shadow: 0 0 0 12px rgba(255,93,59,0); } 100% { box-shadow: 0 0 0 0 rgba(255,93,59,0); } }
    .rmq-check svg { width: 76px; height: 76px; }
    .rmq-check circle { stroke: #1f9d55; stroke-width: 3; fill: none; }
    .rmq-check path { stroke: #1f9d55; stroke-width: 4; fill: none; stroke-linecap: round; stroke-linejoin: round; }
    #rmq-success { display: none; }
</style>
@endsection
@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h4 class="mb-0">{{ __('orders.qr.heading', ['id' => $order->do_no ?: $order->id]) }}</h4>
        <a href="{{ route('admin.orders.summary', $order->id) }}" class="btn btn-secondary">
            <i class="fa fa-chevron-circle-left"></i> {{ __('ui.back') }}
        </a>
    </div>

    <div class="card rmq-card">
        <div class="card-body text-center p-4">

            @if ($qr)
                <div id="rmq-live">
                    <div class="rmq-amount mb-1">RM {{ $qr['amount'] }}</div>
                    <p class="rmq-caption mb-3">{{ __('orders.qr.scan_to_pay') }}</p>

                    <div class="rmq-frame mb-3">
                        <span class="rmq-corner tl"></span><span class="rmq-corner tr"></span>
                        <span class="rmq-corner bl"></span><span class="rmq-corner br"></span>
                        @if ($qr['qr_image'])
                            <img src="{{ $qr['qr_image'] }}" alt="{{ __('orders.qr.alt') }}">
                        @endif
                    </div>

                    @if ($qr['qr_code_url'])
                        <p class="mb-3"><a href="{{ $qr['qr_code_url'] }}" target="_blank" rel="noopener">{{ __('orders.qr.open_link') }}</a></p>
                    @endif

                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <span class="rmq-pulse-dot"></span>
                        <span class="rmq-caption">{{ __('orders.qr.waiting') }}</span>
                    </div>
                </div>

                <div id="rmq-success">
                    <div class="rmq-check mb-3">
                        <svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M14 27l8 8 16-16"/></svg>
                    </div>
                    <h4 class="mb-1">{{ __('orders.qr.received') }}</h4>
                    <div class="rmq-amount rmq-amount-sm" style="font-size:1.8rem;">RM <span id="rmq-paid">{{ $qr['amount'] }}</span></div>
                    <p class="rmq-caption mt-2 mb-3">{{ __('orders.qr.done_hint') }}</p>
                    <a href="{{ route('admin.orders.summary', $order->id) }}" class="btn btn-success">
                        <i class="fa fa-check-circle"></i> {{ __('orders.qr.view_order') }}
                    </a>
                </div>

                <div id="rmq-failed" style="display:none;">
                    <div class="mb-2 text-danger" style="font-size:2rem;"><i class="fa fa-times-circle"></i></div>
                    <h5 class="mb-3">{{ __('orders.qr.payment_failed') }}</h5>
                    <form method="POST" action="{{ route('admin.orders.qr-generate', $order->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-refresh"></i> {{ __('orders.qr.retry') }}
                        </button>
                    </form>
                </div>

            @endif

        </div>
    </div>
@endsection
@section('script')
@if ($qr)
<script>
    (function () {
        var statusUrl = @json($statusUrl) + '?ref=' + encodeURIComponent(@json($qr['reference']));
        var live = document.getElementById('rmq-live');
        var paidEl = document.getElementById('rmq-paid');
        var timer = setInterval(poll, 4000);

        function showState(id) {
            if (live) { live.style.display = 'none'; }
            var el = document.getElementById(id);
            if (el) { el.style.display = 'block'; }
        }

        function poll() {
            fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d) { return; }
                    if (d.paid) {
                        clearInterval(timer);
                        if (paidEl) { paidEl.textContent = d.paid_amount; }
                        showState('rmq-success');
                    } else if (d.failed) {
                        clearInterval(timer);
                        showState('rmq-failed');
                    }
                })
                .catch(function () {});
        }
    })();
</script>
@endif
@endsection
