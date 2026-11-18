<label for="maker-category">Категорія або підкатегорія</label>
<select id="maker-category" name="product_category_id">
    <option value="">Виберіть категорію</option>
    @foreach ($categories as $category)
        @if ($category->children->isNotEmpty())
            <optgroup label="{{ $category->name }}">
                <option value="{{ $category->id }}" @selected((string) old('product_category_id', $product?->product_category_id) === (string) $category->id)>{{ $category->name }} (загальна)</option>
                @foreach ($category->children as $child)
                    <option value="{{ $child->id }}" @selected((string) old('product_category_id', $product?->product_category_id) === (string) $child->id)>{{ $child->name }}</option>
                @endforeach
            </optgroup>
        @else
            <option value="{{ $category->id }}" @selected((string) old('product_category_id', $product?->product_category_id) === (string) $category->id)>{{ $category->name }}</option>
        @endif
    @endforeach
</select>
