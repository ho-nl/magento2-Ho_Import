<?php

$products = [[
    'name' => 'string'
]];

$flatProducts = [[
    '_store' => 'admin', //is it admin or default?
    'name' => 'string'
]];

$product = [
    'name' => [
        'default' => 'string',
        'my_store' => 'string2',
    ]
];

$flatProducts = [
    [
        '_store' => 'admin',
        'name' => 'string',
    ],
    [
        '_store' => 'my_store',
        'name' => 'string2'
    ]
];

$product = [[
    '_category' => [
        'Root/Category/Subcategory',
        'Root/Category/Another Subcategory',
        'Root/Category/Escaped\/Slashes'
    ]
]];

$flatProducts = [
    [
        '_store' => 'admin',
        '_category' => 'Root/Category/Subcategory'
    ],
    [
        '_category' => 'Root/Category/Another Subcategory'
    ],
    [
        '_category' => 'Root/Category/Escaped\/Slashes'
    ],
];

$product = [
    'group1' => [
        'category' => 'Root/Category/Subcategory'
    ],
    'group2' => [
        'category' => [
            'Root/Category/Another Subcategory',
            'Root/Category/Escaped\/Slashes'
        ]
    ]
];

$flatProducts = [
    [
        '_store' => 'admin',
        '_category' => 'Root/Category/Subcategory'
    ],
    [
        '_category' => 'Root/Category/Another Subcategory'
    ],
    [
        '_category' => 'Root/Category/Escaped\/Slashes'
    ],
];
