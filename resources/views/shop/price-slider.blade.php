<div class="shop-price-slider" data-price-slider hidden>
    <div class="shop-price-track" aria-hidden="true"></div>
    <input type="range" min="{{ $minimum }}" max="{{ $maximum }}" step="1" value="{{ $minimum }}" aria-label="Мінімальна ціна">
    <input type="range" min="{{ $minimum }}" max="{{ $maximum }}" step="1" value="{{ $maximum }}" aria-label="Максимальна ціна">
</div>

@push('styles')
    <style>
        .shop-price-slider{position:relative;height:36px;margin-bottom:8px;--thumb-size:24px}
        .shop-price-slider .shop-price-track{
            position:absolute;inset-inline:calc(var(--thumb-size) / 2);top:15px;height:6px;border-radius:6px;
            background:linear-gradient(to right,#dfd5c2 var(--start),#765931 var(--start),#765931 var(--end),#dfd5c2 var(--end));
        }
        .shop-filters .shop-price-slider input{
            appearance:none;position:absolute;inset:0;width:100%;height:36px;margin:0;padding:0;
            border:0;background:none;pointer-events:none;
        }
        .shop-price-slider input::-webkit-slider-thumb{
            appearance:none;width:var(--thumb-size);height:var(--thumb-size);border:2px solid #765931;
            border-radius:50%;background:#fffdf6;pointer-events:auto;cursor:grab;
        }
        .shop-price-slider input::-moz-range-thumb{
            box-sizing:border-box;width:var(--thumb-size);height:var(--thumb-size);border:2px solid #765931;
            border-radius:50%;background:#fffdf6;pointer-events:auto;cursor:grab;
        }
        .shop-price-slider input:focus-visible{outline:2px solid #765931;outline-offset:2px}
        .shop-price-slider input:focus-visible::-webkit-slider-thumb{background:#765931}
        .shop-price-slider input:focus-visible::-moz-range-thumb{background:#765931}
    </style>
@endpush
@push('scripts')
    <script type="module">
        const slider = document.querySelector('[data-price-slider]');
        const [lower, upper] = slider.querySelectorAll('input');
        const from = document.querySelector('#shop-price-from');
        const to = document.querySelector('#shop-price-to');
        const minimum = Number(lower.min);
        const maximum = Number(upper.max);
        const currency = new Intl.NumberFormat('uk-UA', {style: 'currency', currency: 'UAH'});
        const read = (field, fallback) => field.value && !field.validity.patternMismatch
            ? Math.round(Number(field.value.replace(',', '.')) * 100)
            : fallback;
        const clamp = value => Math.max(minimum, Math.min(maximum, value));
        const percent = value => ((value - minimum) / (maximum - minimum) * 100) + '%';

        const sync = () => {
            const start = read(from, minimum);
            const end = read(to, maximum);
            const low = clamp(start);
            const high = clamp(end);
            lower.value = Math.min(low, high);
            upper.value = high;
            lower.style.zIndex = high === maximum ? 1 : 0;
            slider.style.setProperty('--start', percent(lower.valueAsNumber));
            slider.style.setProperty('--end', percent(high));
            lower.setAttribute('aria-valuetext', currency.format(lower.valueAsNumber / 100));
            upper.setAttribute('aria-valuetext', currency.format(high / 100));
            to.setCustomValidity(from.value && to.value && start > end ? 'Ціна «До» має бути не меншою за ціну «Від».' : '');
        };

        for (const [handle, field, other] of [[lower, from, upper], [upper, to, lower]]) {
            handle.addEventListener('input', () => {
                const value = handle === lower
                    ? Math.min(handle.valueAsNumber, other.valueAsNumber)
                    : Math.max(handle.valueAsNumber, other.valueAsNumber);
                field.value = (value / 100).toFixed(2);
                sync();
            });
            field.addEventListener('input', sync);
        }

        slider.addEventListener('click', event => {
            if (event.target === lower || event.target === upper) return;
            const track = slider.querySelector('.shop-price-track').getBoundingClientRect();
            const value = clamp(Math.round(minimum + (maximum - minimum) * (event.clientX - track.left) / track.width));
            const handle = value - lower.valueAsNumber <= upper.valueAsNumber - value ? lower : upper;
            handle.focus();
            handle.value = value;
            handle.dispatchEvent(new Event('input'));
        });

        sync();
        slider.hidden = false;
    </script>
@endpush
