<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Foliotrak</title>
        <script>
            // Apply the saved theme before first paint so there is no flash of the
            // wrong theme. No stored choice (or "system") follows the OS preference.
            (function () {
                try {
                    var stored = localStorage.getItem('foliotrak-theme')
                    var dark =
                        stored === 'dark'
                            ? true
                            : stored === 'light'
                              ? false
                              : window.matchMedia('(prefers-color-scheme: dark)').matches
                    document.documentElement.classList.toggle('dark', dark)
                } catch (e) {}
            })()
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
