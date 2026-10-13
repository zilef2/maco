<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- <script defer data-domain="localhost" src="http://127.0.0.1:8011/js/script.local.js"></script> --}}
        
        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Skeleton de carga inicial (solo OrdenProduccion.index): se pinta mientras
             descargan el bundle y el chunk de la página; Vue lo reemplaza al montar. --}}
        @if (request()->routeIs('OrdenProduccion.index'))
        <script>
            (function () {
                var app = document.getElementById('app');
                if (!app) return;

                var fila =
                    '<div class="flex items-center gap-4 py-3 border-t border-gray-200 dark:border-gray-700">' +
                        '<div class="h-4 w-4 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>' +
                        '<div class="h-4 w-10 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>' +
                        '<div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>' +
                        '<div class="h-4 w-20 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>' +
                        '<div class="h-4 w-16 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>' +
                    '</div>';

                var filas = '';
                for (var i = 0; i < 8; i++) { filas += fila; }

                app.innerHTML =
                    '<div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-4 md:p-6">' +
                        '<div class="h-8 w-52 rounded bg-gray-200 dark:bg-gray-800 animate-pulse mb-6"></div>' +
                        '<div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4">' +
                            '<div class="flex gap-3 mb-4">' +
                                '<div class="h-9 w-28 rounded bg-gray-200 dark:bg-gray-700 animate-pulse"></div>' +
                                '<div class="h-9 w-40 rounded bg-gray-200 dark:bg-gray-700 animate-pulse"></div>' +
                            '</div>' +
                            filas +
                        '</div>' +
                    '</div>';
            })();
        </script>
        @endif
    </body>
</html>
