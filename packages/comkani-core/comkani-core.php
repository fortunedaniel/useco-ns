<?php

// =============================================================================
// Fichier : packages/comkani-core/comkani-core.php
// Rôle : initialise le socle fonctionnel ComKani indépendant du thème
//
// Ce qu'il fait :
// - charge les permissions, le dashboard, la sécurité et les modules métier
// - charge le cadre d’édition des pages et les blocs métier ComKani
// - fonctionne depuis le thème en développement ou comme MU-plugin en production
// - installe les tables et capacités nécessaires sans plugin tiers
// - expose une version unique du ComKani Core
//
// Fichiers liés :
// - packages/comkani-core/includes/project.php
// - packages/comkani-core/includes/capabilities.php
// - packages/comkani-core/includes/editor.php
// - packages/comkani-core/includes/requests.php
// - packages/comkani-core/includes/faq.php
// - packages/comkani-core/includes/security.php
// - packages/comkani-core/includes/cache.php
// - packages/comkani-core/includes/admin.php
// =============================================================================
defined('ABSPATH') || exit;

if (defined('COMKANI_CORE_LOADED')) {
    return;
}

define('COMKANI_CORE_LOADED', true);
define('COMKANI_CORE_VERSION', '1.9.0');
define('COMKANI_CORE_DIR', __DIR__);

/**
 * Construit l'URL publique d'une ressource du Core, charge comme MU-plugin ou depuis le theme.
 *
 * @param string $relative_path Chemin relatif optionnel.
 * @return string URL complete.
 */
function comkani_core_url(string $relative_path = ''): string
{
    $relative_path = ltrim($relative_path, '/');
    $core_dir = wp_normalize_path(COMKANI_CORE_DIR);
    $mu_dir = defined('WPMU_PLUGIN_DIR')
        ? wp_normalize_path(WPMU_PLUGIN_DIR)
        : '';

    if ($mu_dir !== '' && strpos($core_dir, $mu_dir) === 0) {
        $relative_core = ltrim(substr($core_dir, strlen($mu_dir)), '/');
        $base = trailingslashit(WPMU_PLUGIN_URL) . $relative_core;
    } else {
        $base = trailingslashit(get_template_directory_uri()) . 'packages/comkani-core';
    }

    return $relative_path === ''
        ? $base
        : trailingslashit($base) . $relative_path;
}

/**
 * Utilise la date de modification d'une ressource pour invalider le cache navigateur.
 *
 * @param string $relative_path Chemin relatif de la ressource.
 * @return string Version utilisable dans wp_enqueue_script ou wp_enqueue_style.
 */
function comkani_core_asset_version(string $relative_path): string
{
    $absolute_path = COMKANI_CORE_DIR . '/' . ltrim($relative_path, '/');

    if (!file_exists($absolute_path)) {
        return COMKANI_CORE_VERSION;
    }

    return (string) filemtime($absolute_path);
}

$comkani_core_files = [
    'includes/project.php',
    'includes/capabilities.php',
    'includes/requests.php',
    'includes/request-settings.php',
    'includes/requests-privacy.php',
    'includes/faq.php',
    'includes/shared-content.php',
    'includes/post-types.php',
    'includes/editor.php',
    'includes/security.php',
    'includes/cache.php',
    'includes/database.php',
    'includes/seo.php',
    'includes/integrations.php',
    'includes/consent.php',
    'includes/admin.php',
    'includes/profile.php',
    'includes/legal-pages.php',
    'includes/activity-log.php',
    'includes/setup.php',
    'includes/media.php',
    'includes/duplicate.php',
    'includes/smtp.php',
    'includes/notification-bar.php',
];

foreach ($comkani_core_files as $comkani_core_file) {
    require_once COMKANI_CORE_DIR . '/' . $comkani_core_file;
}

unset($comkani_core_files, $comkani_core_file);
