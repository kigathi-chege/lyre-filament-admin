<?php

return [
    'enabled' => true,

    'discovery' => [
        'namespaces' => null,
        'include' => [],
        'exclude' => [
            'App\\Models\\User',
        ],
    ],

    'coexist' => [
        'respect_hand_written' => true,
        'handwritten_namespaces' => [
            'App\\Filament\\Resources',
        ],
    ],

    'use_repository' => true,

    'authorization' => [
        'fallback' => 'deny',
        'super_admin_role' => 'super_admin',
    ],

    'runtime' => [
        'namespace' => 'Lyre\\Filament\\Admin\\Runtime\\Generated',
        'path' => null,
    ],

    'navigation' => [
        'group_map' => [],
        'icon_default' => 'heroicon-o-cube',
    ],

    'forms' => [
        'show_system_fields_on_edit' => false,
    ],

    'tables' => [
        'default_per_page' => 25,
        'bulk_actions' => true,
    ],

    'polymorphic' => [
        'read_only' => true,
    ],

    'cache' => [
        'metadata_store' => null,
    ],
];
