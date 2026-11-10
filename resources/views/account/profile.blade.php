@extends('account.layout', ['accountTitle' => 'Мій профіль'])
@section('account-content')
<form method="post" action="{{ route('account.profile') }}" class="shop-panel account-form">@csrf @method('PUT')
    <div class="account-grid"><div><label for="profile-name">Ім’я та прізвище</label><input id="profile-name" name="name" value="{{ old('name', auth()->user()->name) }}" autocomplete="name" required maxlength="150"></div>
    <div><label for="profile-email">Електронна пошта</label><input id="profile-email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" required maxlength="190"></div>
    <div><label for="profile-phone">Телефон</label><input id="profile-phone" name="phone" type="tel" value="{{ old('phone', auth()->user()->phone) }}" autocomplete="tel" maxlength="30"></div>
    <div><label for="profile-address">Адреса / відділення доставки</label><textarea id="profile-address" name="delivery_address" autocomplete="street-address" maxlength="1000" rows="2">{{ old('delivery_address', auth()->user()->delivery_address) }}</textarea></div></div>
    <h2 style="margin-top:32px">Безпека</h2><p>Для збереження змін введіть поточний пароль. Новий пароль можна залишити порожнім.</p>
    <label for="profile-current">Поточний пароль</label><input id="profile-current" name="current_password" type="password" autocomplete="current-password" required>
    <div class="account-grid"><div><label for="profile-password">Новий пароль</label><input id="profile-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128"></div>
    <div><label for="profile-confirmation">Повторіть новий пароль</label><input id="profile-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128"></div></div>
    <button class="shop-btn" type="submit">Зберегти профіль</button>
</form>
@endsection
