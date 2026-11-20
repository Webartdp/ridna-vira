@extends('account.layout', ['accountTitle' => 'Кабінет майстра', 'errorsInDialog' => $products !== null && auth()->user()->hasVerifiedEmail()])

@push('styles') @include('maker.styles') @endpush

@section('account-content')
@if ($products === null)
    <div class="shop-panel account-form">
        <p>Відкрийте майстерню, щоб пропонувати свої вироби в крамниці.</p>
        @if (auth()->user()->hasVerifiedEmail())
            <form method="post" action="{{ route('maker.activate') }}">
                @csrf
                @include('partials.shop-policy-links', [
                    'links' => \App\Support\ShopPolicies::makerLinks(),
                    'label' => 'Документи для майстрів',
                    'newTab' => true,
                ])
                <button class="shop-btn" type="submit">Відкрити майстерню</button>
            </form>
        @else
            <a href="{{ route('verification.notice') }}">Підтвердити електронну пошту</a>
        @endif
    </div>
@elseif (!auth()->user()->hasVerifiedEmail())
    <div class="shop-panel">
        <p>Підтвердьте електронну пошту, щоб додавати вироби.</p>
        <a href="{{ route('verification.notice') }}">Перейти до підтвердження</a>
    </div>
@else
    <section class="shop-panel maker-inventory" aria-labelledby="maker-products">
        <header class="maker-inventory-heading">
            <h2 id="maker-products">Мої вироби</h2>
            <button class="shop-btn" id="maker-add" type="button" aria-haspopup="dialog" aria-controls="maker-create">
                <span aria-hidden="true">＋</span> Додати виріб
            </button>
        </header>

        @if ($products->isNotEmpty())
            <div class="maker-table-scroll" role="region" aria-label="Таблиця виробів" tabindex="0">
                <table class="maker-table" aria-labelledby="maker-products">
                    <thead>
                        <tr>
                            <th scope="col">Виріб</th>
                            <th scope="col">Ціни</th>
                            <th scope="col">Наявність</th>
                            <th scope="col">Публікація</th>
                            <th scope="col">Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <th scope="row">
                                <div class="maker-product">
                                    <img class="maker-thumb" src="{{ $product->images ? $product->imageUrl($product->images[0], 400) : asset('assets/brand/image-placeholder.svg') }}"
                                        width="52" height="52" loading="lazy" decoding="async" alt="">
                                    <span class="maker-product-name">
                                        @if ($product->is_active)
                                            <a href="{{ route('shop.product', $product) }}">{{ $product->name }}</a>
                                        @else
                                            {{ $product->name }}
                                        @endif
                                        @if ($product->category)
                                            <small>{{ $product->category->parent ? $product->category->parent->name.' / ' : '' }}{{ $product->category->name }}</small>
                                        @endif
                                    </span>
                                </div>
                            </th>
                            <td class="maker-prices">
                                <small>Ваша · {{ \App\Support\Money::display($product->maker_price_minor) }}</small>
                                <small>Крамниця · {{ \App\Support\Money::display($product->price_minor) }}</small>
                            </td>
                            <td>{{ $product->made_to_order || $product->stock === 0 ? $product->availabilityLabel() : $product->stock.' шт.' }}</td>
                            <td>
                                <span @class(['maker-status', 'maker-status-live' => $product->is_active])>
                                    {{ $product->is_active ? 'У крамниці' : 'Приховано' }}
                                </span>
                            </td>
                            <td>
                                <div class="maker-actions">
                                    <a class="maker-action" href="{{ route('maker.products.edit', $product) }}" aria-label="Редагувати {{ $product->name }}" title="Редагувати">
                                        @svg('heroicon-o-pencil-square', 'maker-icon')
                                    </a>
                                    @if ($product->is_active)
                                        <form method="post" action="{{ route('maker.products.hide', $product) }}">
                                            @csrf @method('PATCH')
                                            <button class="maker-action" type="submit" aria-label="Приховати {{ $product->name }}" title="Приховати">
                                                @svg('heroicon-o-eye-slash', 'maker-icon')
                                            </button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('maker.products.destroy', $product) }}" onsubmit="return confirm('Видалити цей виріб?')">
                                        @csrf @method('DELETE')
                                        <button class="maker-action maker-action-danger" type="submit" aria-label="Видалити {{ $product->name }}" title="Видалити">
                                            @svg('heroicon-o-trash', 'maker-icon')
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $products->links('partials.articles-pagination') }}
        @else
            <p class="maker-inventory-empty">Ваші вироби з’являться тут після додавання.</p>
        @endif
    </section>

    <dialog class="maker-dialog" id="maker-create" aria-labelledby="maker-create-title" data-reopen="{{ $errors->any() ? 'yes' : 'no' }}">
        <form method="dialog" class="maker-dialog-close">
            <button class="maker-action" type="submit" aria-label="Закрити форму" title="Закрити">
                @svg('heroicon-o-x-mark', 'maker-icon')
            </button>
        </form>
        @if ($errors->any())
            <div class="shop-alert shop-error" role="alert" tabindex="-1" data-maker-errors>
                <ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @include('maker.form', ['product' => null, 'titleId' => 'maker-create-title'])
    </dialog>
    <script type="module">
        const dialog = document.querySelector('#maker-create');
        const openButton = document.querySelector('#maker-add');
        const openDialog = () => {
            dialog.showModal();
            const focusTarget = dialog.querySelector('[data-maker-errors], #maker-name');
            focusTarget?.focus();
        };

        openButton.addEventListener('click', openDialog);
        dialog.addEventListener('close', () => openButton.focus());
        if (dialog.dataset.reopen === 'yes') openDialog();
    </script>
@endif
@endsection
