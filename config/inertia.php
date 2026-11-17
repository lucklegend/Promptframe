<?php

/*
| Overrides for inertiajs/inertia-laravel. Unlisted keys fall back to the
| package defaults. Pages live in resources/js/pages (lowercase), which
| matters on case-sensitive filesystems such as the Linux CI runner.
*/

$pagePaths = [resource_path('js/pages')];

$pageExtensions = ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'];

return [

    'page_paths' => $pagePaths,

    'page_extensions' => $pageExtensions,

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => $pagePaths,

        'page_extensions' => $pageExtensions,

    ],

];
