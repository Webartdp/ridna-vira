@extends('account.layout', ['accountTitle' => 'Вхід до кабінету'])
@section('account-content')
<div class="account-auth"><form method="post" action="{{ route('login') }}" class="shop-panel account-form">@csrf
    <label for="login-email">Електронна пошта</label><input id="login-email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" maxlength="190" required autofocus>
    <label for="login-password">Пароль</label><input id="login-password" name="password" type="password" autocomplete="current-password" required maxlength="128">
    <label><input type="checkbox" name="remember" value="1"> Запам’ятати мене</label>
    <button class="shop-btn" type="submit">Увійти</button>
    <p class="account-help"><a href="{{ route('password.request') }}">Забули пароль?</a><br>Ще немає облікового запису? <a href="{{ route('register') }}">Зареєструватися</a></p>
</form></div>
@endsection
