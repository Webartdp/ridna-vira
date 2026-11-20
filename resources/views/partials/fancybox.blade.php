@once
    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.14/dist/fancybox/fancybox.css">
    @endpush
    @push('scripts')
        <script defer src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.14/dist/fancybox/fancybox.umd.js"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.14/dist/fancybox/l10n/uk_UA.umd.js"></script>
    @endpush
@endonce

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const fancybox = window.Fancybox;
            const options = { ...@js($fancyboxOptions ?? []) };
            if (fancybox?.l10n?.uk_UA) options.l10n = fancybox.l10n.uk_UA;
            fancybox?.bind?.(@js($fancyboxSelector), options);
        });
    </script>
@endpush
