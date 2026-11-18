@extends('account.layout', ['accountTitle' => 'Редагувати виріб'])

@push('styles') @include('maker.styles') @endpush

@section('account-content')
    <p><a href="{{ route('maker.index') }}">← До моїх виробів</a></p>
    @include('maker.form', ['description' => html_entity_decode(strip_tags(preg_replace('~<br\s*/?>|</p>~i', "\n", $product->description ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')])
@endsection
