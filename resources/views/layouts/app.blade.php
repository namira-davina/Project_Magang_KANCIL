
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') - KANCIL SAPA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/kancil.css') }}">
</head>
<body>

    <div class="app-container">

        @include('components.sidebar')

        <div class="main-wrapper">

            @include('components.topbar')

            <main class="main-content">
                @yield('content')
            </main>

            @include('components.footer')

        </div>

    </div>

    <script src="{{ asset('js/kancil.js') }}"></script>
    @stack('scripts')

</body>
</html>
