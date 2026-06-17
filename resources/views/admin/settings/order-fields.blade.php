@extends('layouts.admin')
@section('title', 'Order Field Settings')
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">Optional Order Field Setup</h5>
                    <p class="text-muted">Configure weight preset buttons and seafood situation options shown during customer ordering.</p>
                    <hr>
                    <form action="{{ route('admin.order-field-settings.update') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="weight_presets">Weight Presets</label>
                                    <span class="text-danger"> *</span>
                                    <textarea class="form-control @error('weight_presets') is-invalid @enderror" name="weight_presets" id="weight_presets" rows="4" placeholder="1, 1.5, 2, 2.5, 3">{{ old('weight_presets', $weightPresets) }}</textarea>
                                    <small class="text-muted">Comma or line-separated values in KG (e.g. 1, 1.5, 2).</small>
                                    @error('weight_presets')
                                        <span class="text-danger d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="situation_label">Situation Option Label</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('situation_label') is-invalid @enderror" name="situation_label" id="situation_label" value="{{ old('situation_label', $situationLabel) }}">
                                    <small class="text-muted">Product options matching this label render as quick-select buttons.</small>
                                    @error('situation_label')
                                        <span class="text-danger d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="mb-4">
                                    <label class="mb-2" for="situation_options">Default Situation Options</label>
                                    <span class="text-danger"> *</span>
                                    <textarea class="form-control @error('situation_options') is-invalid @enderror" name="situation_options" id="situation_options" rows="3" placeholder="live, kill, clean">{{ old('situation_options', $situationOptions) }}</textarea>
                                    <small class="text-muted">Reference list for admins when configuring products (live, kill, clean, etc.).</small>
                                    @error('situation_options')
                                        <span class="text-danger d-block"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary mb-1">Save Settings</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
