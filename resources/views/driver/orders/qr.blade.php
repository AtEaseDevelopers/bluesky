@extends('driver.layouts.app')
@section('title', __('driver_portal.deliveries.rm_collect_title'))
@section('css')
<style>
    .rmq-frame { position: relative; width: 260px; max-width: 80vw; aspect-ratio: 1; padding: 14px; margin: 0 auto;
        background: #fff; border: 1px solid var(--line); border-radius: 1rem; box-shadow: 0 8px 26px rgba(2,62,125,.10); }
    .rmq-frame img { width: 100%; height: 100%; object-fit: contain; display: block; }
    .rmq-corner { position: absolute; width: 22px; height: 22px; border: 3px solid var(--accent); }
    .rmq-corner.tl { top: 8px; left: 8px; border-right: 0; border-bottom: 0; border-top-left-radius: 6px; }
    .rmq-corner.tr { top: 8px; right: 8px; border-left: 0; border-bottom: 0; border-top-right-radius: 6px; }
    .rmq-corner.bl { bottom: 8px; left: 8px; border-right: 0; border-top: 0; border-bottom-left-radius: 6px; }
    .rmq-corner.br { bottom: 8px; right: 8px; border-left: 0; border-top: 0; border-bottom-right-radius: 6px; }
    .rmq-amount { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 2.4rem; color: var(--deep); line-height: 1; }
    .rmq-pulse-dot { width: 11px; height: 11px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 0 rgba(255,93,59,.55); animation: rmqPulse 1.4s infinite; display: inline-block; }
    @keyframes rmqPulse { 0% { box-shadow: 0 0 0 0 rgba(255,93,59,.55); } 70% { box-shadow: 0 0 0 12px rgba(255,93,59,0); } 100% { box-shadow: 0 0 0 0 rgba(255,93,59,0); } }
    .rmq-check svg { width: 76px; height: 76px; }
    .rmq-check circle { stroke: #1f9d55; stroke-width: 3; fill: none; }
    .rmq-check path { stroke: #1f9d55; stroke-width: 4; fill: none; stroke-linecap: round; stroke-linejoin: round; }
    #rmq-success { display: none; }
</style>
@endsection
@section('content')
    <a href="{{ route('driver.orders.show', $order->id) }}" class="btn btn-link ps-0 mb-2" style="text-decoration:none; font-weight:600;">
        <i class="fa fa-arrow-left me-1"></i> {{ __('driver_portal.deliveries.back') }}
    </a>

    <div class="card driver-card">
        <div class="card-body text-center p-4">
            <h5 class="display-font mb-3" style="font-size:1.2rem;">{{ __('driver_portal.deliveries.rm_collect_title') }}</h5>

            @if ($qr)
                <div id="rmq-live">
                    <div class="rmq-amount mb-1">RM {{ $qr['amount'] }}</div>
                    <p class="text-muted-ink mb-3">{{ __('driver_portal.deliveries.rm_scan_to_pay') }}</p>

                    <div class="rmq-frame mb-3">
                        <span class="rmq-corner tl"></span><span class="rmq-corner tr"></span>
                        <span class="rmq-corner bl"></span><span class="rmq-corner br"></span>
                        @if ($qr['qr_image'])
                            <img src="{{ $qr['qr_image'] }}" alt="{{ __('driver_portal.deliveries.rm_qr_alt') }}">
                        @endif
                    </div>

                    @if ($qr['qr_code_url'])
                        <p class="mb-3"><a href="{{ $qr['qr_code_url'] }}" target="_blank" rel="noopener">{{ __('driver_portal.deliveries.rm_open_link') }}</a></p>
                    @endif

                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <span class="rmq-pulse-dot"></span>
                        <span class="text-muted-ink fw-semibold">{{ __('driver_portal.deliveries.rm_waiting') }}</span>
                    </div>
                </div>

                <div id="rmq-success">
                    <div class="rmq-check mb-3">
                        <svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24"/><path d="M14 27l8 8 16-16"/></svg>
                    </div>
                    <h4 class="display-font mb-1">{{ __('driver_portal.deliveries.rm_received') }}</h4>
                    <div class="rmq-amount" style="font-size:1.8rem;">RM <span id="rmq-paid">{{ $qr['amount'] }}</span></div>
                    <p class="text-muted-ink mt-2">{{ __('driver_portal.deliveries.rm_refreshing') }}</p>
                </div>

                <div id="rmq-failed" style="display:none;">
                    <div class="mb-2" style="color:var(--accent); font-size:2rem;"><i class="fa fa-times-circle"></i></div>
                    <h5 class="display-font mb-2">{{ __('driver_portal.deliveries.rm_failed') }}</h5>
                    <form method="POST" action="{{ route('driver.orders.rm-pay', $order->id) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-brand">
                            <i class="fa fa-refresh me-1"></i> {{ __('driver_portal.deliveries.rm_retry') }}
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
        var orderUrl = @json(route('driver.orders.show', $order->id));
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
                        setTimeout(function () { window.location = orderUrl; }, 2200);
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
