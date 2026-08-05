@extends('layouts.app')
@section('title', $title.' — Рідна Віра')
@section('content')
<section class="page-hero"><div class="container site-container"><h1>{{ $title }}</h1><p><a href="{{ url('/') }}">Головна</a> / {{ $title }}</p></div></section>
<section class="placeholder"><div class="container site-container"><div class="paper-card text-center"><h2>{{ $title }}</h2><p>Сторінка вже підключена до структури Laravel. Верстку за відповідним PDF-макетом буде додано наступним етапом.</p></div></div></section>
@endsection
