@extends('layouts.admin')
@section('title', __('uom.edit'))
@section('content')

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="card-title">{{ __('uom.edit') }}</h5>
                    <hr>
                    <form action="{{ route('admin.uom.update', encrypt($uom->id)) }}" method="POST" class="form-wrapper">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-4">
                                    <label class="mb-2" for="uom_name">{{ __('uom.uom_name') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('uom_name') is-invalid @enderror" name="uom_name" id="uom_name" placeholder="{{ __('uom.placeholder.uom_name') }}" value="{{ $uom->uom_name }}">
                                    @error('uom_name')
                                        <span class="text-danger" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.uom.index') }}" class="btn btn-secondary me-2 mb-1">{{ __('ui.back') }}</a>
                                    <button type="submit" class="btn btn-primary mb-1">
                                        {{ __('ui.save') }}
                                        <div class="spinner-border spinner-border-sm d-none" role="status">
                                            <span class="visually-hidden">{{ __('inventory.loading') }}</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
