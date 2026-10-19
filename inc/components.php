<?php

// =============================================================================
// Fichier : inc/components.php
// Rôle : charge uniquement les composants fonctionnels actifs du projet
// =============================================================================
defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/component-helpers.php';

$components = [
    'contact',
    'booking',
    'faq',
    'whatsapp',
];

foreach ($components as $component) {
    if (!comkani_component_enabled($component)) {
        continue;
    }

    $file = get_template_directory()
        . '/components/'
        . $component
        . '/'
        . $component
        . '.php';

    if (is_file($file)) {
        require_once $file;
    }
}
