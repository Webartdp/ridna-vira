@if ($paginator->hasPages())
    @php
        $pages = collect([1, $paginator->currentPage() - 1, $paginator->currentPage(), $paginator->currentPage() + 1, $paginator->lastPage()])
            ->filter(fn ($page) => $page >= 1 && $page <= $paginator->lastPage())
            ->unique()->sort()->values();
        $previousPage = 0;
    @endphp
    <nav class="articles-pager" aria-label="Сторінки статей">
        @if ($paginator->onFirstPage())
            <span class="articles-pager__step is-disabled" aria-disabled="true"><span aria-hidden="true">←</span> Попередня</span>
        @else
            <a class="articles-pager__step" href="{{ $paginator->previousPageUrl() }}" rel="prev"><span aria-hidden="true">←</span> Попередня</a>
        @endif

        <ul class="articles-pager__pages">
            @foreach ($pages as $page)
                @if ($previousPage && $page > $previousPage + 1)
                    <li><span class="articles-pager__gap" aria-hidden="true">…</span></li>
                @endif
                <li>
                    @if ($page === $paginator->currentPage())
                        <span class="articles-pager__number is-current" aria-current="page" aria-label="Сторінка {{ $page }}, поточна">{{ $page }}</span>
                    @else
                        <a class="articles-pager__number" href="{{ $paginator->url($page) }}" aria-label="Сторінка {{ $page }}">{{ $page }}</a>
                    @endif
                </li>
                @php $previousPage = $page; @endphp
            @endforeach
        </ul>

        @if ($paginator->hasMorePages())
            <a class="articles-pager__step articles-pager__step--next" href="{{ $paginator->nextPageUrl() }}" rel="next">Наступна <span aria-hidden="true">→</span></a>
        @else
            <span class="articles-pager__step articles-pager__step--next is-disabled" aria-disabled="true">Наступна <span aria-hidden="true">→</span></span>
        @endif
    </nav>
@endif
