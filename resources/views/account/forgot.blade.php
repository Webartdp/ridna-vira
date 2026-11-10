@extends('account.layout', ['accountTitle' => 'Відновлення пароля'])
@section('account-content')
<div class="account-auth"><form method="post" action="{{ route('password.email') }}" class="shop-panel account-form">@csrf
<p>Вкажіть пошту свого облікового запису. Ми надішлемо посилання для зміни пароля.</p>
<label for="reset-email">Електронна пошта</label><input id="reset-email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required maxlength="190">
<button class="shop-btn" type="submit">Надіслати посилання</button><p class="account-help"><a href="{{ route('login') }}">Повернутися до входу</a></p>
</form></div>
@endsection
