<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/legal-pages.php
// Rôle : garantit la présence des pages légales obligatoires du projet
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - lit les pages légales activées dans la configuration compilée
// - crée les pages absentes avec leur contenu initial de projet
// - restaure une page légale passée à la corbeille
// - applique le template légal ComKani
// - associe Cookies & confidentialité à la politique de confidentialité WordPress
// - empêche les rôles non administrateurs de supprimer ces pages
//
// Ce qu'il ne fait pas :
// - ne remplace jamais un contenu déjà saisi par le client
// - ne modifie pas le titre ou le slug d'une page existante
// - ne bloque pas l'édition par le Gestionnaire du site
// Fichiers liés :
// - voir les imports, appels et chemins référencés directement dans ce fichier
//
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Legal page definitions : fonction du module legal-pages.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_legal_page_definitions(): array
{
    return [
        'mentions' => [
            'title' => 'Mentions légales',
            'slug' => 'mentions-legales',
        ],
        'privacyCookies' => [
            'title' => 'Cookies & confidentialité',
            'slug' => 'confidentialite-cookies',
        ],
        'cgv' => [
            'title' => 'Conditions générales de vente',
            'slug' => 'conditions-generales-de-vente',
        ],
        'cgu' => [
            'title' => 'Conditions générales d’utilisation',
            'slug' => 'conditions-generales-utilisation',
        ],
        'shippingReturns' => [
            'title' => 'Livraison et retours',
            'slug' => 'livraison-retours',
        ],
        'refund' => [
            'title' => 'Politique de remboursement',
            'slug' => 'politique-remboursement',
        ],
        'bookingTerms' => [
            'title' => 'Conditions de réservation',
            'slug' => 'conditions-reservation',
        ],
    ];
}

/**
 * Legal enabled definitions : fonction du module legal-pages.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_legal_enabled_definitions(): array
{
    $configured = (array) comkani_core_get('legalPages', []);
    $definitions = comkani_legal_page_definitions();
    $enabled = [];

    foreach ($configured as $key => $is_enabled) {
        if ($is_enabled === true && isset($definitions[$key])) {
            $enabled[$key] = $definitions[$key];
        }
    }

    return $enabled;
}

/**
 * Legal default content : fonction du module legal-pages.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_legal_default_content(string $key): string
{
    $contents = (array) comkani_core_get('legalContent', []);
    $content = isset($contents[$key]) ? (string) $contents[$key] : '';

    return wp_kses_post($content);
}

/**
 * Legal find page by slug : fonction du module legal-pages.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_legal_find_page_by_slug(string $slug): ?WP_Post
{
    $posts = get_posts([
        'post_type' => 'page',
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private', 'trash'],
        'name' => $slug,
        'posts_per_page' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    return isset($posts[0]) && $posts[0] instanceof WP_Post
        ? $posts[0]
        : null;
}

/**
 * Legal ensure pages : fonction du module legal-pages.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_legal_ensure_pages(): void
{
    foreach (comkani_legal_enabled_definitions() as $key => $definition) {
        $page = comkani_legal_find_page_by_slug((string) $definition['slug']);
        $created = false;

        if ($page && $page->post_status === 'trash') {
            wp_untrash_post($page->ID);
            $page = get_post($page->ID);
        }

        if (!$page instanceof WP_Post) {
            $page_id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => (string) $definition['title'],
                'post_name' => (string) $definition['slug'],
                'post_content' => comkani_legal_default_content((string) $key),
            ], true);

            if (is_wp_error($page_id) || !$page_id) {
                continue;
            }

            $page = get_post((int) $page_id);
            $created = true;
        }

        if (!$page instanceof WP_Post) {
            continue;
        }

        // Migration unique : le patch 1.4.2 a pu créer une page vide.
        // On injecte le contenu initial uniquement si la page est encore vide
        // et si les valeurs par défaut n'ont jamais été appliquées.
        if (
            !$created
            && trim((string) $page->post_content) === ''
            && !get_post_meta($page->ID, '_comkani_legal_defaults_applied', true)
        ) {
            wp_update_post([
                'ID' => $page->ID,
                'post_content' => comkani_legal_default_content((string) $key),
            ]);
        }

        update_post_meta($page->ID, '_wp_page_template', 'templates/page-legal.php');
        update_post_meta($page->ID, '_comkani_required_legal_page', sanitize_key((string) $key));
        update_post_meta($page->ID, '_comkani_legal_defaults_applied', '1');

        if ($key === 'privacyCookies') {
            update_option('wp_page_for_privacy_policy', $page->ID);
        }
    }
}

/**
 * Legal required page ids : fonction du module legal-pages.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_legal_required_page_ids(): array
{
    $ids = [];

    foreach (comkani_legal_enabled_definitions() as $definition) {
        $page = comkani_legal_find_page_by_slug((string) $definition['slug']);

        if ($page instanceof WP_Post && $page->post_status !== 'trash') {
            $ids[] = (int) $page->ID;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Legal filter page row actions : fonction du module legal-pages.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_legal_filter_page_row_actions(array $actions, WP_Post $post): array
{
    if (current_user_can('manage_options')) {
        return $actions;
    }

    if (in_array((int) $post->ID, comkani_legal_required_page_ids(), true)) {
        unset($actions['trash'], $actions['delete']);
    }

    return $actions;
}

/**
 * Legal protect deletion : fonction du module legal-pages.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_legal_protect_deletion(array $caps, string $cap, int $user_id, array $args): array
{
    if (!in_array($cap, ['delete_post', 'delete_page'], true)) {
        return $caps;
    }

    $post_id = isset($args[0]) ? (int) $args[0] : 0;

    if ($post_id <= 0 || !in_array($post_id, comkani_legal_required_page_ids(), true)) {
        return $caps;
    }

    $user = get_userdata($user_id);

    if ($user instanceof WP_User && in_array('administrator', (array) $user->roles, true)) {
        return $caps;
    }

    return ['do_not_allow'];
}

add_action('admin_init', 'comkani_legal_ensure_pages', 7);
add_filter('page_row_actions', 'comkani_legal_filter_page_row_actions', 100, 2);
add_filter('map_meta_cap', 'comkani_legal_protect_deletion', 100, 4);
