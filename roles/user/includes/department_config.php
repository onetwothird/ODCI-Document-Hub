<?php
// Department configuration and mapping
// This file contains all department-related configurations

// Department configuration
$departments = [
    'TED' => [
        'name' => 'Teacher Education Department',
        'icon' => 'bxs-graduation',
        'color' => '#f59e0b' // Warm yellow-orange (symbolizes learning/education)
    ],
    'MD' => [
        'name' => 'Management Department', 
        'icon' => 'bxs-business',
        'color' => '#1e40af' // Deep blue (professional, stable)
    ],
    'FASD' => [
        'name' => 'Fisheries and Aquatic Science Department',
        'icon' => 'bx bx-water',
        'color' => '#0284c7' // Ocean blue (marine/aquatic)
    ],
    'ASD' => [
        'name' => 'Arts and Science Department',
        'icon' => 'bxs-palette', // More artistic emphasis
        'color' => '#d946ef' // Vibrant purple-pink (creativity and diversity)
    ],
    'ITD' => [
        'name' => 'Information Technology Department',
        'icon' => 'bxs-chip',
        'color' => '#0f766e' // Techy teal (modern and digital)
    ],
    'NSTP' => [
        'name' => 'National Service Training Program',
        'icon' => 'bxs-user-check', // More appropriate than a chip
        'color' => '#22c55e' // Green (growth, civic responsibility)
    ],
    'Other Files' => [
        'name' => 'Others',
        'icon' => 'bxs-file',
        'color' => '#6b7280' // Neutral gray (generic/unspecified)
    ]
];

// Map department IDs to department codes
$departmentMap = [
    1 => 'TED',
    2 => 'MD',
    3 => 'ITD',
    4 => 'FASD',
    5 => 'ASD',
    6 => 'NSTP',
    7 => 'Other Files'
];
?>