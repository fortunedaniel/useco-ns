<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/shared-content.php
// Rôle : centraliser les contenus réutilisés sur plusieurs pages
//
// Objectif :
// - éviter les doubles saisies dans l’éditeur WordPress
// - permettre au client de créer un contenu une seule fois puis de l’afficher
//   sur plusieurs pages
// - conserver les titres et textes d’introduction propres à chaque page
//
// Ce qu’il fait :
// - crée les contenus partagés Clients et Étapes
// - ajoute leur édition sous le menu Pages
// - permet de sélectionner les éléments affichés sur chaque page concernée
// - fournit les données prêtes à afficher aux templates du thème
// - migre les anciens repeaters page par page sans supprimer leurs données
//
// Ce qu’il ne fait pas :
// - ne partage pas automatiquement les titres et textes de section
// - ne modifie pas les contenus de la page Accueil liés au logo-rotator
// - ne supprime pas les anciennes métadonnées après migration
// - ne rend aucun contenu partagé public en dehors des pages du site
//
// Fichiers liés :
// - config/shared-content.json
// - config/pages/accueil.json
// - config/pages/services.json
// - config/pages/secteurs.json
// - config/pages/yellow-world.json
// - components/client-logos/client-logos.php
// - components/step/step.php
// =============================================================================

defined('ABSPATH') || exit;

/**
 * Indique si un type de contenu partagé est activé.
 *
 * @param string $type Type logique : clients ou steps.
 * @return bool
 */
function comkani_shared_enabled(string $type): bool
{
    return (bool) comkani_core_get(
        'sharedContent.' . sanitize_key($type) . '.enabled',
        false
    );
}

/**
 * Retourne les slugs de pages autorisées pour un type partagé.
 *
 * @param string $type Type logique : clients ou steps.
 * @return array<int, string>
 */
function comkani_shared_pages(string $type): array
{
    $pages = comkani_core_get(
        'sharedContent.' . sanitize_key($type) . '.pages',
        []
    );

    if (!is_array($pages)) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn($slug): string => sanitize_title((string) $slug),
        $pages
    )));
}

/**
 * Enregistre les contenus Clients et Étapes dans l’administration.
 *
 * @return void
 */
function comkani_shared_register_post_types(): void
{
    if (comkani_shared_enabled('clients')) {
        register_post_type('comkani_client', [
            'labels' => [
                'name' => 'Clients',
                'singular_name' => 'Client',
                'add_new' => 'Ajouter',
                'add_new_item' => 'Ajouter un client',
                'edit_item' => 'Modifier le client',
                'new_item' => 'Nouveau client',
                'all_items' => 'Clients',
                'featured_image' => 'Logo',
                'set_featured_image' => 'Choisir le logo',
                'remove_featured_image' => 'Retirer le logo',
                'use_featured_image' => 'Utiliser comme logo',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=page',
            'show_in_rest' => false,
            'supports' => ['title', 'thumbnail', 'page-attributes'],
            'capabilities' => comkani_shared_post_capabilities(),
            'map_meta_cap' => false,
        ]);
    }

    if (comkani_shared_enabled('steps')) {
        register_post_type('comkani_step', [
            'labels' => [
                'name' => 'Étapes',
                'singular_name' => 'Étape',
                'add_new' => 'Ajouter',
                'add_new_item' => 'Ajouter une étape',
                'edit_item' => 'Modifier l’étape',
                'new_item' => 'Nouvelle étape',
                'all_items' => 'Étapes',
                'featured_image' => 'Image',
                'set_featured_image' => 'Choisir l’image',
                'remove_featured_image' => 'Retirer l’image',
                'use_featured_image' => 'Utiliser cette image',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=page',
            'show_in_rest' => false,
            'supports' => ['title', 'thumbnail', 'page-attributes'],
            'capabilities' => comkani_shared_post_capabilities(),
            'map_meta_cap' => false,
        ]);
    }
}

add_action('init', 'comkani_shared_register_post_types', 10);

/**
 * Retourne les capacités communes aux contenus partagés.
 *
 * @return array<string, string>
 */
function comkani_shared_post_capabilities(): array
{
    return [
        'edit_post' => 'comkani_manage_shared_content',
        'read_post' => 'read',
        'delete_post' => 'comkani_manage_shared_content',
        'edit_posts' => 'comkani_manage_shared_content',
        'edit_others_posts' => 'comkani_manage_shared_content',
        'publish_posts' => 'comkani_manage_shared_content',
        'read_private_posts' => 'comkani_manage_shared_content',
        'delete_posts' => 'comkani_manage_shared_content',
        'delete_private_posts' => 'comkani_manage_shared_content',
        'delete_published_posts' => 'comkani_manage_shared_content',
        'delete_others_posts' => 'comkani_manage_shared_content',
        'edit_private_posts' => 'comkani_manage_shared_content',
        'edit_published_posts' => 'comkani_manage_shared_content',
        'create_posts' => 'comkani_manage_shared_content',
    ];
}

/**
 * Ajoute les champs spécifiques aux fiches Client et Étape.
 *
 * @return void
 */
function comkani_shared_add_item_meta_boxes(): void
{
    if (comkani_shared_enabled('clients')) {
        add_meta_box(
            'comkani-client-details',
            'Informations du client',
            'comkani_shared_render_client_meta_box',
            'comkani_client',
            'normal',
            'high'
        );
    }

    if (comkani_shared_enabled('steps')) {
        add_meta_box(
            'comkani-step-details',
            'Contenu de l’étape',
            'comkani_shared_render_step_meta_box',
            'comkani_step',
            'normal',
            'high'
        );
    }
}

add_action('add_meta_boxes', 'comkani_shared_add_item_meta_boxes');

/**
 * Affiche le champ URL d’une fiche Client.
 *
 * @param WP_Post $post Client en cours d’édition.
 * @return void
 */
function comkani_shared_render_client_meta_box(WP_Post $post): void
{
    wp_nonce_field('comkani_save_client_' . $post->ID, 'comkani_client_nonce');

    $url = (string) get_post_meta(
        $post->ID,
        '_comkani_client_url',
        true
    );

    echo '<p><label for="comkani-client-url"><strong>Site internet</strong></label></p>';
    echo '<input class="widefat" type="url" id="comkani-client-url" name="comkani_client_url" value="' . esc_attr($url) . '" placeholder="https://exemple.com">';
    echo '<p class="description">Optionnel. Le logo devient cliquable lorsqu’une URL est renseignée.</p>';
}

/**
 * Affiche le texte d’une fiche Étape.
 *
 * @param WP_Post $post Étape en cours d’édition.
 * @return void
 */
function comkani_shared_render_step_meta_box(WP_Post $post): void
{
    wp_nonce_field('comkani_save_step_' . $post->ID, 'comkani_step_nonce');

    $text = (string) get_post_meta(
        $post->ID,
        '_comkani_step_text',
        true
    );

    echo '<p><label for="comkani-step-text"><strong>Texte</strong></label></p>';
    echo '<textarea class="widefat" id="comkani-step-text" name="comkani_step_text" rows="6">' . esc_textarea($text) . '</textarea>';
}

/**
 * Enregistre les métadonnées d’un Client ou d’une Étape.
 *
 * @param int $post_id Identifiant WordPress du contenu.
 * @return void
 */
function comkani_shared_save_item_meta(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('comkani_manage_shared_content')) {
        return;
    }

    $post_type = get_post_type($post_id);

    if ($post_type === 'comkani_client') {
        $nonce = isset($_POST['comkani_client_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['comkani_client_nonce']))
            : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, 'comkani_save_client_' . $post_id)) {
            return;
        }

        $url = isset($_POST['comkani_client_url'])
            ? esc_url_raw((string) wp_unslash($_POST['comkani_client_url']))
            : '';

        update_post_meta($post_id, '_comkani_client_url', $url);
    }

    if ($post_type === 'comkani_step') {
        $nonce = isset($_POST['comkani_step_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['comkani_step_nonce']))
            : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, 'comkani_save_step_' . $post_id)) {
            return;
        }

        $text = isset($_POST['comkani_step_text'])
            ? sanitize_textarea_field((string) wp_unslash($_POST['comkani_step_text']))
            : '';

        update_post_meta($post_id, '_comkani_step_text', $text);
    }
}

add_action('save_post', 'comkani_shared_save_item_meta', 20);

/**
 * Désactive Gutenberg pour les deux contenus administrés par ComKani.
 *
 * @param bool    $use_block_editor État courant de Gutenberg.
 * @param WP_Post $post Contenu concerné.
 * @return bool
 */
function comkani_shared_disable_block_editor(bool $use_block_editor, WP_Post $post): bool
{
    if (in_array($post->post_type, ['comkani_client', 'comkani_step'], true)) {
        return false;
    }

    return $use_block_editor;
}

add_filter('use_block_editor_for_post', 'comkani_shared_disable_block_editor', 110, 2);

/**
 * Retourne les collections partagées disponibles sur une page.
 *
 * @param string $page_slug Slug WordPress de la page.
 * @return array<int, string>
 */
function comkani_shared_page_type(string $page_slug): array
{
    $types = [];

    foreach (['clients', 'steps'] as $type) {
        if (
            comkani_shared_enabled($type)
            && in_array($page_slug, comkani_shared_pages($type), true)
        ) {
            $types[] = $type;
        }
    }

    return $types;
}

/**
 * Ajoute les sélecteurs Clients / Étapes aux pages concernées.
 *
 * @return void
 */
/**
 * Exécute la fonction shared add page meta boxes.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkani_shared_add_page_meta_boxes(): void
{
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

    if ($post_id < 1) {
        return;
    }

    $page_slug = comkani_page_editor_post_slug($post_id);

    foreach (comkani_shared_page_type($page_slug) as $type) {
        $label = $type === 'clients'
            ? 'Clients affichés sur cette page'
            : 'Étapes affichées sur cette page';

        add_meta_box(
            'comkani-shared-' . $type,
            $label,
            'comkani_shared_render_page_meta_box',
            'page',
            'side',
            'default',
            ['type' => $type]
        );
    }
}

add_action('add_meta_boxes_page', 'comkani_shared_add_page_meta_boxes', 80);

/**
 * Affiche la liste des contenus partageables sur une page.
 *
 * @param WP_Post              $post Page WordPress.
 * @param array<string, mixed> $box Configuration de la metabox.
 * @return void
 */
function comkani_shared_render_page_meta_box(WP_Post $post, array $box): void
{
    $type = sanitize_key((string) (($box['args']['type'] ?? '')));
    $post_type = $type === 'clients' ? 'comkani_client' : 'comkani_step';
    $meta_key = '_comkani_shared_' . $type;
    $selected = array_map(
        'absint',
        (array) get_post_meta($post->ID, $meta_key, true)
    );

    wp_nonce_field(
        'comkani_save_shared_' . $type . '_' . $post->ID,
        'comkani_shared_' . $type . '_nonce'
    );

    $items = get_posts([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => [
            'menu_order' => 'ASC',
            'title' => 'ASC',
        ],
    ]);

    if ($items === []) {
        echo '<p>Aucun élément disponible.</p>';
        return;
    }

    foreach ($items as $item) {
        printf(
            '<label style="display:block;margin:0 0 8px"><input type="checkbox" name="comkani_shared_%1$s[]" value="%2$d" %3$s> %4$s</label>',
            esc_attr($type),
            (int) $item->ID,
            checked(in_array((int) $item->ID, $selected, true), true, false),
            esc_html($item->post_title)
        );
    }
}

/**
 * Enregistre les éléments partagés sélectionnés pour une page.
 *
 * @param int $post_id Identifiant de la page.
 * @return void
 */
function comkani_shared_save_page_meta(int $post_id): void
{
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $page_slug = comkani_page_editor_post_slug($post_id);

    foreach (comkani_shared_page_type($page_slug) as $type) {
        $nonce_key = 'comkani_shared_' . $type . '_nonce';
        $nonce = isset($_POST[$nonce_key])
            ? sanitize_text_field(wp_unslash($_POST[$nonce_key]))
            : '';

        if (
            $nonce === ''
            || !wp_verify_nonce(
                $nonce,
                'comkani_save_shared_' . $type . '_' . $post_id
            )
        ) {
            continue;
        }

        $field = 'comkani_shared_' . $type;
        $ids = isset($_POST[$field])
            ? array_values(array_unique(array_filter(array_map(
                'absint',
                (array) wp_unslash($_POST[$field])
            ))))
            : [];

        update_post_meta(
            $post_id,
            '_comkani_shared_' . $type,
            $ids
        );
    }
}

add_action('save_post_page', 'comkani_shared_save_page_meta', 30);

/**
 * Retourne les contenus partagés sélectionnés pour une page.
 *
 * @param int    $page_id Identifiant de la page.
 * @param string $type Type logique : clients ou steps.
 * @return array<int, WP_Post>
 */
function comkani_shared_selected_posts(int $page_id, string $type): array
{
    $post_type = $type === 'clients' ? 'comkani_client' : 'comkani_step';
    $ids = array_values(array_filter(array_map(
        'absint',
        (array) get_post_meta(
            $page_id,
            '_comkani_shared_' . $type,
            true
        )
    )));

    if ($ids === []) {
        return [];
    }

    $posts = get_posts([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'post__in' => $ids,
        'orderby' => [
            'menu_order' => 'ASC',
            'title' => 'ASC',
        ],
    ]);

    return is_array($posts) ? $posts : [];
}

/**
 * Prépare les Clients sélectionnés au format attendu par le composant logos.
 *
 * @param int $page_id Identifiant de la page.
 * @return array<int, array<string, mixed>>
 */
function comkani_shared_clients_for_page(int $page_id): array
{
    $items = [];

    foreach (comkani_shared_selected_posts($page_id, 'clients') as $post) {
        $items[] = [
            'name' => (string) $post->post_title,
            'url' => (string) get_post_meta(
                $post->ID,
                '_comkani_client_url',
                true
            ),
            'image' => (int) get_post_thumbnail_id($post->ID),
        ];
    }

    return $items;
}

/**
 * Prépare les Étapes sélectionnées au format attendu par le composant step.
 *
 * @param int $page_id Identifiant de la page.
 * @return array<int, array<string, mixed>>
 */
function comkani_shared_steps_for_page(int $page_id): array
{
    $items = [];

    foreach (comkani_shared_selected_posts($page_id, 'steps') as $post) {
        $items[] = [
            'title' => (string) $post->post_title,
            'text' => (string) get_post_meta(
                $post->ID,
                '_comkani_step_text',
                true
            ),
            'image' => (int) get_post_thumbnail_id($post->ID),
        ];
    }

    return $items;
}

/**
 * Retrouve ou crée un Client pendant la migration des anciens repeaters.
 *
 * @param array<string, mixed> $item Ancienne donnée du repeater.
 * @param int                  $position Position initiale.
 * @return int Identifiant du Client ou 0 en cas d’échec.
 */
function comkani_shared_find_or_create_client(array $item, int $position): int
{
    $name = sanitize_text_field((string) ($item['name'] ?? ''));
    $image = absint($item['image'] ?? 0);

    if ($name === '' && $image < 1) {
        return 0;
    }

    $existing = get_posts([
        'post_type' => 'comkani_client',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'title' => $name,
        'fields' => 'ids',
    ]);

    $post_id = $existing !== [] ? (int) $existing[0] : 0;

    if ($post_id < 1) {
        $post_id = (int) wp_insert_post([
            'post_type' => 'comkani_client',
            'post_status' => 'publish',
            'post_title' => $name !== '' ? $name : 'Client',
            'menu_order' => $position,
        ]);
    }

    if ($post_id < 1) {
        return 0;
    }

    if ($image > 0 && get_post_thumbnail_id($post_id) < 1) {
        set_post_thumbnail($post_id, $image);
    }

    $url = esc_url_raw((string) ($item['url'] ?? ''));

    if ($url !== '' && get_post_meta($post_id, '_comkani_client_url', true) === '') {
        update_post_meta($post_id, '_comkani_client_url', $url);
    }

    return $post_id;
}

/**
 * Retrouve ou crée une Étape pendant la migration des anciens repeaters.
 *
 * @param array<string, mixed> $item Ancienne donnée du repeater.
 * @param int                  $position Position initiale.
 * @return int Identifiant de l’Étape ou 0 en cas d’échec.
 */
function comkani_shared_find_or_create_step(array $item, int $position): int
{
    $title = sanitize_text_field((string) ($item['title'] ?? ''));

    if ($title === '') {
        return 0;
    }

    $existing = get_posts([
        'post_type' => 'comkani_step',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'title' => $title,
        'fields' => 'ids',
    ]);

    $post_id = $existing !== [] ? (int) $existing[0] : 0;

    if ($post_id < 1) {
        $post_id = (int) wp_insert_post([
            'post_type' => 'comkani_step',
            'post_status' => 'publish',
            'post_title' => $title,
            'menu_order' => $position,
        ]);
    }

    if ($post_id < 1) {
        return 0;
    }

    $text = sanitize_textarea_field((string) ($item['text'] ?? ''));

    if ($text !== '' && get_post_meta($post_id, '_comkani_step_text', true) === '') {
        update_post_meta($post_id, '_comkani_step_text', $text);
    }

    $image = absint($item['image'] ?? 0);

    if ($image > 0 && get_post_thumbnail_id($post_id) < 1) {
        set_post_thumbnail($post_id, $image);
    }

    return $post_id;
}

/**
 * Lit une ancienne valeur de page sans dépendre du JSON actuel de l’éditeur.
 *
 * @param string $slug Slug de la page.
 * @param string $field Ancienne clé du champ.
 * @return array<int, mixed>
 */
function comkani_shared_legacy_items(string $slug, string $field): array
{
    $page = get_page_by_path($slug, OBJECT, 'page');

    if (!$page instanceof WP_Post) {
        return [];
    }

    $value = get_post_meta(
        $page->ID,
        comkani_page_editor_meta_key($slug, $field),
        true
    );

    return is_array($value) ? array_values($value) : [];
}

/**
 * Retourne les Étapes génériques prévues pour un nouveau projet.
 *
 * @return array<int, mixed>
 */
function comkani_shared_seed_defaults(): array
{
    $defaults = comkani_core_get('sharedContent.steps.defaults', []);

    return is_array($defaults) ? array_values($defaults) : [];
}

/**
 * Migre une seule fois les anciens repeaters vers les contenus partagés.
 *
 * Les anciennes métadonnées restent intactes afin de permettre un retour
 * arrière tant que la migration n’a pas été validée sur le projet.
 *
 * @return void
 */
function comkani_shared_migrate_legacy_content(): void
{
    if ((int) get_option('comkani_shared_content_version', 0) >= 1) {
        return;
    }

    $client_map = [];
    $client_position = 0;

    foreach (comkani_shared_pages('clients') as $slug) {
        $page = get_page_by_path($slug, OBJECT, 'page');

        if (!$page instanceof WP_Post) {
            continue;
        }

        $legacy = comkani_shared_legacy_items($slug, 'client_logos');
        $selected = [];

        foreach ($legacy as $item) {
            if (!is_array($item)) {
                continue;
            }

            $signature = sanitize_title((string) ($item['name'] ?? ''));

            if ($signature === '') {
                $signature = 'image-' . absint($item['image'] ?? 0);
            }

            if (!isset($client_map[$signature])) {
                $client_map[$signature] = comkani_shared_find_or_create_client(
                    $item,
                    $client_position++
                );
            }

            if ($client_map[$signature] > 0) {
                $selected[] = $client_map[$signature];
            }
        }

        if ($selected !== []) {
            update_post_meta(
                $page->ID,
                '_comkani_shared_clients',
                array_values(array_unique($selected))
            );
        }
    }

    $all_client_ids = array_values(
        array_unique(
            array_filter(
                array_map('absint', $client_map)
            )
        )
    );

    if ($all_client_ids !== []) {
        foreach (comkani_shared_pages('clients') as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');

            if (!$page instanceof WP_Post) {
                continue;
            }

            if (!metadata_exists('post', $page->ID, '_comkani_shared_clients')) {
                update_post_meta(
                    $page->ID,
                    '_comkani_shared_clients',
                    $all_client_ids
                );
            }
        }
    }

    $step_map = [];
    $step_position = 0;
    $step_pages = comkani_shared_pages('steps');

    foreach ($step_pages as $slug) {
        $page = get_page_by_path($slug, OBJECT, 'page');

        if (!$page instanceof WP_Post) {
            continue;
        }

        $legacy = comkani_shared_legacy_items($slug, 'steps');
        $selected = [];

        foreach ($legacy as $item) {
            if (!is_array($item)) {
                continue;
            }

            $signature = sanitize_title((string) ($item['title'] ?? ''));

            if ($signature === '') {
                continue;
            }

            if (!isset($step_map[$signature])) {
                $step_map[$signature] = comkani_shared_find_or_create_step(
                    $item,
                    $step_position++
                );
            }

            if ($step_map[$signature] > 0) {
                $selected[] = $step_map[$signature];
            }
        }

        if ($selected !== []) {
            update_post_meta(
                $page->ID,
                '_comkani_shared_steps',
                array_values(array_unique($selected))
            );
        }
    }

    if ($step_map === [] && comkani_shared_enabled('steps')) {
        foreach (comkani_shared_seed_defaults() as $item) {
            if (!is_array($item)) {
                continue;
            }

            $post_id = comkani_shared_find_or_create_step(
                $item,
                $step_position++
            );

            if ($post_id > 0) {
                $step_map[sanitize_title((string) ($item['title'] ?? ''))] = $post_id;
            }
        }

        $ids = array_values(array_filter(array_map('absint', $step_map)));

        foreach ($step_pages as $slug) {
            $page = get_page_by_path($slug, OBJECT, 'page');

            if ($page instanceof WP_Post && $ids !== []) {
                update_post_meta($page->ID, '_comkani_shared_steps', $ids);
            }
        }
    }

    $configured_pages = array_unique(array_merge(
        comkani_shared_pages('clients'),
        comkani_shared_pages('steps')
    ));

    $has_page = false;

    foreach ($configured_pages as $slug) {
        if (get_page_by_path($slug, OBJECT, 'page') instanceof WP_Post) {
            $has_page = true;
            break;
        }
    }

    if ($has_page) {
        update_option('comkani_shared_content_version', 1, false);
    }
}

add_action('admin_init', 'comkani_shared_migrate_legacy_content', 20);
