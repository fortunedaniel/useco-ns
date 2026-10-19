<?php

// =============================================================================
// Fichier : functions.php
// Rôle : initialise le thème WordPress ComKani Starter
//
// Objectif :
// - initialiser ou relier une responsabilité technique du thème WordPress
// - conserver une architecture compatible avec un hébergement WordPress classique
//
// Ce qu'il fait :
// - charge la configuration du projet
// - charge le ComKani Core en secours lorsqu'il n'est pas installé en MU-plugin
// - charge la configuration du thème et les composants front
// - charge les assets compilés par Vite
//
// Ce qu'il ne fait pas :
// - ne rend pas Node.js nécessaire au fonctionnement du thème en production
// - ne doit pas contenir de logique éditoriale spécifique à une seule page lorsqu’un composant suffit
//
// Fichiers liés :
// - inc/project.php
// - inc/theme.php
// - inc/assets.php
// - inc/components.php
// - packages/comkani-core/comkani-core.php
// =============================================================================
defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/project.php';

if (!defined('COMKANI_CORE_LOADED')) {
    require_once get_template_directory() . '/packages/comkani-core/comkani-core.php';
}

require_once get_template_directory() . '/inc/theme.php';
require_once get_template_directory() . '/inc/assets.php';
require_once get_template_directory() . '/inc/components.php';
require_once get_template_directory() . '/inc/uploads.php';

// Rechargement automatique exclusivement dans WordPress Local.
require_once get_template_directory() . '/inc/dev-live-reload.php';


/**
 * Réserve Gutenberg aux articles WordPress.
 *
 * Les pages et les types de contenus personnalisés utilisent
 * prioritairement les interfaces d'administration ComKani.
 *
 * @param bool   $use_block_editor État initial de Gutenberg.
 * @param string $post_type Type de contenu WordPress.
 * @return bool Autorisation de Gutenberg.
 */
function comkani_limit_block_editor_to_posts(
    bool $use_block_editor,
    string $post_type
): bool {
    return $post_type === 'post' ? $use_block_editor : false;
}

add_filter(
    'use_block_editor_for_post_type',
    'comkani_limit_block_editor_to_posts',
    100,
    2
);

