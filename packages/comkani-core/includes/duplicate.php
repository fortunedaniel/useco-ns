<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/duplicate.php
// Rôle : permet de dupliquer les contenus autorisés par l'agence
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - lit les types de contenus autorisés dans config/src/features/duplication.ts
// - ajoute l'action "Dupliquer" dans les listes WordPress concernées
// - crée systématiquement la copie en brouillon
// - copie le contenu, les taxonomies et les métadonnées utiles
// - conserve notamment les champs de l'éditeur ComKani et les données SEO
// - redirige vers l'écran d'édition de la nouvelle copie
//
// Ce qu'il ne fait pas :
// - ne publie jamais automatiquement une copie
// - ne permet pas au client d'activer de nouveaux types duplicables
// - ne copie pas les verrous, révisions ou métadonnées temporaires WordPress
// Fichiers liés :
// - voir les imports, appels et chemins référencés directement dans ce fichier
//
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Retourne les types de contenus dont la duplication est activée par l'agence.
 *
 * Configuration attendue :
 * duplication.enabled = true
 * duplication.postTypes.page = true
 * duplication.postTypes.post = true
 * duplication.postTypes.comkani_faq = true
 */
function comkani_duplicate_enabled_post_types(): array
{
    if (!(bool) comkani_core_get('duplication.enabled', false)) {
        return [];
    }

    $configured = comkani_core_get('duplication.postTypes', []);

    if (!is_array($configured)) {
        return [];
    }

    $enabled = [];

    foreach ($configured as $post_type => $active) {
        $post_type = sanitize_key((string) $post_type);

        if ($post_type !== '' && (bool) $active) {
            $enabled[] = $post_type;
        }
    }

    return array_values(array_unique($enabled));
}

/**
 * Duplicate is enabled : fonction du module duplicate.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_duplicate_is_enabled(string $post_type): bool
{
    return in_array(
        sanitize_key($post_type),
        comkani_duplicate_enabled_post_types(),
        true
    );
}

/**
 * Vérifie que l'utilisateur a réellement le droit de créer une copie.
 */
function comkani_duplicate_user_can(WP_Post $post): bool
{
    if (!comkani_duplicate_is_enabled($post->post_type)) {
        return false;
    }

    if (!current_user_can('edit_post', $post->ID)) {
        return false;
    }

    $object = get_post_type_object($post->post_type);

    if (!$object instanceof WP_Post_Type) {
        return false;
    }

    $create_cap = $object->cap->create_posts
        ?? $object->cap->edit_posts
        ?? 'edit_posts';

    return current_user_can($create_cap);
}

/**
 * Ajoute l'action Dupliquer dans la liste des contenus.
 */
function comkani_duplicate_row_action(array $actions, WP_Post $post): array
{
    if (!comkani_duplicate_user_can($post)) {
        return $actions;
    }

    $url = wp_nonce_url(
        admin_url(
            'admin.php?action=comkani_duplicate_post&post=' . $post->ID
        ),
        'comkani_duplicate_post_' . $post->ID
    );

    $duplicate = [
        'comkani_duplicate' => sprintf(
            '<a href="%1$s" aria-label="%2$s">Dupliquer</a>',
            esc_url($url),
            esc_attr(
                sprintf(
                    'Dupliquer « %s »',
                    get_the_title($post)
                )
            )
        ),
    ];

    // Place "Dupliquer" juste après "Modifier" quand cette action existe.
    $result = [];

    foreach ($actions as $key => $action) {
        $result[$key] = $action;

        if ($key === 'edit') {
            $result += $duplicate;
            $duplicate = [];
        }
    }

    return $result + $duplicate;
}

add_filter('post_row_actions', 'comkani_duplicate_row_action', 20, 2);
add_filter('page_row_actions', 'comkani_duplicate_row_action', 20, 2);

/**
 * Métadonnées internes ou temporaires à ne jamais recopier.
 */
function comkani_duplicate_excluded_meta_keys(): array
{
    $keys = [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_wp_desired_post_slug',
        '_wp_trash_meta_status',
        '_wp_trash_meta_time',
        '_wp_trash_meta_comments_status',
    ];

    return apply_filters('comkani_duplicate_excluded_meta_keys', $keys);
}

/**
 * Duplicate should skip meta : fonction du module duplicate.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_duplicate_should_skip_meta(string $meta_key): bool
{
    if (in_array($meta_key, comkani_duplicate_excluded_meta_keys(), true)) {
        return true;
    }

    // Ne jamais recopier les données internes du journal / des révisions ComKani.
    if (str_starts_with($meta_key, '_comkani_activity_')) {
        return true;
    }

    if (str_starts_with($meta_key, '_comkani_revision_')) {
        return true;
    }

    return false;
}

/**
 * Copie les métadonnées, y compris les champs de l'éditeur ComKani,
 * l'image mise en avant, le template et les métadonnées SEO.
 */
function comkani_duplicate_copy_meta(int $source_id, int $target_id): void
{
    $all_meta = get_post_meta($source_id);

    foreach ($all_meta as $meta_key => $values) {
        if (comkani_duplicate_should_skip_meta((string) $meta_key)) {
            continue;
        }

        delete_post_meta($target_id, (string) $meta_key);

        foreach ((array) $values as $value) {
            add_post_meta(
                $target_id,
                (string) $meta_key,
                maybe_unserialize($value)
            );
        }
    }
}

/**
 * Copie toutes les taxonomies associées au type de contenu.
 */
function comkani_duplicate_copy_taxonomies(WP_Post $source, int $target_id): void
{
    $taxonomies = get_object_taxonomies($source->post_type, 'names');

    foreach ($taxonomies as $taxonomy) {
        $term_ids = wp_get_object_terms(
            $source->ID,
            $taxonomy,
            ['fields' => 'ids']
        );

        if (is_wp_error($term_ids)) {
            continue;
        }

        wp_set_object_terms(
            $target_id,
            array_map('intval', $term_ids),
            $taxonomy,
            false
        );
    }
}

/**
 * Crée la copie en brouillon puis ouvre directement son écran d'édition.
 */
function comkani_duplicate_handle(): void
{
    $post_id = isset($_GET['post'])
        ? absint($_GET['post'])
        : 0;

    if ($post_id <= 0) {
        wp_die('Contenu à dupliquer introuvable.');
    }

    check_admin_referer('comkani_duplicate_post_' . $post_id);

    $source = get_post($post_id);

    if (!$source instanceof WP_Post) {
        wp_die('Contenu à dupliquer introuvable.');
    }

    if (!comkani_duplicate_user_can($source)) {
        wp_die(
            'Vous n’avez pas l’autorisation de dupliquer ce contenu.',
            'Duplication non autorisée',
            ['response' => 403]
        );
    }

    $title = trim((string) $source->post_title);
    $copy_title = $title !== ''
        ? $title . ' - copie'
        : 'Copie';

    $new_post_id = wp_insert_post([
        'post_type' => $source->post_type,
        'post_status' => 'draft',
        'post_title' => $copy_title,
        'post_content' => $source->post_content,
        'post_excerpt' => $source->post_excerpt,
        'post_author' => get_current_user_id(),
        'post_parent' => (int) $source->post_parent,
        'menu_order' => (int) $source->menu_order,
        'comment_status' => $source->comment_status,
        'ping_status' => $source->ping_status,
        'post_password' => $source->post_password,
    ], true);

    if (is_wp_error($new_post_id)) {
        wp_die(
            esc_html($new_post_id->get_error_message()),
            'Impossible de dupliquer le contenu'
        );
    }

    $new_post_id = (int) $new_post_id;

    comkani_duplicate_copy_meta($source->ID, $new_post_id);
    comkani_duplicate_copy_taxonomies($source, $new_post_id);

    /**
     * Permet à un projet ou à un futur module d'ajouter une copie spécifique.
     */
    do_action(
        'comkani_after_duplicate_post',
        $new_post_id,
        $source->ID,
        $source->post_type
    );

    $edit_url = get_edit_post_link($new_post_id, 'raw');

    if (!$edit_url) {
        $edit_url = admin_url('edit.php');
    }

    $edit_url = add_query_arg(
        'comkani_duplicated',
        '1',
        $edit_url
    );

    wp_safe_redirect($edit_url);
    exit;
}

add_action('admin_action_comkani_duplicate_post', 'comkani_duplicate_handle');

/**
 * Duplicate admin notice : fonction du module duplicate.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_duplicate_admin_notice(): void
{
    if (!isset($_GET['comkani_duplicated'])) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>Copie créée en brouillon. Vérifiez son contenu avant publication.</p></div>';
}

add_action('admin_notices', 'comkani_duplicate_admin_notice');
