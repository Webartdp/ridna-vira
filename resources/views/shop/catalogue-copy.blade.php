@push('styles')
    <style>
        .shop-catalogue-copy {
            margin-top: 32px;
            padding: clamp(18px, 4vw, 36px);
        }

        .shop-catalogue-copy__inner {
            max-width: 64ch;
            margin-inline: auto;
            font: 1.0625rem/1.7 var(--rv-font-body);
            overflow-wrap: anywhere;
        }

        .shop-catalogue-copy h2 {
            margin: 0 0 16px;
            font-size: clamp(1.25rem, 2.2vw, 1.625rem);
            line-height: 1.3;
        }

        .shop-catalogue-copy__viewport { --shop-copy-preview-height: 350px; }

        .shop-catalogue-copy__viewport[data-collapsed] {
            max-height: var(--shop-copy-preview-height);
            overflow: hidden;
            mask-image: linear-gradient(#000 calc(100% - 48px), transparent);
        }

        .shop-catalogue-copy__text {
            display: grid;
            gap: 1em;
        }

        .shop-catalogue-copy__text p { margin: 0; }

        .shop-catalogue-copy__toggle {
            margin-top: 16px;
            min-height: 44px;
            font: .9375rem/1.4 var(--rv-font-ui);
        }

        .shop-catalogue-copy__toggle:hover { background: #e3d3b8; }
        .shop-catalogue-copy__toggle:focus-visible { outline: 2px solid #785629; outline-offset: 3px; }
        .shop-catalogue-copy__toggle[hidden] { display: none; }
    </style>
@endpush
@push('scripts')
    <script type="module">
        const viewport = document.querySelector('#shop-catalogue-copy-text');
        const text = viewport.firstElementChild;
        const toggle = document.querySelector('[aria-controls="shop-catalogue-copy-text"]');
        const previewHeight = parseFloat(getComputedStyle(viewport).getPropertyValue('--shop-copy-preview-height'));
        let expanded = false;

        function updatePreview() {
            const canCollapse = text.getBoundingClientRect().height > previewHeight;
            const collapsed = canCollapse && !expanded;

            viewport.toggleAttribute('data-collapsed', collapsed);
            toggle.hidden = !canCollapse;
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.textContent = collapsed ? 'Читати повністю' : 'Згорнути';
        }

        toggle.addEventListener('click', () => {
            expanded = !expanded;
            updatePreview();

            if (!expanded) toggle.scrollIntoView({block: 'nearest'});
        });

        new ResizeObserver(updatePreview).observe(text);
        updatePreview();
    </script>
@endpush

<section class="shop-panel shop-catalogue-copy" aria-labelledby="shop-catalogue-copy-title">
    <div class="shop-catalogue-copy__inner">
        <h2 id="shop-catalogue-copy-title">{{ $heading }}</h2>
        <div id="shop-catalogue-copy-text" class="shop-catalogue-copy__viewport">
            <div class="shop-catalogue-copy__text">
                @foreach ($paragraphs as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>
        <button class="shop-btn shop-secondary shop-catalogue-copy__toggle" type="button" aria-expanded="true" aria-controls="shop-catalogue-copy-text" hidden>
            Читати повністю
        </button>
    </div>
</section>
