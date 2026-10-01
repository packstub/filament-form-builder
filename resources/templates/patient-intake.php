<?php

/**
 * Template: Patient intake.
 */
return [
    'name' => 'Patient intake',
    'category' => 'Healthcare',
    'description' => 'Contact, insurance and consent before a visit.',
    'form' => [
        'name' => 'Patient intake',
        'settings' => [
            'mode' => 'wizard',
        ],
        'fields' => [
            [
                'type' => 'section',
                'data' => [
                    'label' => 'Patient',
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
                            'type' => 'date',
                            'data' => [
                                'label' => 'Date of birth',
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
                            'type' => 'email',
                            'data' => [
                                'label' => 'Email',
                                'width' => 'half',
                            ],
                        ],
                        [
                            'type' => 'address',
                            'data' => [
                                'label' => 'Address',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'section',
                'data' => [
                    'label' => 'Insurance',
                    'fields' => [
                        [
                            'type' => 'toggle',
                            'data' => [
                                'label' => 'I have insurance',
                                'key' => 'insured',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'data' => [
                                'label' => 'Insurance provider',
                                'visibility' => 'when',
                                'visibility_rules' => [
                                    [
                                        'field' => 'insured',
                                        'operator' => 'equals',
                                        'value' => 'true',
                                    ],
                                ],
                                'width' => 'half',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'data' => [
                                'label' => 'Policy number',
                                'visibility' => 'when',
                                'visibility_rules' => [
                                    [
                                        'field' => 'insured',
                                        'operator' => 'equals',
                                        'value' => 'true',
                                    ],
                                ],
                                'width' => 'half',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'section',
                'data' => [
                    'label' => 'Medical',
                    'fields' => [
                        [
                            'type' => 'textarea',
                            'data' => [
                                'label' => 'Current medications',
                                'rows' => 2,
                            ],
                        ],
                        [
                            'type' => 'textarea',
                            'data' => [
                                'label' => 'Allergies',
                                'rows' => 2,
                            ],
                        ],
                        [
                            'type' => 'textarea',
                            'data' => [
                                'label' => 'Reason for the visit',
                                'rows' => 3,
                                'required' => true,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'section',
                'data' => [
                    'label' => 'Consent',
                    'fields' => [
                        [
                            'type' => 'consent',
                            'data' => [
                                'label' => 'I consent to treatment and to the',
                                'link_text' => 'privacy practices',
                                'link_url' => '/privacy',
                                'required' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
