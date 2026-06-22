@extends('layouts.admin')
@section('title', __('settings.order_fields'))
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">{{ __('settings.order_fields_title') }}</h5>
                    <p class="text-muted">{{ __('settings.order_fields_help') }}</p>
                    <hr>
                    <form action="{{ route('admin.order-field-settings.update') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="weight_presets">{{ __('settings.weight_presets') }}</label>
                                    <span class="text-danger"> *</span>
                                    <textarea class="form-control @error('weight_presets') is-invalid @enderror" name="weight_presets" id="weight_presets" rows="4" placeholder="{{ __('settings.weight_presets_placeholder') }}">{{ old('weight_presets', $weightPresets) }}</textarea>
                                    <small class="text-muted">{{ __('settings.weight_presets_help') }}</small>
                                    @error('weight_presets')
                                        <span class="text-danger d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="situation_label">{{ __('settings.situation_label') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('situation_label') is-invalid @enderror" name="situation_label" id="situation_label" value="{{ old('situation_label', $situationLabel) }}">
                                    <small class="text-muted">{{ __('settings.situation_label_help') }}</small>
                                    @error('situation_label')
                                        <span class="text-danger d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="situation_options">{{ __('settings.situation_options') }}</label>
                                    <span class="text-danger"> *</span>
                                    <textarea class="form-control @error('situation_options') is-invalid @enderror" name="situation_options" id="situation_options" rows="3" placeholder="{{ __('settings.situation_options_placeholder') }}">{{ old('situation_options', $situationOptions) }}</textarea>
                                    <small class="text-muted">{{ __('settings.situation_options_help') }}</small>
                                    @error('situation_options')
                                        <span class="text-danger d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary mb-1">{{ __('settings.save_settings') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
