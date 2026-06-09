<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>Impulsate - Encuentro de Negocios</title>

        <!-- SEO -->
        <meta name="description" content="Programa tu cita y conecta con los mejores empresarios de Yucatán en el Encuentro de Negocios Impulsate.">

        <!-- Open Graph -->
        <meta property="og:title" content="Impulsate - Encuentro de Negocios">
        <meta property="og:description" content="Programa tu cita y conecta con los mejores empresarios de Yucatán en el Encuentro de Negocios Impulsate.">
        <meta property="og:url" content="https://impulsate.iyemyucatan.com">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Impulsate">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Impulsate - Encuentro de Negocios">
        <meta name="twitter:description" content="Programa tu cita y conecta con los mejores empresarios de Yucatán en el Encuentro de Negocios Impulsate.">

        <!-- Favicon -->
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Theme initialization (prevents dark-mode flash) -->
        <script>
            (function(){
                var t=localStorage.getItem('theme')||'light';
                if(t==='dark')document.documentElement.classList.add('dark');
            })();
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
