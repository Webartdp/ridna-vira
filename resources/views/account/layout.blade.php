@extends('layouts.app')
@section('title', $accountTitle.' — Рідна Віра')
@push('styles')
@include('shop.styles')
<style>
.account{max-width:1040px}.account-auth{max-width:520px;margin:0 auto}.account-heading{display:flex;justify-content:space-between;gap:20px;align-items:start;margin-bottom:28px}.account-heading h1{margin:0}.account-nav{display:flex;flex-wrap:wrap;gap:12px;margin:20px 0 32px}.account-nav form{margin:0}.account-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.account-form p{line-height:1.6}.account-form button[type=submit]{margin-top:24px}.account-help{margin-top:24px;line-height:1.8}.account-orders{overflow:auto}.account-orders td{vertical-align:middle}.account-orders th{white-space:nowrap}.account-caption{color:#756348;font-size:.95rem;margin-top:8px}.account-order-ref{font-family:monospace;overflow-wrap:anywhere}.account-password-hint{font-size:.9rem;color:#756348}.account-alert{border-radius:8px}.account-button-row{display:flex;gap:12px;flex-wrap:wrap}.account-verify{display:flex;gap:20px;align-items:center;justify-content:space-between}.account-verify p{margin:0}@media(max-width:650px){.account-grid{grid-template-columns:1fr}.account-heading,.account-verify{display:block}.account-verify button{margin-top:16px}.account-nav .shop-btn{padding:10px 14px}.account h1{font-size:2rem}}
.account-avatar{display:grid;place-items:center;flex:none;width:56px;height:56px;border-radius:50%;background:#e9dfcc;border:1px solid #d7cab3;color:#493a23;font:700 1.4rem/1 var(--rv-font-ui);object-fit:cover}
.profile-avatar-panel{margin-bottom:20px}.profile-avatar-panel form+form{margin-top:12px}.profile-avatar-panel input[type=file]{display:block;margin:8px 0 14px}
</style>
@endpush
@section('content')
<section class="shop account">
    <header class="account-heading" style="display:flex"><div><h1>{{ $accountTitle }}</h1><p class="account-caption">Духовний центр «Рідна Віра»</p></div>
        @auth
            @if (auth()->user()->avatar_path)
                <img class="account-avatar" src="{{ route('account.avatar.show', ['v' => md5(auth()->user()->avatar_path)]) }}" width="56" height="56" alt="Аватарка профілю">
            @else
                <span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            @endif
        @endauth
    </header>
    @auth
    <nav class="account-nav" aria-label="Особистий кабінет">
        <a class="shop-btn {{ request()->routeIs('account.index') ? '' : 'shop-secondary' }}" href="{{ route('account.index') }}">Мої замовлення</a>
        <a class="shop-btn {{ request()->routeIs('account.profile') ? '' : 'shop-secondary' }}" href="{{ route('account.profile') }}">Мій профіль</a>
        @if(in_array(auth()->user()->roleName(), ['user', 'maker'], true))
            <a class="shop-btn {{ request()->routeIs('maker.index', 'maker.products.*') ? '' : 'shop-secondary' }}" href="{{ route('maker.index') }}" @if(request()->routeIs('maker.index', 'maker.products.*')) aria-current="page" @endif>{{ auth()->user()->roleName() === 'maker' ? 'Мої вироби' : 'Кабінет майстра' }}</a>
        @endif
        @if(auth()->user()->roleName() === 'maker')
            <a class="shop-btn {{ request()->routeIs('maker.payouts') ? '' : 'shop-secondary' }}" href="{{ route('maker.payouts') }}" @if(request()->routeIs('maker.payouts')) aria-current="page" @endif>Мої виплати</a>
        @endif
        @if(auth()->user()->canManage('admin') || auth()->user()->canManage('content') || auth()->user()->canManage('shop'))<a class="shop-btn shop-secondary" href="{{ url('/admin') }}">Керування сайтом</a>@endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="shop-btn shop-secondary" type="submit">Вийти</button></form>
    </nav>
    @endauth
    @if(session('status'))<div class="shop-alert account-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any() && empty($errorsInDialog))<div class="shop-alert shop-error" role="alert"><ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('account-content')
</section>
@endsection
