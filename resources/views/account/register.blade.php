@extends('account.layout', ['accountTitle' => !empty($maker) ? 'Реєстрація майстра' : 'Реєстрація'])
@section('account-content')
<div class="account-auth">
@include('account.auth-tabs', ['registering' => true])
<form method="post" action="{{ route(!empty($maker) ? 'maker.register.submit' : 'register') }}" class="shop-panel account-form">@csrf
    <p>{{ !empty($maker) ? 'Створіть кабінет майстра, щоб подавати свої вироби до крамниці.' : 'Створіть кабінет, щоб зберігати контактні дані та переглядати свої замовлення.' }}</p>
    <label for="register-name">Ім’я та прізвище</label><input id="register-name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="150" required>
    <label for="register-email">Електронна пошта</label><input id="register-email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" maxlength="190" required>
    <label for="register-password">Пароль</label><input id="register-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
    <p class="account-password-hint">Щонайменше 12 символів, із літерами та цифрами.</p>
    <label for="register-confirmation">Повторіть пароль</label><input id="register-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
    <button class="shop-btn" type="submit">{{ !empty($maker) ? 'Створити кабінет майстра' : 'Створити кабінет' }}</button>
</form></div>
@endsection
