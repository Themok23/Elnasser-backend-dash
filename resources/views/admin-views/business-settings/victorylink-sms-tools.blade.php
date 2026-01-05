@extends('layouts.admin.app')

@section('title', 'VictoryLink SMS')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/sms.png')}}" class="w--26" alt="">
                </span>
                <span>VictoryLink SMS</span>
            </h1>
            @include('admin-views.business-settings.partials.third-party-links')
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Gateway Status</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.business-settings.third-party.sms-module-update', ['victorylink']) }}">
                            @csrf
                            @method('post')

                            <div class="d-flex align-items-center gap-4 gap-xl-5 mb-3">
                                <div class="custom-radio">
                                    <input type="radio" id="victorylink-active" name="status" value="1"
                                        {{ (int)($config['status'] ?? 0) === 1 ? 'checked' : '' }}>
                                    <label for="victorylink-active">Active</label>
                                </div>
                                <div class="custom-radio">
                                    <input type="radio" id="victorylink-inactive" name="status" value="0"
                                        {{ (int)($config['status'] ?? 0) === 1 ? '' : 'checked' }}>
                                    <label for="victorylink-inactive">Inactive</label>
                                </div>
                            </div>

                            {{-- Keep existing saved config when toggling status --}}
                            <input type="hidden" name="gateway" value="victorylink">
                            <input type="hidden" name="mode" value="live">
                            <input type="hidden" name="username" value="{{ $config['username'] ?? '' }}">
                            <input type="hidden" name="password" value="{{ $config['password'] ?? '' }}">
                            <input type="hidden" name="sender" value="{{ $config['sender'] ?? '' }}">
                            <input type="hidden" name="lang" value="{{ $config['lang'] ?? 'E' }}">
                            <input type="hidden" name="otp_template" value="{{ $config['otp_template'] ?? 'Your OTP is: #OTP#' }}">
                            <input type="hidden" name="phone_prefix" value="{{ $config['phone_prefix'] ?? '' }}">
                            <input type="hidden" name="use_dlr" value="{{ $config['use_dlr'] ?? 0 }}">
                            <input type="hidden" name="dlr_url" value="{{ $config['dlr_url'] ?? '' }}">
                            <input type="hidden" name="base_url" value="{{ $config['base_url'] ?? 'https://smsvas.vlserv.com' }}">

                            @if(empty($config['username'] ?? null) || empty($config['password'] ?? null))
                                <div class="alert alert-warning mb-3">
                                    Please fill <strong>username</strong> and <strong>password</strong> in
                                    <a href="{{ route('admin.business-settings.third-party.sms-module') }}"><strong>SMS Module → victorylink</strong></a>
                                    before activating, otherwise sending will fail.
                                </div>
                            @endif

                            <button class="btn btn--primary" type="submit">Update Status</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Send Test SMS</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.business-settings.third-party.victorylink-sms.send') }}">
                            @csrf

                            <div class="form-group">
                                <label>Receiver (phone)</label>
                                <input type="text" name="receiver" class="form-control" value="{{ old('receiver') }}" required>
                            </div>

                            <div class="form-group">
                                <label>Message</label>
                                <textarea name="message" class="form-control" rows="3" required>{{ old('message', 'Test message from dashboard') }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Sender (optional)</label>
                                    <input type="text" name="sender" class="form-control" value="{{ old('sender', $config['sender'] ?? '') }}">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>Lang</label>
                                    <select name="lang" class="form-control">
                                        @php($langOld = old('lang', $config['lang'] ?? 'E'))
                                        <option value="E" {{ strtoupper($langOld) == 'E' ? 'selected' : '' }}>E (English)</option>
                                        <option value="A" {{ strtoupper($langOld) == 'A' ? 'selected' : '' }}>A (Arabic)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>SMSID (optional)</label>
                                    <input type="text" name="sms_id" class="form-control" value="{{ old('sms_id') }}" placeholder="Leave empty to auto-generate">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>CampaignID (optional)</label>
                                    <input type="text" name="campaign_id" class="form-control" value="{{ old('campaign_id') }}">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="use_dlr" value="1" {{ old('use_dlr', (int)($config['use_dlr'] ?? 0)) ? 'checked' : '' }}>
                                    Send with DLR
                                </label>
                            </div>

                            <div class="form-group">
                                <label>DLR URL (optional)</label>
                                <input type="text" name="dlr_url" class="form-control" value="{{ old('dlr_url', $config['dlr_url'] ?? $dlr_callback_url) }}">
                                <small class="text-muted">Your callback endpoint: <span class="text-monospace">{{ $dlr_callback_url }}</span></small>
                            </div>

                            <button class="btn btn--primary" type="submit">Send</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h4 class="mb-0">Check Credit</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.business-settings.third-party.victorylink-sms.check-credit') }}">
                            @csrf
                            <button class="btn btn-outline-primary" type="submit">Check Credit</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Check DLR Status</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.business-settings.third-party.victorylink-sms.check-dlr') }}">
                            @csrf
                            <div class="form-group">
                                <label>UserSMSId</label>
                                <input type="text" name="user_sms_id" class="form-control" value="{{ old('user_sms_id', old('sms_id')) }}" required>
                            </div>
                            <button class="btn btn-outline-primary" type="submit">Check DLR</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


