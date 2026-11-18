@extends('account.layout', ['accountTitle' => 'Кабінет майстра'])

@section('account-content')
@if ($products === null)
    <div class="shop-panel account-form">
        <p>Відкрийте майстерню, щоб пропонувати свої вироби в крамниці.</p>
        @if (auth()->user()->hasVerifiedEmail())
            <form method="post" action="{{ route('maker.activate') }}">
                @csrf
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
    <div class="shop-split">
        <form class="shop-panel account-form" method="post" action="{{ route('maker.products.store') }}" enctype="multipart/form-data">
            @csrf
            <h2>Додати виріб</h2>
            <p>Адміністратор перевірить виріб перед публікацією в крамниці.</p>
            <label for="maker-name">Назва виробу</label>
            <input id="maker-name" name="name" required maxlength="150" value="{{ old('name') }}">
            <label for="maker-description">Опис</label>
            <textarea id="maker-description" name="description" rows="5" required maxlength="5000">{{ old('description') }}</textarea>
            <label for="maker-images">Фотографії</label>
            <input id="maker-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required aria-describedby="maker-images-hint">
            <small id="maker-images-hint">До 8 фотографій у форматі JPG, PNG або WebP. До 2 МБ на фото й до 6 МБ загалом.</small>
            <label for="maker-price">Ваша ціна, грн</label>
            <input id="maker-price" name="price" inputmode="decimal" pattern="[0-9]{1,7}([.,][0-9]{1,2})?" required maxlength="10" value="{{ old('price') }}" aria-describedby="maker-retail">
            <small id="maker-retail">Ціна в крамниці включає націнку 30%.</small>
            <label for="maker-stock">Кількість готових виробів</label>
            <input id="maker-stock" name="stock" type="number" min="0" max="1000000" step="1" value="{{ old('stock') }}" aria-describedby="maker-stock-hint">
            <small id="maker-stock-hint">Залиште порожнім для виробу під замовлення. Вкажіть 0, якщо виріб зараз недоступний.</small>
            <button class="shop-btn" type="submit">Подати на публікацію</button>
        </form>
        <section class="shop-panel" aria-labelledby="maker-products">
            <h2 id="maker-products">Мої вироби</h2>
            @forelse ($products as $product)
                <article class="shop-row">
                    <div>
                        <h3>@if ($product->is_active)<a href="{{ route('shop.product', $product) }}">{{ $product->name }}</a>@else{{ $product->name }}@endif</h3>
                        <small>{{ $product->is_active ? 'У крамниці' : 'Очікує публікації' }} · {{ $product->availabilityLabel() }}</small>
                        <small>Ваша ціна {{ \App\Support\Money::display($product->maker_price_minor) }}</small>
                        <small>У крамниці {{ \App\Support\Money::display($product->price_minor) }}</small>
                    </div>
                </article>
            @empty
                <p>Ваші вироби з’являться тут після додавання.</p>
            @endforelse
            {{ $products->links('partials.articles-pagination') }}
        </section>
    </div>
    <script type="module">
        const price = document.querySelector('#maker-price');
        const retail = document.querySelector('#maker-retail');
        const currency = new Intl.NumberFormat('uk-UA', { style: 'currency', currency: 'UAH' });
        const update = () => {
            const match = price.value.match(/^([0-9]{1,7})(?:[.,]([0-9]{1,2}))?$/);
            const minor = match ? Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0')) : 0;
            retail.textContent = minor
                ? 'Ціна в крамниці з націнкою 30% — ' + currency.format(Math.round(minor * 130 / 100) / 100)
                : 'Ціна в крамниці включає націнку 30%.';
        };
        price.addEventListener('input', update);
        update();
    </script>
@endif
@endsection
