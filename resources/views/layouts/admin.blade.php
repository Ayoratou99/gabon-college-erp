<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'CUK — Back-office')</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary app-loaded">
<div class="app-wrapper">

    @include('partials.admin.sidebar')
    @include('partials.admin.topbar')

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                @include('partials.admin.breadcrumbs', ['items' => $breadcrumbs ?? []])
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </main>

    <footer class="app-footer text-center text-muted small py-3">
        <strong>{{ config('app.name') }}</strong> &copy; {{ now()->year }}
    </footer>
</div>

@stack('scripts')
</body>
</html>
