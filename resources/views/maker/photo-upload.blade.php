<label for="maker-images">Фотографії</label>
<input id="maker-images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple @required(!$product)
    aria-describedby="maker-images-hint maker-images-status maker-images-error">
<small id="maker-images-hint">До 8 фото JPG, PNG або WebP. До 2 МБ на фото й до 6 МБ загалом.</small>
<p id="maker-images-status" class="maker-photo-feedback" role="status" hidden></p>
<p id="maker-images-error" class="maker-photo-feedback shop-error" role="alert" hidden></p>

@push('scripts')
<script type="module">
    const input = document.querySelector('#maker-images');
    const status = document.querySelector('#maker-images-status');
    const error = document.querySelector('#maker-images-error');
    const form = input.form;
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    const mib = 1024 * 1024;
    const formats = new Map([['image/jpeg', 'jpg'], ['image/png', 'png'], ['image/webp', 'webp']]);
    const compression = [
        { edge: 2000, quality: 0.9 },
        { edge: 2000, quality: 0.8 },
        { edge: 1500, quality: 0.8 },
        { edge: 1100, quality: 0.72 },
    ];

    function feedback(element, message = '') {
        element.textContent = message;
        element.hidden = !message;
    }

    async function prepare(file, position, budget, webpSupported) {
        const label = 'Фото №' + position;
        if (file.size > 25 * mib) {
            throw new Error(label + ' перевищує 25 МБ. Виберіть менший файл.');
        }
        if (!formats.has(file.type) && !/\.(jpe?g|png|webp)$/i.test(file.name)) {
            throw new Error(label + ' має бути у форматі JPG, PNG або WebP.');
        }

        const url = URL.createObjectURL(file);
        const image = new Image();
        image.src = url;
        try {
            await image.decode().catch(() => {
                throw new Error(label + ' не вдалося відкрити. Виберіть інший файл.');
            });
            const width = image.naturalWidth;
            const height = image.naturalHeight;
            const longestEdge = Math.max(width, height);
            if (width * height > 64_000_000) {
                throw new Error(label + ' перевищує 64 мегапікселі. Виберіть фото меншої роздільності.');
            }
            if (file.size <= budget && longestEdge <= compression[0].edge) {
                return file;
            }

            const jpegSource = file.type === 'image/jpeg' || /\.jpe?g$/i.test(file.name);
            const outputType = webpSupported ? 'image/webp' : (jpegSource ? 'image/jpeg' : 'image/png');
            for (const { edge, quality } of compression) {
                const scale = Math.min(1, edge / longestEdge);
                canvas.width = Math.max(1, Math.round(width * scale));
                canvas.height = Math.max(1, Math.round(height * scale));
                context.imageSmoothingQuality = 'high';
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, outputType, quality));
                if (blob?.size && blob.size <= budget && formats.has(blob.type)) {
                    const name = file.name.replace(/\.[^.]+$/, '') + '.' + formats.get(blob.type);
                    return new File([blob], name, { type: blob.type });
                }
            }
            throw new Error(label + ' не вдалося стиснути до потрібного розміру. Виберіть менший файл.');
        } finally {
            URL.revokeObjectURL(url);
            canvas.width = canvas.height = 1;
        }
    }

    if (context && typeof Image.prototype.decode === 'function'
        && typeof DataTransfer !== 'undefined' && typeof form.requestSubmit === 'function') {
        const webpSupported = canvas.toDataURL('image/webp').startsWith('data:image/webp');
        const hint = document.querySelector('#maker-images-hint');
        hint.textContent = 'До 8 фото JPG, PNG або WebP, до 25 МБ кожне. Великі фото автоматично зменшуються перед завантаженням.';
        let selection = 0;
        let preparing = false;
        let prepared = false;

        input.addEventListener('change', () => {
            selection++;
            prepared = false;
            feedback(status);
            feedback(error);
        });

        form.addEventListener('submit', async event => {
            const files = Array.from(input.files);
            if (prepared) {
                if (event.submitter) event.submitter.disabled = true;
                return;
            }
            if (!preparing && files.length === 0) return;
            event.preventDefault();
            if (preparing) return;
            feedback(error);
            if (files.length > 8) {
                feedback(error, 'Виберіть до 8 фотографій.');
                return;
            }

            const current = selection;
            const submitter = event.submitter;
            const budget = Math.min(2 * mib, Math.floor(6 * mib / files.length));
            preparing = true;
            if (submitter) submitter.disabled = true;
            try {
                const transfer = new DataTransfer();
                for (const [index, file] of files.entries()) {
                    feedback(status, 'Підготовка фото ' + (index + 1) + ' з ' + files.length + '…');
                    const photo = await prepare(file, index + 1, budget, webpSupported);
                    if (selection !== current) return;
                    transfer.items.add(photo);
                }
                input.files = transfer.files;
                prepared = true;
            } catch (failure) {
                if (selection === current) {
                    feedback(error, failure instanceof Error && failure.message.startsWith('Фото №')
                        ? failure.message
                        : 'Не вдалося підготувати фотографії. Виберіть їх повторно або спробуйте менші файли.');
                }
            } finally {
                preparing = false;
                if (submitter) submitter.disabled = false;
                feedback(status);
            }

            if (prepared) {
                feedback(status, 'Завантаження фотографій…');
                form.requestSubmit(submitter ?? undefined);
            }
        });
    }
</script>
@endpush
