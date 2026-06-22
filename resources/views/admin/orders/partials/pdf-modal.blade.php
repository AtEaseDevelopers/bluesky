<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('orders.pdf_preview') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="pdfFrame" style="width:100%;height:80vh;border:none;"></iframe>
            </div>
            <div class="modal-footer">
                <a id="downloadLink" class="btn btn-primary" href="#" download><i class="fa fa-download"></i> {{ __('orders.download_pdf') }}</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.close') }}</button>
            </div>
        </div>
    </div>
</div>
