@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>{{ __('ui.alert.success') }}</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ui.close') }}"></button>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <strong>{{ __('ui.alert.warning') }}</strong> {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ui.close') }}"></button>
    </div>
@endif

@if (session('danger'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>{{ __('ui.alert.danger') }}</strong> {{ session('danger') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ui.close') }}"></button>
    </div>
@endif
    
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>{{ __('ui.alert.error') }}</strong> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ui.close') }}"></button>
    </div>
@endif

@if (isset($errors) && $errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
