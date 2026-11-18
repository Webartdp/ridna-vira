@php($isMaker = !empty($maker))
<div class="account-auth-tabs">
    <nav aria-label="Тип кабінету">
        <a href="{{ route($registering ? 'register' : 'login') }}" @if (!$isMaker) aria-current="page" @endif>Користувач</a>
        <a href="{{ route($registering ? 'maker.register' : 'maker.login') }}" @if ($isMaker) aria-current="page" @endif>Майстер</a>
    </nav>
    <nav aria-label="Вхід і реєстрація">
        <a href="{{ route($isMaker ? 'maker.login' : 'login') }}" @if (!$registering) aria-current="page" @endif>Вхід</a>
        <a href="{{ route($isMaker ? 'maker.register' : 'register') }}" @if ($registering) aria-current="page" @endif>Реєстрація</a>
    </nav>
</div>
<style>
.account-auth-tabs{margin-bottom:16px}
.account-auth-tabs nav{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}
.account-auth-tabs nav:first-child{padding:4px;background:#eee5d3;border:1px solid #d7cab3;border-radius:10px}
.account-auth-tabs a{display:grid;place-items:center;min-height:44px;padding:8px;text-align:center;text-decoration:none;border-radius:7px}
.account-auth-tabs nav:first-child a[aria-current]{background:#493a23;color:#fff}
.account-auth-tabs nav:last-child a[aria-current]{text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:5px;font-weight:700}
.account-auth-tabs a:focus-visible{outline:2px solid #846532;outline-offset:-2px}
</style>
