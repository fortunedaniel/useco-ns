<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/faq.php
// Rôle : gère les FAQ multiples depuis l'administration WordPress
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - crée le type de contenu FAQ
// - crée les Groupes de FAQ
// - permet d'associer un ou plusieurs groupes à chaque page
// - fournit les données nécessaires au composant FAQ front
//
// Ce qu'il ne fait pas :
// - ne dépend pas de Node.js ou Vite en production
// - ne contient pas de contenu client codé en dur sauf valeur de repli explicitement prévue
//
// Fichiers liés :
// - components/faq/faq.php
// - components/faq/faq.ts
// - resources/scss/components/_faq.scss
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Register faq : fonction du module faq.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_register_faq(): void
{
    if (!comkani_core_component_enabled('faq')) {
        return;
    }

    register_post_type('comkani_faq', [
        'labels' => [
            'name' => 'FAQ',
            'singular_name' => 'Question',
            'add_new_item' => 'Ajouter une question',
            'edit_item' => 'Modifier la question',
            'all_items' => 'Toutes les questions',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => false,
        'menu_icon' => 'dashicons-editor-help',
        'supports' => ['title', 'editor', 'page-attributes'],
        'capabilities' => [
            'edit_post' => 'comkani_manage_faq',
            'read_post' => 'read',
            'delete_post' => 'comkani_manage_faq',
            'edit_posts' => 'comkani_manage_faq',
            'edit_others_posts' => 'comkani_manage_faq',
            'publish_posts' => 'comkani_manage_faq',
            'read_private_posts' => 'comkani_manage_faq',
            'delete_posts' => 'comkani_manage_faq',
            'delete_private_posts' => 'comkani_manage_faq',
            'delete_published_posts' => 'comkani_manage_faq',
            'delete_others_posts' => 'comkani_manage_faq',
            'edit_private_posts' => 'comkani_manage_faq',
            'edit_published_posts' => 'comkani_manage_faq',
            'create_posts' => 'comkani_manage_faq',
        ],
        'map_meta_cap' => false,
    ]);

    register_taxonomy('comkani_faq_group', ['comkani_faq'], [
        'labels' => [
            'name' => 'Groupes de FAQ',
            'singular_name' => 'Groupe de FAQ',
        ],
        'public' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => false,
        'hierarchical' => true,
        'capabilities' => [
            'manage_terms' => 'comkani_manage_faq',
            'edit_terms' => 'comkani_manage_faq',
            'delete_terms' => 'comkani_manage_faq',
            'assign_terms' => 'comkani_manage_faq',
        ],
    ]);
}

add_action('init', 'comkani_register_faq', 10);

/**
 * Faq add page metabox : fonction du module faq.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_faq_add_page_metabox(): void
{
    if (!comkani_core_component_enabled('faq')) {
        return;
    }

    add_meta_box(
        'comkani-faq-groups',
        'FAQ affichée sur cette page',
        'comkani_faq_render_page_metabox',
        'page',
        'side',
        'default'
    );
}

add_action('add_meta_boxes', 'comkani_faq_add_page_metabox');

/**
 * Faq render page metabox : fonction du module faq.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_faq_render_page_metabox(WP_Post $post): void
{
    wp_nonce_field('comkani_save_page_faq_groups', 'comkani_faq_groups_nonce');

    $selected = array_map(
        'intval',
        (array) get_post_meta($post->ID, '_comkani_faq_groups', true)
    );
    $terms = get_terms([
        'taxonomy' => 'comkani_faq_group',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms) || $terms === []) {
        echo '<p>Créez d’abord un Groupe de FAQ.</p>';
        return;
    }

    foreach ($terms as $term) {
        printf(
            '<label style="display:block;margin:0 0 8px"><input type="checkbox" name="comkani_faq_groups[]" value="%1$d" %2$s> %3$s</label>',
            (int) $term->term_id,
            checked(in_array((int) $term->term_id, $selected, true), true, false),
            esc_html($term->name)
        );
    }
}

/**
 * Faq save page metabox : fonction du module faq.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_faq_save_page_metabox(int $post_id): void
{
    $nonce = isset($_POST['comkani_faq_groups_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['comkani_faq_groups_nonce']))
        : '';

    if ($nonce === '' || !wp_verify_nonce($nonce, 'comkani_save_page_faq_groups')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $groups = isset($_POST['comkani_faq_groups'])
        ? array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['comkani_faq_groups']))))
        : [];

    update_post_meta($post_id, '_comkani_faq_groups', $groups);
}

add_action('save_post_page', 'comkani_faq_save_page_metabox');

/**
 * Récupère les groupes FAQ depuis la configuration compilée
 *
 * @return array
 */
function comkani_faq_get_project_groups(): array
{
    $groups = comkani_core_get('faq.groups', []);

    return is_array($groups) ? $groups : [];
}

/**
 * Initialise les groupes et questions FAQ depuis la configuration du projet
 *
 * La synchronisation est non destructive :
 * - crée uniquement les groupes absents
 * - crée uniquement les questions absentes
 * - n'écrase jamais une réponse modifiée dans WordPress
 * - associe automatiquement les groupes aux pages configurées
 *
 * @return void
 */
function comkani_faq_seed_from_project(): void
{
    if (!comkani_core_component_enabled('faq')) {
        return;
    }

    $groups = comkani_faq_get_project_groups();

    if ($groups === []) {
        return;
    }

    foreach ($groups as $group) {
        if (!is_array($group)) {
            continue;
        }

        $key = sanitize_title((string) ($group['key'] ?? ''));
        $label = sanitize_text_field((string) ($group['label'] ?? ''));

        if ($key === '' || $label === '') {
            continue;
        }

        $term = get_term_by('slug', $key, 'comkani_faq_group');

        if (!$term instanceof WP_Term) {
            $created = wp_insert_term(
                $label,
                'comkani_faq_group',
                ['slug' => $key]
            );

            if (is_wp_error($created)) {
                continue;
            }

            $term_id = (int) $created['term_id'];
        } else {
            $term_id = (int) $term->term_id;
        }

        $items = is_array($group['items'] ?? null)
            ? $group['items']
            : [];

        foreach ($items as $position => $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = sanitize_text_field(
                (string) ($item['question'] ?? '')
            );
            $answer = wp_kses_post(
                (string) ($item['answer'] ?? '')
            );

            if ($question === '' || $answer === '') {
                continue;
            }

            $existing = get_posts([
                'post_type' => 'comkani_faq',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'title' => $question,
                'fields' => 'ids',
                'tax_query' => [[
                    'taxonomy' => 'comkani_faq_group',
                    'field' => 'term_id',
                    'terms' => [$term_id],
                ]],
            ]);

            if ($existing !== []) {
                continue;
            }

            $post_id = wp_insert_post([
                'post_type' => 'comkani_faq',
                'post_status' => 'publish',
                'post_title' => $question,
                'post_content' => $answer,
                'menu_order' => (int) $position,
            ]);

            if ($post_id > 0) {
                wp_set_object_terms(
                    $post_id,
                    [$term_id],
                    'comkani_faq_group',
                    false
                );
            }
        }

        $pages = is_array($group['pages'] ?? null)
            ? $group['pages']
            : [];

        foreach ($pages as $slug) {
            $page = get_page_by_path(
                sanitize_title((string) $slug),
                OBJECT,
                'page'
            );

            if (!$page instanceof WP_Post) {
                continue;
            }

            $selected = array_map(
                'intval',
                (array) get_post_meta(
                    $page->ID,
                    '_comkani_faq_groups',
                    true
                )
            );

            if (!in_array($term_id, $selected, true)) {
                $selected[] = $term_id;

                update_post_meta(
                    $page->ID,
                    '_comkani_faq_groups',
                    array_values(array_unique($selected))
                );
            }
        }
    }
}

add_action('init', 'comkani_faq_seed_from_project', 20);
/**
 * Faq get items for page : fonction du module faq.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_faq_get_items_for_page(int $page_id): array
{
    if (!comkani_core_component_enabled('faq')) {
        return [];
    }

    $groups = array_map(
        'intval',
        (array) get_post_meta($page_id, '_comkani_faq_groups', true)
    );

    if ($groups === []) {
        return [];
    }

    $query = new WP_Query([
        'post_type' => 'comkani_faq',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => [
            'menu_order' => 'ASC',
            'date' => 'ASC',
        ],
        'tax_query' => [[
            'taxonomy' => 'comkani_faq_group',
            'field' => 'term_id',
            'terms' => $groups,
        ]],
    ]);
    $items = [];

    foreach ($query->posts as $post) {
        $items[] = [
            'id' => (int) $post->ID,
            'question' => get_the_title($post),
            'answer' => apply_filters('the_content', $post->post_content),
        ];
    }

    return $items;
}
