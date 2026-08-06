const contactPanel = document.querySelector('.contact-action-panel');

if (contactPanel) {
    contactPanel.classList.add('contact-action-panel--compact');
    contactPanel.innerHTML = `
        <div class="contact-action-panel__compact">
            <div>
                <span>Офіційне звернення</span>
                <h2 id="contact-action-title">Звернутися до Управи</h2>
                <p>Питання щодо вступу, створення громади, обрядів, заходів або співпраці.</p>
            </div>
            <button class="figma-button" type="button" data-contact-form-open>Написати Управі</button>
        </div>
    `;

    const modal = document.createElement('dialog');
    modal.className = 'contact-form-modal';
    modal.setAttribute('data-contact-form-modal', '');
    modal.setAttribute('aria-labelledby', 'contact-form-modal-title');
    modal.innerHTML = `
        <div class="contact-form-modal__surface">
            <header class="contact-form-modal__header">
                <div>
                    <span>Офіційне звернення</span>
                    <h2 id="contact-form-modal-title">Написати Управі</h2>
                </div>
                <button class="contact-form-modal__close" type="button" data-contact-form-close aria-label="Закрити форму">×</button>
            </header>

            <div class="contact-form-modal__body">
                <p class="contact-form-modal__lead">Заповніть поля нижче. Звернення буде надіслано на офіційну пошту Управи.</p>

                <form class="contact-form" action="/zviazok" method="post" data-contact-form novalidate>
                    <div class="contact-form__grid">
                        <label class="contact-form__field">
                            <span>Ім’я та прізвище <b aria-hidden="true">*</b></span>
                            <input type="text" name="name" autocomplete="name" maxlength="120" required>
                            <small class="contact-form__error" data-error-for="name"></small>
                        </label>

                        <label class="contact-form__field">
                            <span>Електронна пошта <b aria-hidden="true">*</b></span>
                            <input type="email" name="email" autocomplete="email" maxlength="190" required>
                            <small class="contact-form__error" data-error-for="email"></small>
                        </label>

                        <label class="contact-form__field">
                            <span>Телефон</span>
                            <input type="tel" name="phone" autocomplete="tel" maxlength="40" placeholder="+38 0__ ___ __ __">
                            <small class="contact-form__error" data-error-for="phone"></small>
                        </label>

                        <label class="contact-form__field">
                            <span>Місто / область</span>
                            <input type="text" name="city" autocomplete="address-level2" maxlength="120">
                            <small class="contact-form__error" data-error-for="city"></small>
                        </label>

                        <label class="contact-form__field">
                            <span>Тема звернення <b aria-hidden="true">*</b></span>
                            <select name="topic" required>
                                <option value="">Оберіть тему</option>
                                <option value="membership">Вступ до Духовного центру</option>
                                <option value="community">Створення або приєднання громади</option>
                                <option value="ritual">Проведення обряду чи святодії</option>
                                <option value="event">Лекція, зустріч або інший захід</option>
                                <option value="cooperation">Співпраця</option>
                                <option value="other">Інше питання</option>
                            </select>
                            <small class="contact-form__error" data-error-for="topic"></small>
                        </label>

                        <label class="contact-form__field">
                            <span>Назва громади або організації</span>
                            <input type="text" name="organization" maxlength="190">
                            <small class="contact-form__error" data-error-for="organization"></small>
                        </label>

                        <label class="contact-form__field contact-form__field--wide">
                            <span>Текст звернення <b aria-hidden="true">*</b></span>
                            <textarea name="message" rows="4" minlength="20" maxlength="4000" required placeholder="Коротко опишіть суть звернення та зручний спосіб зв’язку"></textarea>
                            <small class="contact-form__error" data-error-for="message"></small>
                        </label>
                    </div>

                    <label class="contact-form__consent">
                        <input type="checkbox" name="consent" value="1" required>
                        <span>Погоджуюся на обробку вказаних даних виключно для розгляду цього звернення.</span>
                    </label>
                    <small class="contact-form__error contact-form__error--consent" data-error-for="consent"></small>

                    <label class="contact-form__honeypot" aria-hidden="true">
                        <span>Не заповнюйте це поле</span>
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>

                    <div class="contact-form__footer">
                        <p>Відповідь буде надіслано на вказану електронну пошту. Поля із зірочкою обов’язкові.</p>
                        <button class="figma-button" type="submit" data-contact-form-submit>Надіслати звернення</button>
                    </div>

                    <div class="contact-form__status" data-contact-form-status role="status" aria-live="polite" hidden></div>
                </form>
            </div>
        </div>
    `;

    document.body.append(modal);

    const openButton = contactPanel.querySelector('[data-contact-form-open]');
    const closeButton = modal.querySelector('[data-contact-form-close]');
    const form = modal.querySelector('[data-contact-form]');
    const submitButton = modal.querySelector('[data-contact-form-submit]');
    const status = modal.querySelector('[data-contact-form-status]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const xsrfCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));
    const xsrfToken = xsrfCookie
        ? decodeURIComponent(xsrfCookie.substring('XSRF-TOKEN='.length))
        : '';

    const openModal = () => {
        if (typeof modal.showModal === 'function') {
            modal.showModal();
        } else {
            modal.setAttribute('open', '');
        }

        document.body.classList.add('contact-form-modal-open');
        window.setTimeout(() => form.elements.namedItem('name')?.focus(), 80);
    };

    const closeModal = () => {
        if (typeof modal.close === 'function') {
            modal.close();
        } else {
            modal.removeAttribute('open');
        }

        document.body.classList.remove('contact-form-modal-open');
        openButton?.focus();
    };

    openButton?.addEventListener('click', openModal);
    closeButton?.addEventListener('click', closeModal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    modal.addEventListener('close', () => {
        document.body.classList.remove('contact-form-modal-open');
    });

    const clearErrors = () => {
        form.querySelectorAll('.is-invalid').forEach((field) => {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
        });

        form.querySelectorAll('[data-error-for]').forEach((message) => {
            message.textContent = '';
        });

        status.hidden = true;
        status.className = 'contact-form__status';
        status.textContent = '';
    };

    const showFieldErrors = (errors = {}) => {
        Object.entries(errors).forEach(([name, messages]) => {
            const field = form.elements.namedItem(name);
            const message = form.querySelector(`[data-error-for="${name}"]`);

            if (field instanceof HTMLElement) {
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');
            }

            if (message) {
                message.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            }
        });

        const firstInvalid = form.querySelector('.is-invalid');
        firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(() => firstInvalid?.focus(), 250);
    };

    const showStatus = (message, type) => {
        status.hidden = false;
        status.className = `contact-form__status is-${type}`;
        status.textContent = message;
        status.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();

        submitButton.disabled = true;
        submitButton.textContent = 'Надсилання…';

        try {
            const headers = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };

            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            } else if (xsrfToken) {
                headers['X-XSRF-TOKEN'] = xsrfToken;
            }

            const response = await fetch(form.action, {
                method: 'POST',
                headers,
                body: new FormData(form),
                credentials: 'same-origin',
            });

            const payload = await response.json().catch(() => ({}));

            if (response.status === 422) {
                showFieldErrors(payload.errors ?? {});
                showStatus('Перевірте заповнення виділених полів.', 'error');
                return;
            }

            if (!response.ok) {
                throw new Error(payload.message || 'Не вдалося надіслати звернення.');
            }

            form.reset();
            showStatus(payload.message || 'Звернення успішно надіслано.', 'success');
        } catch (error) {
            showStatus(error instanceof Error ? error.message : 'Не вдалося надіслати звернення.', 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Надіслати звернення';
        }
    });
}
