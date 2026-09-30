<?php

/**
 * Template: Catering order.
 */
return [
    'name' => 'Catering order',
    'category' => 'Orders & booking',
    'description' => 'Food orders with guest counts and delivery details.',
    'form' => [
        'name' => 'Catering order',
        'fields' => [
            [
                'type' => 'text',
                'data' => [
                    'label' => 'Full name',
                    'required' => true,
                    'width' => 'half',
                ],
            ],
            [
                'type' => 'email',
                'data' => [
                    'label' => 'Email',
                    'required' => true,
                    'width' => 'half',
                ],
            ],
            [
                'type' => 'phone',
                'data' => [
                    'label' => 'Phone',
                    'required' => true,
                    'width' => 'half',
                ],
            ],
            [
                'type' => 'datetime',
                'data' => [
                    'label' => 'Delivery date and time',
                    'required' => true,
                    'width' => 'half',
                ],
            ],
            [
                'type' => 'number',
                'data' => [
                    'label' => 'Number of guests',
                    'min' => 1,
                    'required' => true,
                    'width' => 'half',
                ],
            ],
            [
                'type' => 'select',
                'data' => [
                    'label' => 'Menu',
                    'choices' => [
                        'standard' => 'Standard',
                        'vegetarian' => 'Vegetarian',
                        'premium' => 'Premium',
                    ],
                    'required' => true,
                    'width' => 'half',
                ],
            ],
            [
                'type' => 'address',
                'data' => [
                    'label' => 'Delivery address',
                    'parts' => ['line1', 'line2', 'city', 'postal_code'],
                    'required_parts' => ['line1', 'city', 'postal_code'],
                    'required' => true,
                ],
            ],
            [
                'type' => 'textarea',
                'data' => [
                    'label' => 'Special requests',
                    'rows' => 3,
                ],
            ],
        ],
    ],
];
