<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Record Wizard',
    'description' => 'Brings the guided step-by-step creation wizard — known from the TYPO3 page creation dialog — to arbitrary records in the backend list module.',
    'category' => 'backend',
    'author' => 'Georg Ringer',
    'author_email' => 'mail@ringer.it',
    'state' => 'experimental',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
        ],
    ],
];
