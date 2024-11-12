<?php

use Workbench\App\Models\Author;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

return [
    'resources' => [
        Post::class => [
            'name' => 'Posts',
            'listing' => [
                'columns' => [
                    'title',
                ],
                'sort' => [
                    'column' => 'title',
                    'direction' => 'desc',
                ],
            ],
            'route' => '/posts/{{ slug }}',
            'published' => true,
            'revisions' => true,
        ],

        Author::class => [
            'name' => 'Authors',
            'listing' => [
                'columns' => [
                    'name',
                ],
                'sort' => [
                    'column' => 'name',
                    'direction' => 'asc',
                ],
            ],
        ],

        User::class => [
            'name' => 'Users',
        ],
    ],
];
