<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        {{-- Follow the visitor's light / dark preference (they have no Knowbase account settings). --}}
        <script>
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <title>{{ $config['teamName'] }} · Help</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/widget/main.ts'])
    </head>
    <body class="font-sans antialiased">
        {{-- Blade escapes the JSON, so a greeting can never inject HTML. --}}
        <div id="widget" data-config="{{ json_encode($config) }}"></div>
    </body>
</html>
