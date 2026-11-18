@push('scripts')
<script type="module">
    const checkout = document.querySelector('#checkout-form');
    const rows = [...document.querySelectorAll('.cart-quantity')].map(form => {
        const input = form.elements.quantity;
        const product = form.closest('.cart-product');

        return { form, input, product, status: product.querySelector('[data-quantity-status]'), saved: input.value, failed: false };
    });
    let timer;
    let running;
    let waitingForQuantity = false;
    let submitting = false;

    const save = async row => {
        const quantity = row.input.value;
        const body = new FormData(row.form);
        body.set('quantity', String(row.input.valueAsNumber));
        try {
            const response = await fetch(row.form.action, {
                method: 'POST', body, headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                const errors = response.status === 422 ? (await response.json()).errors : null;
                throw new Error(errors?.cart?.[0] ?? errors?.quantity?.[0] ?? 'Кількість не вдалося оновити. Спробуйте ще раз.');
            }
            const result = await response.json();
            row.saved = String(result.quantity);
            if (row.input.value === quantity) row.input.value = row.saved;
            row.product.querySelector('[data-line-total]').textContent = result.lineTotal;
            row.product.querySelector('[data-unit-price]').textContent = result.unitPrice;
            row.product.querySelector('[data-stock-warning]')?.remove();
            for (const total of document.querySelectorAll('[data-cart-total]')) total.textContent = result.total;
            for (const basket of document.querySelectorAll('.basket-link')) {
                const count = basket.querySelector('b');
                if (count) count.textContent = result.count;
                basket.setAttribute('aria-label', 'Кошик, кількість товарів: ' + result.count);
            }
            row.status.textContent = '';
        } catch (error) {
            if (row.input.value !== quantity) return;
            row.failed = true;
            row.status.textContent = error instanceof TypeError
                ? 'Не вдалося з’єднатися з сайтом. Спробуйте ще раз.'
                : error.message;
        }
    };

    const flush = () => {
        clearTimeout(timer);
        if (!running) {
            running = (async () => {
                let row;
                while ((row = rows.find(candidate => !candidate.failed && candidate.input.validity.valid && candidate.input.value !== candidate.saved))) {
                    await save(row);
                }
            })().finally(() => { running = null; });
        }
        return running;
    };

    for (const row of rows) {
        row.input.addEventListener('input', () => {
            row.failed = false;
            row.status.textContent = '';
            clearTimeout(timer);
            timer = setTimeout(flush, 250);
        });
        row.input.addEventListener('change', flush);
        row.form.addEventListener('submit', event => {
            event.preventDefault();
            row.failed = false;
            flush();
        });
    }

    checkout.addEventListener('submit', async event => {
        if (submitting) return;
        event.preventDefault();
        if (waitingForQuantity) return;
        waitingForQuantity = true;
        await flush();
        waitingForQuantity = false;
        const unsaved = rows.find(row => row.input.value !== row.saved || !row.input.validity.valid);
        if (unsaved) {
            unsaved.input.focus();
            unsaved.input.reportValidity();
            return;
        }
        submitting = true;
        checkout.requestSubmit(event.submitter);
        submitting = false;
    });

    for (const form of document.querySelectorAll('[data-cart-remove]')) {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            await flush();
            form.submit();
        });
    }
</script>
@endpush
