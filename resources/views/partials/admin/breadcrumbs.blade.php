@props(['items' => []])

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        @hasSection('page-title')
            <h1 class="h3 mb-1">@yield('page-title')</h1>
        @endif
        @if(!empty($items))
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    @foreach($items as $i => $b)
                        @if($loop->last)
                            <li class="breadcrumb-item active">{{ $b['label'] }}</li>
                        @else
                            <li class="breadcrumb-item">
                                <a href="{{ $b['url'] ?? '#' }}">{{ $b['label'] }}</a>
                            </li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif
    </div>

    @hasSection('page-actions')
        <div>@yield('page-actions')</div>
    @endif
</div>
