@extends('account.layout', ['accountTitle' => 'Новий пароль'])
@section('account-content')
<div class="account-auth"><form method="post" action="{{ route('password.update') }}" class="shop-panel account-form">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<label for="reset-email">Електронна пошта</label><input id="reset-email" name="email" type="email" value="{{ old('email', request('email')) }}" autocomplete="email" required maxlength="190">
<label for="reset-password">Новий пароль</label><input id="reset-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
<p class="account-password-hint">Щонайменше 12 символів, із літерами та цифрами.</p>
<label for="reset-confirmation">Повторіть пароль</label><input id="reset-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
<button class="shop-btn" type="submit">Зберегти пароль</button>
</form></div>
@endsection
