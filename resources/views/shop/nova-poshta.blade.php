<div id="nova-poshta-lookup" hidden data-cities="{{ route('shop.nova-poshta.cities') }}" data-warehouses="{{ route('shop.nova-poshta.warehouses') }}">
    <div class="np-field">
        <label for="np-city-search">Населений пункт</label>
        <input id="np-city-search" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false"
            aria-controls="np-city-options" placeholder="Почніть вводити назву" maxlength="100" autocomplete="off">
        <ul id="np-city-options" class="np-options" role="listbox" aria-label="Населені пункти" hidden></ul>
    </div>
    <div class="np-field">
        <label for="np-warehouse-search">Відділення або поштомат</label>
        <input id="np-warehouse-search" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false"
            aria-controls="np-warehouse-options" placeholder="Спочатку виберіть населений пункт" maxlength="1000" autocomplete="off" disabled>
        <ul id="np-warehouse-options" class="np-options" role="listbox" aria-label="Відділення й поштомати" hidden></ul>
    </div>
    <small id="np-status" role="status"></small>
</div>
<button class="np-manual-toggle" id="np-manual-toggle" type="button" aria-controls="np-manual-address" aria-expanded="false" hidden>Ввести адресу вручну</button>

@push('styles')
<style>
    .np-field{position:relative}
    .np-options:not([hidden]){position:absolute;z-index:5;inset-inline:0;top:100%;max-height:17rem;overflow:auto;list-style:none;margin:4px 0 0;padding:4px;background:#fffdf6;border:1px solid #cdbd9f;border-radius:8px;box-shadow:0 6px 18px #302a2226;overscroll-behavior:contain}
    .np-options li{padding:10px 12px;border-radius:5px;cursor:pointer;overflow-wrap:anywhere}
    .np-options li:hover,.np-options [aria-selected=true]{background:#eee5d3}
    .np-manual-toggle{margin-top:12px;padding:4px 0;border:0;background:transparent;text-decoration:underline;color:#493a23;cursor:pointer}
    #np-status:not(:empty){margin-top:6px}
</style>
@endpush

@push('scripts')
<script type="module">
    const byId = id => document.getElementById(id);
    const lookup = byId('nova-poshta-lookup');
    const address = byId('delivery-address');
    const manualAddress = byId('np-manual-address');
    const toggle = byId('np-manual-toggle');
    const status = byId('np-status');
    let selectedCity;
    let selectedWarehouse;
    let formattedAddress = '';

    const createPicker = (input, list, load, select, change) => {
        let items = [];
        let active = -1;
        let timer;
        let request;

        const close = () => {
            active = -1;
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            for (const option of list.children) option.setAttribute('aria-selected', 'false');
        };

        const clear = () => {
            clearTimeout(timer);
            request?.abort();
            items = [];
            list.replaceChildren();
            close();
        };

        const open = () => {
            if (!items.length || document.activeElement !== input || input.disabled) return;
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const search = async () => {
            clear();
            const query = input.value.trim();
            if (input.disabled) return;
            const controller = new AbortController();
            request = controller;
            status.textContent = 'Завантаження…';
            try {
                const response = await load(query, controller.signal);
                if (controller.signal.aborted) return;
                items = response;
                items.forEach((item, index) => {
                    const option = document.createElement('li');
                    option.id = list.id + '-' + index;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.textContent = item.name;
                    option.addEventListener('mousedown', event => event.preventDefault());
                    option.addEventListener('click', () => choose(item));
                    list.append(option);
                });
                open();
                status.textContent = response.length ? '' : 'Збігів немає. Уточніть пошук або введіть адресу вручну.';
            } catch {
                if (!controller.signal.aborted) status.textContent = 'Довідник тимчасово недоступний. Введіть адресу вручну.';
            }
        };

        const choose = item => {
            input.value = item.name;
            input.setCustomValidity('');
            clear();
            status.textContent = '';
            select(item);
        };

        input.addEventListener('input', () => {
            clear();
            change();
            timer = setTimeout(search, 300);
        });
        input.addEventListener('focus', open);
        input.addEventListener('blur', close);
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !list.hidden) {
                event.preventDefault();
                close();
            } else if (event.key === 'Enter' && !list.hidden && active >= 0) {
                event.preventDefault();
                choose(items[active]);
            } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                if (!items.length) return;
                event.preventDefault();
                open();
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                active = active < 0 ? (direction > 0 ? 0 : items.length - 1) : (active + direction + items.length) % items.length;
                [...list.children].forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
                const option = list.children[active];
                input.setAttribute('aria-activedescendant', option.id);
                option.scrollIntoView({ block: 'nearest' });
            }
        });

        return { input, search, clear };
    };

    const fetchOptions = async (url, params, signal) => {
        const response = await fetch(url + '?' + new URLSearchParams(params), { signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error();
        return response.json();
    };

    const clearWarehouse = () => {
        selectedWarehouse = null;
        formattedAddress = '';
        address.value = '';
        warehousePicker.input.setCustomValidity('Виберіть відділення або поштомат зі списку.');
    };

    const cityPicker = createPicker(byId('np-city-search'), byId('np-city-options'), (query, signal) =>
        query.length === 1 ? Promise.resolve([]) : fetchOptions(lookup.dataset.cities, { q: query }, signal),
    item => {
        selectedCity = item;
        warehousePicker.input.disabled = false;
        warehousePicker.input.placeholder = 'Введіть номер або адресу';
        warehousePicker.input.focus();
        warehousePicker.search();
    }, () => {
        selectedCity = null;
        cityPicker.input.setCustomValidity('Виберіть населений пункт зі списку.');
        warehousePicker.clear();
        warehousePicker.input.value = '';
        warehousePicker.input.disabled = true;
        warehousePicker.input.placeholder = 'Спочатку виберіть населений пункт';
        clearWarehouse();
    });

    const warehousePicker = createPicker(byId('np-warehouse-search'), byId('np-warehouse-options'), (query, signal) =>
        fetchOptions(lookup.dataset.warehouses, { city: selectedCity.id, q: query.slice(0, 100), page: 1 }, signal),
    item => {
        selectedWarehouse = item;
        formattedAddress = selectedCity.name + ' — ' + item.name;
        address.value = formattedAddress;
    }, clearWarehouse);

    const setManual = manual => {
        lookup.hidden = manual;
        manualAddress.hidden = !manual;
        toggle.textContent = manual ? 'Вибрати з довідника' : 'Ввести адресу вручну';
        toggle.setAttribute('aria-expanded', String(manual));
        address.required = manual;
        cityPicker.input.disabled = manual;
        cityPicker.input.required = !manual;
        warehousePicker.input.disabled = manual || !selectedCity;
        warehousePicker.input.required = !manual;
        if (manual) {
            cityPicker.clear();
            warehousePicker.clear();
        } else {
            address.value = formattedAddress;
            if (!selectedCity) cityPicker.search();
            else if (!selectedWarehouse) warehousePicker.search();
        }
        cityPicker.input.setCustomValidity(!manual && !selectedCity ? 'Виберіть населений пункт зі списку.' : '');
        warehousePicker.input.setCustomValidity(!manual && !selectedWarehouse ? 'Виберіть відділення або поштомат зі списку.' : '');
    };

    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const manual = manualAddress.hidden;
        setManual(manual);
        (manual ? address : cityPicker.input).focus();
    });
    setManual(manualAddress.dataset.restore === 'yes');
</script>
@endpush
