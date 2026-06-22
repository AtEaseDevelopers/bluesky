@extends('layouts.admin')
@section('title', __('drivers.add'))
@section('content')

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow no-border">
                <div class="card-body">
                    <h5 class="mb-4">{{ __('drivers.add') }}</h5>
                    <form action="{{ route('admin.lorry.store') }}" method="POST" class="form-wrapper">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="name">{{ __('drivers.driver_name') }}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" placeholder="{{ __('drivers.placeholder.driver_name') }}" value="{{ old('name') }}">
                                    @error('name')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="phone">{{ __('drivers.phone') }}</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" id="phone" placeholder="{{ __('drivers.placeholder.phone') }}" value="{{ old('phone') }}">
                                    @error('phone')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="lorry_number">{{ __('drivers.lorry_number') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('lorry_number') is-invalid @enderror" name="lorry_number" id="lorry_number" placeholder="{{ __('drivers.placeholder.lorry_number') }}" value="{{ old('lorry_number') }}">
                                    @error('lorry_number')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="is_active">{{ __('drivers.status') }}</label>
                                    <select class="form-control @error('is_active') is-invalid @enderror" name="is_active" id="is_active">
                                        <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>{{ __('drivers.status_labels.active') }}</option>
                                        <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>{{ __('drivers.status_labels.inactive') }}</option>
                                    </select>
                                    @error('is_active')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3">{{ __('drivers.login_credentials') }}</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="username">{{ __('drivers.username') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="text" class="form-control @error('username') is-invalid @enderror" name="username" id="username" placeholder="{{ __('drivers.placeholder.username') }}" value="{{ old('username') }}" autocomplete="off">
                                    @error('username')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="mb-2" for="password">{{ __('drivers.password') }}</label>
                                    <span class="text-danger"> *</span>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="password" placeholder="{{ __('drivers.placeholder.password') }}" autocomplete="new-password">
                                    @error('password')<span class="text-danger" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end">
                                    <a href="{{ route('admin.lorry.index') }}" class="btn btn-secondary me-2 mb-1">{{ __('ui.back') }}</a>
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
