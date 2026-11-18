<fieldset id="nova-poshta-lookup" hidden data-cities="{{ route('shop.nova-poshta.cities') }}" data-warehouses="{{ route('shop.nova-poshta.warehouses') }}">
    <legend>Довідник Нової пошти</legend>
    <label for="np-city-search">Назва міста або селища</label>
    <input id="np-city-search" type="search" maxlength="100" autocomplete="off">
    <div id="np-city-results" hidden>
        <label for="np-city">Населений пункт</label>
        <select id="np-city"></select>
    </div>
    <div id="np-warehouse-results" hidden>
        <label for="np-warehouse-search">Пошук за номером або адресою</label>
        <input id="np-warehouse-search" type="search" maxlength="100" autocomplete="off">
        <label for="np-warehouse">Відділення або поштомат</label>
        <select id="np-warehouse"></select>
        <button class="shop-btn shop-secondary" type="button" id="np-more" hidden>Показати ще</button>
    </div>
    <small id="np-status" role="status"></small>
</fieldset>

@push('scripts')
<script type="module">
    const byId = id => document.getElementById(id);
    const fieldset = byId('nova-poshta-lookup');
    const cityQuery = byId('np-city-search');
    const city = byId('np-city');
    const warehouseQuery = byId('np-warehouse-search');
    const warehouse = byId('np-warehouse');
    const status = byId('np-status');
    const address = byId('delivery-address');
    const more = byId('np-more');
    let currentRequest;
    let searchTimer;
    let page = 1;
    let warehouseSearch = '';
    let selectedAddress = '';

    const clearSelectedAddress = () => {
        if (selectedAddress && address.value === selectedAddress) address.value = '';
        selectedAddress = '';
    };

    const request = async (url, params, apply) => {
        currentRequest?.abort();
        const controller = new AbortController();
        currentRequest = controller;
        status.textContent = 'Завантаження…';
        try {
            const response = await fetch(url + '?' + new URLSearchParams(params), {
                headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if (!response.ok) throw new Error();
            const items = await response.json();
            if (controller.signal.aborted) return;
            apply(items);
            status.textContent = items.length ? '' : 'Збігів немає. Спробуйте інший запит або введіть адресу вручну.';
        } catch (error) {
            if (error.name !== 'AbortError') {
                status.textContent = 'Довідник тимчасово недоступний. Адресу можна ввести вручну.';
            }
        }
    };

    const fill = (select, items) => {
        for (const item of items) {
            if (![...select.options].some(option => option.value === item.id)) {
                select.add(new Option(item.name, item.id));
            }
        }
    };

    const findCities = () => {
        currentRequest?.abort();
        clearSelectedAddress();
        byId('np-city-results').hidden = true;
        byId('np-warehouse-results').hidden = true;
        const query = cityQuery.value.trim();
        if (query.length === 1) {
            status.textContent = 'Введіть щонайменше дві літери.';
            return;
        }
        request(fieldset.dataset.cities, { q: query }, items => {
            city.replaceChildren(new Option('Виберіть населений пункт', ''));
            fill(city, items);
            byId('np-city-results').hidden = items.length === 0;
        });
    };

    const findWarehouses = (nextPage = 1) => {
        if (!city.value) return;
        if (nextPage === 1) {
            clearSelectedAddress();
            warehouseSearch = warehouseQuery.value.trim();
            warehouse.replaceChildren(new Option('Виберіть відділення або поштомат', ''));
        }
        more.hidden = true;
        request(fieldset.dataset.warehouses, { city: city.value, q: warehouseSearch, page: nextPage }, items => {
            page = nextPage;
            fill(warehouse, items);
            more.hidden = items.length < 50 || page >= 100;
        });
    };

    city.addEventListener('change', () => {
        clearTimeout(searchTimer);
        currentRequest?.abort();
        clearSelectedAddress();
        byId('np-warehouse-results').hidden = !city.value;
        warehouseQuery.value = '';
        if (city.value) findWarehouses();
        else status.textContent = '';
    });
    more.addEventListener('click', () => findWarehouses(page + 1));
    warehouse.addEventListener('change', () => {
        if (!warehouse.value) {
            clearSelectedAddress();
            return;
        }
        selectedAddress = city.selectedOptions[0].text + ' — ' + warehouse.selectedOptions[0].text;
        address.value = selectedAddress;
    });
    for (const [input, search] of [[cityQuery, findCities], [warehouseQuery, findWarehouses]]) {
        input.addEventListener('input', () => {
            currentRequest?.abort();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => search(), 350);
        });
        input.addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            clearTimeout(searchTimer);
            search();
        });
    }
    fieldset.hidden = false;
    findCities();
</script>
@endpush
