<aside class="app-sidebar shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link text-decoration-none px-3 py-3 d-flex align-items-center">
            <span class="brand-text fw-bold text-white">CUK</span>
            <small class="ms-2 text-white-50">Concours</small>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">
                @foreach($adminMenu ?? [] as $item)
                    @if($item['type'] === 'header')
                        <li class="nav-header">{{ $item['label'] }}</li>
                    @else
                        @php
                            $href = ($item['route'] ?? null)
                                ? route($item['route'], $item['route_params'] ?? [])
                                : '#';
                            $isActive = ($item['route'] ?? null) && request()->routeIs($item['route']);
                        @endphp
                        <li class="nav-item">
                            <a href="{{ $href }}" class="nav-link {{ $isActive ? 'active' : '' }}">
                                <i class="nav-icon {{ $item['icon'] ?? 'far fa-circle' }}"></i>
                                <p class="ms-2 mb-0 d-inline-block">{{ $item['label'] }}</p>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
