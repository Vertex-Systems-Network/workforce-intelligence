<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    // Do not wrap this in realpath(). Clean ZIP deployments may not have the
    // runtime directory until first boot; Laravel can create/use this path
    // once storage/framework is writable.
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),
];
