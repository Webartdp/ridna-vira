@extends('account.layout', ['accountTitle' => 'Підтвердження пошти'])
@section('account-content')
<div class="shop-panel account-form"><p>Відкрийте лист, надісланий на {{ auth()->user()->email }}, і перейдіть за посиланням підтвердження. Якщо листа немає, перевірте папку «Спам» або надішліть запит повторно.</p>
<form method="post" action="{{ route('verification.send') }}">@csrf<button class="shop-btn" type="submit">Надіслати лист повторно</button></form></div>
@endsection
