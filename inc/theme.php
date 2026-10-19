<?php

// =============================================================================
// Fichier : inc/theme.php
// Rôle : centralise la configuration native du thème WordPress
//
// Objectif :
// - initialiser ou relier une responsabilité technique du thème WordPress
// - conserver une architecture compatible avec un hébergement WordPress classique
//
// Ce qu'il fait :
// - active les fonctionnalités WordPress nécessaires
// - déclare les emplacements de menus
// - génère les emplacements des groupes de footer depuis project.json
// - configure les tailles d'images du Starter
// - associe les templates spécifiques aux pages du projet
//
// Ce qu'il ne fait pas :
// - ne rend pas Node.js nécessaire au fonctionnement du thème en production
// - ne doit pas contenir de logique éditoriale spécifique à une seule page lorsqu’un composant suffit
//
// Fichiers liés :
// - header.php
// - footer.php
// - config/src/project.ts
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Theme setup : fonction du module theme.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_theme_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');

    add_theme_support('custom-logo', [
        'height' => 80,
        'width' => 320,
        'flex-height' => true,
        'flex-width' => true,
    ]);

    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    // Déclare les emplacements de menus du projet
    $menu_locations = [
        'comkani_menu_primary' => 'Navigation principale',
        'comkani_menu_legal' => 'Informations légales',
    ];

    $footer_groups = function_exists('comkani_project_get')
        ? (array) comkani_project_get('menus.footer', [])
        : [];

    $grouped_footer = $footer_groups !== []
        && array_keys($footer_groups) !== range(
            0,
            count($footer_groups) - 1
        );

    if ($grouped_footer) {
        foreach ($footer_groups as $key => $group) {
            $key = sanitize_key((string) $key);
            $group = (array) $group;

            if ($key === '') {
                continue;
            }

            $label = trim(
                (string) ($group['label'] ?? $key)
            );

            $menu_locations[
                'comkani_menu_footer_' . $key
            ] = 'Pied de page - ' . $label;
        }
    }

    register_nav_menus($menu_locations);

    // Déclare les tailles d’images du thème
    add_image_size(
        'comkani-card',
        960,
        640,
        true
    );
}

add_action(
    'after_setup_theme',
    'comkani_theme_setup'
);

// Associe les templates spécifiques aux pages du projet
/**
 * Page template : fonction du module theme.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_template(string $template): string
{
    if (!is_page() || is_front_page()) {
        return $template;
    }

    $page = get_queried_object();

    if (!$page instanceof WP_Post) {
        return $template;
    }

    $slug = sanitize_file_name(
        (string) $page->post_name
    );

    if ($slug === '') {
        return $template;
    }

    $project_template = get_template_directory()
        . '/pages/'
        . $slug
        . '.php';

    if (!file_exists($project_template)) {
        return $template;
    }

    return $project_template;
}

add_filter(
    'template_include',
    'comkani_page_template',
    99
);
