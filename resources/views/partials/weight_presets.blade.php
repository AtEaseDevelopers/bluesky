@if (!empty($presets ?? []))
    <div class="mb-2 d-flex flex-wrap gap-2 weight-preset-group">
        @foreach ($presets as $preset)
            <button type="button" class="btn btn-sm btn-outline-secondary weight-preset-btn" data-target="{{ $targetId }}" data-value="{{ $preset }}">
                {{ $preset }} {{ $uomLabel ?? 'KG' }}
            </button>
        @endforeach
    </div>
@endif
