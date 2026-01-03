@extends('layouts.admin.app')
@section('title',translate('Add Customer'))
@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
<div class="content container-fluid">
    <!-- Page Heading -->
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/people.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.add_new_customer')}}
            </span>
        </h1>
    </div>
    <!-- Content Row -->
    <form action="{{route('admin.users.customer.add-new')}}" method="post" enctype="multipart/form-data" class="js-validate">
        @csrf
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">
                    <span class="card-header-icon">
                        <i class="tio-user"></i>
                    </span>
                    <span>{{translate('messages.general_information')}}</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="input-label qcont" for="fname">{{translate('messages.first_name')}}<span class="form-label-secondary text-danger"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.')}}"> *
                                                        </span>
                                                    </label>
                                <input type="text" name="f_name" class="form-control @error('f_name') is-invalid @enderror" id="fname"
                                    placeholder="{{translate('messages.first_name')}}" value="{{old('f_name')}}" required>
                                @error('f_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="input-label qcont" for="lname">{{translate('messages.last_name')}}</label>
                                <input type="text" name="l_name" class="form-control @error('l_name') is-invalid @enderror" id="lname" value="{{old('l_name')}}"
                                    placeholder="{{translate('messages.last_name')}}">
                                @error('l_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="input-label qcont" for="phone">{{translate('messages.phone')}}<span class="form-label-secondary text-danger"
                                                        data-toggle="tooltip" data-placement="right"
                                                        data-original-title="{{ translate('messages.Required.')}}"> *
                                                        </span>
                                    </label>
                                <input type="text" name="phone" value="{{old('phone')}}" class="form-control @error('phone') is-invalid @enderror" id="phone"
                                        placeholder="{{ translate('messages.Ex:') }} +88017********" required>
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="input-label qcont" for="email">{{translate('messages.email')}}</label>
                                <input type="email" name="email" value="{{old('email')}}" class="form-control @error('email') is-invalid @enderror" id="email"
                                        placeholder="{{ translate('messages.Ex:') }} ex@gmail.com">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-6">
                                <div>
                                    <label class="input-label" for="zone_id">{{translate('messages.zone')}}</label>
                                    <select name="zone_id" id="zone_id" class="form-control js-select2-custom @error('zone_id') is-invalid @enderror">
                                        <option value="">{{translate('messages.all')}}</option>
                                        @foreach($zones as $zone)
                                            <option value="{{$zone->id}}" {{old('zone_id') == $zone->id ? 'selected' : ''}}>{{$zone->name}}</option>
                                        @endforeach
                                    </select>
                                    @error('zone_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <label class="input-label" for="tier_is_manual">
                                    {{ translate('messages.customer_tier_settings') }}
                                </label>
                                <div class="d-flex align-items-center gap-3">
                                    <label class="mb-0 d-flex align-items-center gap-2">
                                        <input type="checkbox" name="tier_is_manual" id="tier_is_manual" value="1" {{ old('tier_is_manual') ? 'checked' : '' }}>
                                        <span>{{ translate('messages.manual') ?? 'Manual' }}</span>
                                    </label>
                                    <select name="tier" id="tier" class="form-control" style="max-width: 220px;">
                                        <option value="bronze" {{ old('tier','bronze') === 'bronze' ? 'selected' : '' }}>Bronze</option>
                                        <option value="silver" {{ old('tier') === 'silver' ? 'selected' : '' }}>Silver</option>
                                        <option value="gold" {{ old('tier') === 'gold' ? 'selected' : '' }}>Gold</option>
                                    </select>
                                </div>
                                <small class="text-muted d-block mt-1">If manual is off, tier is calculated from loyalty points.</small>
                                @error('tier')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="h-100 d-flex flex-column">
                            <div class="text-center input-label qcont py-3 my-auto">
                                {{ translate('messages.customer_image') }} <small class="text-muted">( {{ translate('messages.ratio') }} 1:1 )</small>
                            </div>
                            <div class="text-center py-3 my-auto">
                                <img class="img--100" id="viewer"
                                src="{{asset('public/assets/admin/img/400x400/img2.jpg')}}" alt="Customer thumbnail"/>
                            </div>
                            <div class="custom-file">
                                <input type="file" name="image" id="customFileUpload" class="custom-file-input @error('image') is-invalid @enderror"
                                    accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" value="{{old('image')}}">
                                <div class="custom-file-label">{{translate('messages.choose_file')}}</div>
                                @error('image')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <span class="card-header-icon">
                        <i class="tio-lock"></i>
                    </span>
                    <span>{{translate('messages.account_information')}}</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="js-form-message form-group mb-0">
                            <label class="input-label" for="signupSrPassword">{{translate('messages.password')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="top"
        data-original-title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"><img src="{{ asset('/public/assets/admin/img/info-circle.svg') }}" alt="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"></span> <span class="form-label-secondary text-danger"
                            data-toggle="tooltip" data-placement="top"
                            data-original-title="{{ translate('messages.Required.')}}"> *
                            </span> </label>

                            <div class="input-group input-group-merge">
                                <input type="password" class="js-toggle-password form-control @error('password') is-invalid @enderror" name="password" id="signupSrPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"
                                placeholder="{{ translate('messages.password_length_placeholder', ['length' => '8+']) }}"
                                aria-label="8+ characters required" required
                                data-msg="Your password is invalid. Please try again."
                                data-hs-toggle-password-options='{
                                "target": [".js-toggle-password-target-1"],
                                "defaultClass": "tio-hidden-outlined",
                                "showClass": "tio-visible-outlined",
                                "classChangeTarget": ".js-toggle-passowrd-show-icon-1"
                                }'>
                                <div class="js-toggle-password-target-1 input-group-append">
                                    <a class="input-group-text" href="javascript:">
                                        <i class="js-toggle-passowrd-show-icon-1 tio-visible-outlined"></i>
                                    </a>
                                </div>
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="btn--container justify-content-end mt-4">
            <a href="{{route('admin.users.customer.list')}}" class="btn btn--reset">{{translate('messages.cancel')}}</a>
            <button type="reset" id="reset_btn" class="btn btn--reset">{{translate('messages.reset')}}</button>
            <button type="submit" class="btn btn--primary">{{translate('messages.submit')}}</button>
        </div>
    </form>
</div>
@endsection

@push('script_2')
<script>
    "use strict";
    $(document).on('ready', function () {
        // INITIALIZATION OF SHOW PASSWORD
        // =======================================================
        $('.js-toggle-password').each(function () {
            new HSTogglePassword(this).init()
        });

        // INITIALIZATION OF FORM VALIDATION
        // =======================================================
        $('.js-validate').each(function() {
            $.HSCore.components.HSValidation.init($(this));
        });
    });

    function readURL(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#viewer').attr('src', e.target.result);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $("#customFileUpload").change(function() {
        readURL(this);
    });

    $('#reset_btn').click(function(){
        $('#viewer').attr('src', "{{ asset('public/assets/admin/img/400x400/img2.jpg') }}");
        $('#customFileUpload').val(null);
        $('#zone_id').val(null).trigger('change');
        $('input[name="f_name"]').val('');
        $('input[name="l_name"]').val('');
        $('input[name="phone"]').val('');
        $('input[name="email"]').val('');
        $('input[name="password"]').val('');
    });
</script>
@endpush

