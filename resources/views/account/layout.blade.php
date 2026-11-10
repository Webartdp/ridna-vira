@extends('layouts.app')
@section('title', $accountTitle.' — Рідна Віра')
@push('styles')
@include('shop.styles')
<style>
.account{max-width:1040px}.account-auth{max-width:520px;margin:0 auto}.account-heading{display:flex;justify-content:space-between;gap:20px;align-items:start;margin-bottom:28px}.account-heading h1{margin:0}.account-nav{display:flex;flex-wrap:wrap;gap:12px;margin:20px 0 32px}.account-nav form{margin:0}.account-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.account-form p{line-height:1.6}.account-form button[type=submit]{margin-top:24px}.account-help{margin-top:24px;line-height:1.8}.account-orders{overflow:auto}.account-orders td{vertical-align:middle}.account-orders th{white-space:nowrap}.account-caption{color:#756348;font-size:.95rem;margin-top:8px}.account-order-ref{font-family:monospace;overflow-wrap:anywhere}.account-password-hint{font-size:.9rem;color:#756348}.account-alert{border-radius:8px}.account-button-row{display:flex;gap:12px;flex-wrap:wrap}.account-verify{display:flex;gap:20px;align-items:center;justify-content:space-between}.account-verify p{margin:0}@media(max-width:650px){.account-grid{grid-template-columns:1fr}.account-heading,.account-verify{display:block}.account-verify button{margin-top:16px}.account-nav .shop-btn{padding:10px 14px}.account h1{font-size:2rem}}
</style>
@endpush
@section('content')
<section class="shop account">
    <header class="account-heading"><div><h1>{{ $accountTitle }}</h1><p class="account-caption">Духовний центр «Рідна Віра»</p></div></header>
    @auth
    <nav class="account-nav" aria-label="Особистий кабінет">
        <a class="shop-btn {{ request()->routeIs('account.index') ? '' : 'shop-secondary' }}" href="{{ route('account.index') }}">Мої замовлення</a>
        <a class="shop-btn {{ request()->routeIs('account.profile') ? '' : 'shop-secondary' }}" href="{{ route('account.profile') }}">Мій профіль</a>
        @if(auth()->user()->roleName() !== 'user')<a class="shop-btn shop-secondary" href="{{ url('/admin') }}">Керування сайтом</a>@endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="shop-btn shop-secondary" type="submit">Вийти</button></form>
    </nav>
    @endauth
    @if(session('status'))<div class="shop-alert account-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="shop-alert shop-error" role="alert"><ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('account-content')
</section>
@endsection
