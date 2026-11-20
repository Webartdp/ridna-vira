<form class="shop-panel account-form" method="post" action="{{ $product ? route('maker.products.update', $product) : route('maker.products.store') }}" enctype="multipart/form-data">
    @csrf
    @if ($product)
        @method('PUT')
        <input type="hidden" name="stock_before" value="{{ old('stock_before', $product->stock) }}">
    @endif
    <h2 @isset($titleId) id="{{ $titleId }}" @endisset>{{ $product ? 'Дані виробу' : 'Додати виріб' }}</h2>
    <p>Адміністратор перевірить виріб перед публікацією в крамниці.</p>
    <label for="maker-name">Назва виробу</label>
    <input id="maker-name" name="name" required maxlength="150" value="{{ old('name', $product?->name) }}">
    @include('maker.category-select')
    <label for="maker-description">Опис</label>
    <textarea id="maker-description" name="description" rows="5" required maxlength="5000">{{ old('description', $description ?? '') }}</textarea>
    @if ($product && $product->images)
        <fieldset class="maker-photos">
            <legend>Поточні фото</legend>
            @foreach ($product->images as $photo)
                <label>
                    <img src="{{ $product->imageUrl($photo, 400) }}" width="64" height="64" loading="lazy" alt="">
                    <span><input type="checkbox" name="remove_images[]" value="{{ $photo }}" @checked(in_array($photo, (array) old('remove_images', []), true))> Прибрати фото {{ $loop->iteration }}</span>
                </label>
            @endforeach
        </fieldset>
    @endif
    <label for="maker-images">Фотографії</label>
    <input id="maker-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple @required(!$product) aria-describedby="maker-images-hint">
    <small id="maker-images-hint">До 8 фотографій у форматі JPG, PNG або WebP. До 2 МБ на фото й до 6 МБ загалом.</small>
    <label for="maker-price">Ваша ціна, грн</label>
    <input id="maker-price" name="price" inputmode="decimal" pattern="[0-9]{1,7}([.,][0-9]{1,2})?" required maxlength="10" value="{{ old('price', $product ? \App\Support\Money::decimal($product->maker_price_minor) : '') }}" aria-describedby="maker-retail">
    <small id="maker-retail">Ціна в крамниці включає націнку {{ \App\Models\Product::MAKER_MARKUP_PERCENT }}%.</small>
    <label for="maker-stock">Кількість готових виробів</label>
    <input id="maker-stock" name="stock" type="number" min="0" max="1000000" step="1" value="{{ old('stock', $product && !$product->made_to_order ? $product->stock : '') }}" aria-describedby="maker-stock-hint">
    <small id="maker-stock-hint">Залиште порожнім для виробу під замовлення. Вкажіть 0, якщо виріб зараз недоступний.</small>
    <button class="shop-btn" type="submit"> {{ $product ? 'Зберегти й подати на схвалення' : 'Подати на публікацію' }}</button>
</form>
    <script type="module">
        const price = document.querySelector('#maker-price');
        const retail = document.querySelector('#maker-retail');
        const markup = @js(\App\Models\Product::MAKER_MARKUP_PERCENT);
        const defaultHint = retail.textContent;
        const currency = new Intl.NumberFormat('uk-UA', { style: 'currency', currency: 'UAH' });
        const update = () => {
            const match = price.value.match(/^([0-9]{1,7})(?:[.,]([0-9]{1,2}))?$/);
            const minor = match ? Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0')) : 0;
            retail.textContent = minor
                ? `Ціна в крамниці з націнкою ${markup}% — ${currency.format(Math.round(minor * (100 + markup) / 100) / 100)}`
                : defaultHint;
        };
        price.addEventListener('input', update);
        update();
    </script>
