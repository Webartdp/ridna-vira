<div class="avatar-crop" id="avatar-crop" hidden>
    <canvas width="512" height="512" tabindex="0" aria-label="Кадрування аватарки" aria-describedby="avatar-crop-help"></canvas>
    <div class="avatar-crop-controls">
        <p id="avatar-crop-help">Перетягніть фото або скористайтеся клавішами зі стрілками.</p>
        <label for="avatar-zoom">Масштаб</label>
        <input id="avatar-zoom" type="range" min="1" max="3" step="0.01" value="1">
        <button class="shop-btn shop-secondary" id="avatar-reset" type="button">Скинути кадрування</button>
    </div>
</div>
<p id="avatar-crop-error" class="shop-error" role="alert" hidden></p>

@push('styles')
<style>
.avatar-crop:not([hidden]){display:flex;flex-wrap:wrap;align-items:center;gap:20px;margin:16px 0}
.avatar-crop canvas{display:block;width:min(224px,100%);height:auto;border-radius:50%;outline:1px solid #d7cab3;touch-action:none;cursor:grab}
.avatar-crop canvas:active{cursor:grabbing}
.avatar-crop canvas:focus-visible{outline:3px solid #846532;outline-offset:3px}
.avatar-crop-controls{flex:1 1 190px;min-width:0;max-width:300px}
.avatar-crop-controls p{margin:0}
.avatar-crop-controls input[type=range]{padding:0;margin-bottom:12px;accent-color:#493a23}
</style>
@endpush

@push('scripts')
<script type="module">
    const input = document.querySelector('#profile-avatar');
    const editor = document.querySelector('#avatar-crop');
    const canvas = editor.querySelector('canvas');
    const context = canvas.getContext('2d');
    const zoom = document.querySelector('#avatar-zoom');
    const error = document.querySelector('#avatar-crop-error');
    const form = input.form;

    if (context && typeof DataTransfer !== 'undefined') {
        const size = canvas.width;
        let image = null;
        let x = 0;
        let y = 0;
        let pointer = null;
        let readyFile = null;
        let submitting = false;

        const showError = message => {
            input.setCustomValidity(message);
            error.textContent = message;
            error.hidden = !message;
        };

        const draw = () => {
            if (!image) return;

            const scale = size / Math.min(image.naturalWidth, image.naturalHeight) * Number(zoom.value);
            const width = image.naturalWidth * scale;
            const height = image.naturalHeight * scale;
            const limitX = Math.max(0, (width - size) / 2);
            const limitY = Math.max(0, (height - size) / 2);
            x = Math.max(-limitX, Math.min(limitX, x));
            y = Math.max(-limitY, Math.min(limitY, y));
            context.clearRect(0, 0, size, size);
            context.drawImage(image, (size - width) / 2 + x, (size - height) / 2 + y, width, height);
        };

        const reset = () => {
            zoom.value = '1';
            x = y = 0;
            draw();
        };

        input.addEventListener('change', async () => {
            const file = input.files[0];
            image = readyFile = pointer = null;
            editor.hidden = true;
            showError('');
            if (!file) return;
            if (file.size > 25 * 1024 * 1024) {
                showError('Виберіть фото розміром до 25 МБ.');
                return;
            }

            const url = URL.createObjectURL(file);
            const candidate = new Image();
            candidate.src = url;
            try {
                await candidate.decode();
                if (input.files[0] !== file) return;
                if (candidate.naturalWidth * candidate.naturalHeight > 64_000_000) {
                    showError('Виберіть фото з роздільністю до 64 мегапікселів.');
                    return;
                }
                image = candidate;
                editor.hidden = false;
                reset();
            } catch {
                if (input.files[0] === file) showError('Не вдалося відкрити фото. Виберіть JPG, PNG або WebP.');
            } finally {
                URL.revokeObjectURL(url);
            }
        });

        zoom.addEventListener('input', draw);
        document.querySelector('#avatar-reset').addEventListener('click', reset);
        canvas.addEventListener('pointerdown', event => {
            if (!event.isPrimary || event.button !== 0) return;
            pointer = { id: event.pointerId, x: event.clientX, y: event.clientY };
            canvas.setPointerCapture(event.pointerId);
        });
        canvas.addEventListener('pointermove', event => {
            if (pointer?.id !== event.pointerId) return;
            const ratio = size / canvas.getBoundingClientRect().width;
            x += (event.clientX - pointer.x) * ratio;
            y += (event.clientY - pointer.y) * ratio;
            pointer.x = event.clientX;
            pointer.y = event.clientY;
            draw();
        });
        canvas.addEventListener('lostpointercapture', () => { pointer = null; });
        canvas.addEventListener('keydown', event => {
            const step = { ArrowLeft: [-12, 0], ArrowRight: [12, 0], ArrowUp: [0, -12], ArrowDown: [0, 12] }[event.key];
            if (!step) return;
            event.preventDefault();
            x += step[0];
            y += step[1];
            draw();
        });

        form.addEventListener('submit', event => {
            const original = input.files[0];
            if (readyFile && original === readyFile) return;
            event.preventDefault();
            if (submitting || !image) return;

            submitting = true;
            canvas.toBlob(blob => {
                submitting = false;
                if (input.files[0] !== original) return;
                if (blob?.type !== 'image/webp') {
                    showError('Не вдалося обрізати фото. Виберіть його ще раз.');
                    return;
                }
                const transfer = new DataTransfer();
                transfer.items.add(new File([blob], 'avatar.webp', { type: blob.type }));
                input.files = transfer.files;
                readyFile = input.files[0];
                form.requestSubmit();
            }, 'image/webp', 0.88);
        });
    }
</script>
@endpush
