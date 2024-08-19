<?php

return [
    'autoload' => false,
    'hooks' => [
        'epay_config_init' => [
            'epay',
        ],
        'addon_action_begin' => [
            'epay',
        ],
        'action_begin' => [
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
