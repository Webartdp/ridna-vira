@extends('layouts.app')
@section('title', 'Оплата замовлення — Рідна Віра')
@push('styles') @include('shop.styles') @endpush
@section('content')
<section class="shop"><h1>Оплата замовлення</h1><p>Перейдіть на захищену сторінку WayForPay, щоб оплатити замовлення карткою.</p>
<form method="post" action="https://secure.wayforpay.com/pay" accept-charset="utf-8">
    @foreach($fields as $key => $value)
        @if(is_array($value))@foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach
        @else<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
    @endforeach
    <button class="shop-btn">Перейти до WayForPay</button>
</form></section>
@endsection
