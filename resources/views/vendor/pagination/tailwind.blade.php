@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="flex gap-2 items-center justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;color:#94A3B8;background:#fff;border:1px solid #E2E8F0;border-radius:6px;cursor:not-allowed;">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;color:#475569;background:#fff;border:1px solid #E2E8F0;border-radius:6px;text-decoration:none;">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;color:#475569;background:#fff;border:1px solid #E2E8F0;border-radius:6px;text-decoration:none;">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;color:#94A3B8;background:#fff;border:1px solid #E2E8F0;border-radius:6px;cursor:not-allowed;">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:gap-2 sm:items-center sm:justify-between">
            <div>
                <p style="font-size:12px;color:#64748B;line-height:1.5;">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span style="font-weight:600;color:#334155;">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span style="font-weight:600;color:#334155;">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('of') !!}
                    <span style="font-weight:600;color:#334155;">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                <span style="display:inline-flex;flex-direction:row;box-shadow:0 1px 2px rgba(15,23,42,0.05);border-radius:6px;">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span style="display:inline-flex;align-items:center;padding:4px 8px;font-size:12px;font-weight:500;color:#CBD5E1;background:#fff;border:1px solid #E2E8F0;border-radius:6px 0 0 6px;cursor:not-allowed;" aria-hidden="true">
                                <svg style="width:16px;height:16px;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="display:inline-flex;align-items:center;padding:4px 8px;font-size:12px;font-weight:500;color:#94A3B8;background:#fff;border:1px solid #E2E8F0;border-radius:6px 0 0 6px;text-decoration:none;" aria-label="{{ __('pagination.previous') }}">
                            <svg style="width:16px;height:16px;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;color:#94A3B8;background:#fff;border:1px solid #E2E8F0;margin-left:-1px;cursor:default;">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:600;color:#fff;background:#2E75B6;border:1px solid #2E75B6;margin-left:-1px;cursor:default;">{{ $page }}</span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;color:#64748B;background:#fff;border:1px solid #E2E8F0;margin-left:-1px;text-decoration:none;" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="display:inline-flex;align-items:center;padding:4px 8px;font-size:12px;font-weight:500;color:#94A3B8;background:#fff;border:1px solid #E2E8F0;margin-left:-1px;border-radius:0 6px 6px 0;text-decoration:none;" aria-label="{{ __('pagination.next') }}">
                            <svg style="width:16px;height:16px;" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span style="display:inline-flex;align-items:center;padding:4px 8px;font-size:12px;font-weight:500;color:#CBD5E1;background:#fff;border:1px solid #E2E8F0;margin-left:-1px;border-radius:0 6px 6px 0;cursor:not-allowed;" aria-hidden="true">
                                <svg style="width:16px;height:16px;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
