<?php

return [
    'autoload' => false,
    'hooks' => [
        'app_init' => [
            'epay',
        ],
        'wipecache_after' => [
            'tinymce',
        ],
        'set_tinymce' => [
            'tinymce',
        ],
    ],
    'route' => [],
    'priority' => [],
    'domain' => '',
];
