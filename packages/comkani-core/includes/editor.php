<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/editor.php
// Rôle : cadre l'édition des pages du Starter ComKani
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - remplace l'éditeur de blocs des Pages par des champs simples
// - lit la définition des champs depuis config/src/project.ts
// - permet au client de modifier uniquement les contenus prévus
// - laisse le HTML, les classes et la structure aux templates du thème
// - gère textes, images, médias, liens, listes, cases et répétiteurs
// - conserve Gutenberg pour les Articles / Ressources
//
// Ce qu'il ne fait pas :
// - ne dépend pas de Node.js ou Vite en production
// - ne contient pas de contenu client codé en dur sauf valeur de repli explicitement prévue
//
// Fichiers liés :
// - config/src/project.ts
// - packages/comkani-core/assets/editor.js
// - pages/*.php
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Page editor enabled : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_enabled(): bool
{
    return (bool) comkani_core_get('pageEditor.enabled', true);
}

/**
 * Page editor pages : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_pages(): array
{
    $pages = comkani_core_get('pageEditor.pages', []);

    return is_array($pages) ? $pages : [];
}

/**
 * Page editor page config : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_page_config(string $slug): array
{
    $pages = comkani_page_editor_pages();
    $config = $pages[$slug] ?? [];

    return is_array($config) ? $config : [];
}

/**
 * Page editor post slug : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_post_slug(int $post_id): string
{
    if ($post_id < 1) {
        return '';
    }

    return sanitize_title((string) get_post_field('post_name', $post_id));
}

/**
 * Page editor meta key : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_meta_key(string $page_slug, string $field_key): string
{
    return sprintf(
        '_comkani_page_%s_%s',
        sanitize_key($page_slug),
        sanitize_key($field_key)
    );
}

/**
 * Page editor fields : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_fields(string $page_slug): array
{
    $config = comkani_page_editor_page_config($page_slug);
    $fields = [];

    foreach ((array) ($config['groups'] ?? []) as $group) {
        if (!is_array($group)) {
            continue;
        }

        foreach ((array) ($group['fields'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = sanitize_key((string) ($field['key'] ?? ''));

            if ($key !== '') {
                $fields[$key] = $field;
            }
        }
    }

    return $fields;
}

/**
 * Page field : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_field(
    string $field_key,
    int $post_id = 0,
    string $page_slug = ''
) {
    $post_id = $post_id > 0
        ? $post_id
        : (int) get_queried_object_id();

    if ($page_slug === '') {
        $page_slug = comkani_page_editor_post_slug(
            $post_id
        );
    }

    if ($post_id < 1 || $page_slug === '') {
        return '';
    }

    $fields = comkani_page_editor_fields(
        $page_slug
    );

    $field = $fields[$field_key] ?? [];

    $meta_key = comkani_page_editor_meta_key(
        $page_slug,
        $field_key
    );

    if (metadata_exists(
        'post',
        $post_id,
        $meta_key
    )) {
        return get_post_meta(
            $post_id,
            $meta_key,
            true
        );
    }

    return $field['default'] ?? '';
}

// Textes avec paragraphes
if (!function_exists(
    'comkani_textarea_paragraphs'
)) {
    /**
     * Textarea paragraphs : fonction du module editor.
     *
     * @see Signature de la fonction pour les parametres et le type retourne.
     */
    function comkani_textarea_paragraphs(
        string $value
    ): string {
        return wpautop(
            esc_html($value),
            true
        );
    }
}

// Textes avec retours à la ligne
if (!function_exists(
    'comkani_textarea_lines'
)) {
    /**
     * Textarea lines : fonction du module editor.
     *
     * @see Signature de la fonction pour les parametres et le type retourne.
     */
    function comkani_textarea_lines(
        string $value
    ): string {
        return nl2br(
            esc_html($value),
            false
        );
    }
}

// Textes séparés en blocs
if (!function_exists(
    'comkani_textarea_blocks'
)) {
    /**
     * Textarea blocks : fonction du module editor.
     *
     * @see Signature de la fonction pour les parametres et le type retourne.
     */
    function comkani_textarea_blocks(
        string $value,
        int $limit = 0
    ): array {
        $value = trim(
            str_replace(
                ["\r\n", "\r"],
                "\n",
                $value
            )
        );

        if ($value === '') {
            return [];
        }

        $pattern = '/\n[\t ]*\n+/';

        $blocks = $limit > 0
            ? preg_split(
                $pattern,
                $value,
                $limit
            )
            : preg_split(
                $pattern,
                $value
            );

        if (!is_array($blocks)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    'trim',
                    $blocks
                ),
                static fn(
                    string $block
                ): bool => $block !== ''
            )
        );
    }
}

/**
 * Page editor managed post : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_managed_post(
    int $post_id
): bool {
    if (
        $post_id < 1
        || get_post_type($post_id) !== 'page'
    ) {
        return false;
    }

    $page_slug = comkani_page_editor_post_slug(
        $post_id
    );

    return (
        $page_slug !== ''
        && comkani_page_editor_page_config(
            $page_slug
        ) !== []
    );
}

/**
 * Détermine si une page utilise l'éditeur de texte légal de WordPress.
 *
 * ComKani conserve un éditeur classique pour les pages légales déclarées
 * par le Core, ainsi que pour la page de confidentialité WordPress.
 *
 * @param int $post_id Identifiant de la page à vérifier.
 * @return bool True uniquement pour une page légale reconnue.
 */
function comkani_page_editor_is_legal_page(int $post_id): bool
{
    if ($post_id < 1 || get_post_type($post_id) !== 'page') {
        return false;
    }

    if ((int) get_option('wp_page_for_privacy_policy') === $post_id) {
        return true;
    }

    if ((string) get_post_meta($post_id, '_comkani_required_legal_page', true) !== '') {
        return true;
    }

    if (function_exists('comkani_legal_enabled_definitions')) {
        $slug = comkani_page_editor_post_slug($post_id);

        foreach (comkani_legal_enabled_definitions() as $definition) {
            if ($slug !== '' && $slug === (string) ($definition['slug'] ?? '')) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Supprime l'éditeur natif sur les pages ordinaires dans l'administration.
 *
 * La modification est limitée à la requête d'administration en cours.
 * Les articles et les types de contenus personnalisés ne sont pas concernés.
 * Les pages légales conservent l'éditeur classique WordPress.
 *
 * @return void
 */
function comkani_page_editor_prepare_admin_screen(): void
{
    if (!is_admin()) {
        return;
    }

    global $pagenow;

    if (!in_array($pagenow, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    $post_type = $post_id > 0
        ? get_post_type($post_id)
        : sanitize_key((string) ($_GET['post_type'] ?? 'post'));

    if ($post_type !== 'page') {
        return;
    }

    if ($post_id > 0 && comkani_page_editor_is_legal_page($post_id)) {
        return;
    }

    remove_post_type_support('page', 'editor');
}

add_action('admin_init', 'comkani_page_editor_prepare_admin_screen', 100);

/**
 * Réserve Gutenberg aux autres types de contenus autorisés par WordPress.
 *
 * Toutes les pages utilisent une édition contrôlée par ComKani ; les pages
 * légales font exception et conservent l'éditeur classique de WordPress.
 *
 * @param bool    $use_block_editor Autorisation calculée par WordPress.
 * @param WP_Post $post             Publication concernée.
 * @return bool Autorisation définitive de l'éditeur de blocs.
 */
function comkani_page_editor_disable_block_editor(bool $use_block_editor, WP_Post $post): bool
{
    return $post->post_type === 'page' ? false : $use_block_editor;
}

add_filter('use_block_editor_for_post', 'comkani_page_editor_disable_block_editor', 100, 2);

/**
 * Désactive également Gutenberg lors de la création d'une nouvelle page.
 *
 * @param bool   $use_block_editor Autorisation initiale.
 * @param string $post_type        Type de contenu enregistré.
 * @return bool Autorisation de l'éditeur de blocs.
 */
function comkani_page_editor_disable_block_editor_for_pages(bool $use_block_editor, string $post_type): bool
{
    return $post_type === 'page' ? false : $use_block_editor;
}

add_filter('use_block_editor_for_post_type', 'comkani_page_editor_disable_block_editor_for_pages', 100, 2);

/**
 * Avertit lorsqu'une page sans champs ComKani ne peut plus éditer son corps.
 *
 * @return void
 */
function comkani_page_editor_unconfigured_notice(): void
{
    global $pagenow;

    if ($pagenow !== 'post.php') {
        return;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

    if ($post_id < 1 || get_post_type($post_id) !== 'page'
        || comkani_page_editor_is_legal_page($post_id)
        || (comkani_page_editor_enabled() && comkani_page_editor_managed_post($post_id))) {
        return;
    }

    echo '<div class="notice notice-warning"><p>'
        . esc_html__('Cette page ne dispose pas encore de champs ComKani configurés. Son contenu existant est conservé, mais son édition est verrouillée jusqu’à la configuration de son interface.', 'comkani-core')
        . '</p></div>';
}

add_action('admin_notices', 'comkani_page_editor_unconfigured_notice');


/**
 * Page editor hide native custom fields : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_hide_native_custom_fields(): void
{
    if (current_user_can('comkani_manage_project')) {
        return;
    }

    remove_meta_box('postcustom', 'page', 'normal');
}

add_action(
    'add_meta_boxes_page',
    'comkani_page_editor_hide_native_custom_fields',
    100
);

/**
 * Page editor register meta boxes : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_register_meta_boxes(): void
{
    if (!comkani_page_editor_enabled()) {
        return;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

    if ($post_id < 1) {
        return;
    }

    $page_slug = comkani_page_editor_post_slug($post_id);
    $config = comkani_page_editor_page_config($page_slug);

    foreach ((array) ($config['groups'] ?? []) as $index => $group) {
        if (!is_array($group)) {
            continue;
        }

        $group_key = sanitize_key((string) ($group['key'] ?? 'group-' . $index));
        $group_label = sanitize_text_field((string) ($group['label'] ?? 'Contenu'));

        add_meta_box(
            'comkani-page-' . $group_key,
            $group_label,
            'comkani_page_editor_render_meta_box',
            'page',
            'normal',
            'high',
            [
                'page_slug' => $page_slug,
                'group' => $group,
            ]
        );
    }
}

add_action('add_meta_boxes_page', 'comkani_page_editor_register_meta_boxes');


/**
 * Retourne les IDs des metaboxes ComKani dans l'ordre structurel de la page.
 *
 * L'ordre des groupes dans la configuration est la source de verite :
 * le client peut modifier les contenus, mais pas reorganiser les sections.
 */
function comkani_page_editor_meta_box_order(string $page_slug): array
{
    $config = comkani_page_editor_page_config($page_slug);
    $order = [];

    foreach ((array) ($config['groups'] ?? []) as $index => $group) {
        if (!is_array($group)) {
            continue;
        }

        $group_key = sanitize_key((string) ($group['key'] ?? 'group-' . $index));

        if ($group_key !== '') {
            $order[] = 'comkani-page-' . $group_key;
        }
    }

    return $order;
}

/**
 * Neutralise l'ordre personnalise que WordPress peut memoriser par utilisateur
 * pour les seules metaboxes ComKani, sans modifier l'ordre des metaboxes tierces.
 */
function comkani_page_editor_force_meta_box_order($result)
{
    if (!is_admin() || !comkani_page_editor_enabled() || !is_array($result)) {
        return $result;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

    if (!comkani_page_editor_managed_post($post_id)) {
        return $result;
    }

    $page_slug = comkani_page_editor_post_slug($post_id);
    $locked_order = comkani_page_editor_meta_box_order($page_slug);

    if ($locked_order === []) {
        return $result;
    }

    $current_normal = array_values(array_filter(
        array_map('trim', explode(',', (string) ($result['normal'] ?? '')))
    ));

    $other_boxes = array_values(array_filter(
        $current_normal,
        static fn(string $box_id): bool => !in_array($box_id, $locked_order, true)
    ));

    $result['normal'] = implode(',', array_merge($locked_order, $other_boxes));

    return $result;
}

add_filter(
    'get_user_option_meta-box-order_page',
    'comkani_page_editor_force_meta_box_order',
    100
);

/**
 * Page editor render meta box : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_render_meta_box(WP_Post $post, array $box): void
{
    $args = (array) ($box['args'] ?? []);
    $page_slug = sanitize_title((string) ($args['page_slug'] ?? ''));
    $group = (array) ($args['group'] ?? []);

    wp_nonce_field('comkani_save_page_fields_' . $post->ID, 'comkani_page_fields_nonce');

    foreach ((array) ($group['fields'] ?? []) as $field) {
        if (is_array($field)) {
            comkani_page_editor_render_field($post, $page_slug, $field);
        }
    }
}

/**
 * Page editor render field : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_render_field(WP_Post $post, string $page_slug, array $field): void
{
    $key = sanitize_key((string) ($field['key'] ?? ''));

    if ($key === '') {
        return;
    }

    $label = sanitize_text_field((string) ($field['label'] ?? $key));
    $description = sanitize_text_field((string) ($field['description'] ?? ''));
    $name = 'comkani_page_fields[' . $key . ']';
    $id = 'comkani-page-field-' . $key;
    $value = comkani_page_field($key, $post->ID, $page_slug);

    echo '<div class="comkani-page-field">';
    echo '<label class="comkani-page-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
    comkani_page_editor_render_control($field, $value, $name, $id);

    if ($description !== '') {
        echo '<p class="description">' . esc_html($description) . '</p>';
    }

    echo '</div>';
}

/**
 * Page editor render control : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_render_control(array $field, $value, string $name, string $id): void
{
    $type = sanitize_key((string) ($field['type'] ?? 'text'));

    if ($type === 'hidden') {
        echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
        return;
    }

    if ($type === 'textarea') {
        $rows = max(3, (int) ($field['rows'] ?? 5));
        echo '<textarea class="widefat" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="' . esc_attr((string) $rows) . '">';
        echo esc_textarea((string) $value);
        echo '</textarea>';
        return;
    }

    if (in_array($type, ['image', 'media', 'video'], true)) {
        comkani_page_editor_render_media_field(
            $value,
            $name,
            $id,
            $type
        );
        return;
    }

    if ($type === 'link') {
        $link = is_array($value) ? $value : [];
        echo '<div class="comkani-link-field">';
        echo '<input class="widefat" type="text" name="' . esc_attr($name . '[label]') . '" value="' . esc_attr((string) ($link['label'] ?? '')) . '" placeholder="Libellé">';
        echo '<input class="widefat" type="text" name="' . esc_attr($name . '[url]') . '" value="' . esc_attr((string) ($link['url'] ?? '')) . '" placeholder="/contact/ ou https://exemple.com">';
        echo '</div>';
        return;
    }

    if ($type === 'select') {
        echo '<select class="widefat" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';

        foreach ((array) ($field['options'] ?? []) as $option_value => $option_label) {
            echo '<option value="' . esc_attr((string) $option_value) . '" ' . selected((string) $value, (string) $option_value, false) . '>';
            echo esc_html((string) $option_label);
            echo '</option>';
        }

        echo '</select>';
        return;
    }

    if ($type === 'checkbox') {
        echo '<label class="comkani-checkbox">';
        echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1" ' . checked((bool) $value, true, false) . '> ';
        echo esc_html((string) ($field['checkboxLabel'] ?? 'Activer'));
        echo '</label>';
        return;
    }

    if ($type === 'repeater') {
        comkani_page_editor_render_repeater($field, $value, $name, $id);
        return;
    }

    $input_type = in_array($type, ['url', 'email'], true) ? $type : 'text';
    echo '<input class="widefat" type="' . esc_attr($input_type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
}

/**
 * Page editor render media field : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_render_media_field(
    $value,
    string $name,
    string $id,
    string $type = 'image'
): void {
    $media_id = absint($value);
    $mime = $media_id > 0
        ? (string) get_post_mime_type($media_id)
        : '';
    $preview = $media_id > 0
        ? wp_get_attachment_url($media_id)
        : '';
    $library_type = in_array($type, ['image', 'video'], true)
        ? $type
        : '';

    echo '<div class="comkani-image-field" data-comkani-media-field data-library-type="' . esc_attr($library_type) . '">';
    echo '<input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $media_id) . '" data-comkani-media-id>';
    echo '<div class="comkani-image-preview" data-comkani-media-preview>';

    if ($preview !== '') {
        if (strpos($mime, 'video/') === 0) {
            echo '<video src="' . esc_url($preview) . '" controls muted playsinline></video>';
        } else {
            echo '<img src="' . esc_url($preview) . '" alt="">';
        }
    }

    echo '</div>';
    echo '<button type="button" class="button" data-comkani-media-select>Choisir / remplacer le média</button> ';
    echo '<button type="button" class="button-link-delete" data-comkani-media-remove>Retirer le média</button>';
    echo '</div>';
}

/**
 * Page editor render repeater : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_render_repeater(array $field, $value, string $name, string $id): void
{
    $items = is_array($value) ? array_values($value) : [];
    $max = max(0, (int) ($field['max'] ?? 0));
    $allow_add = (bool) ($field['allowAdd'] ?? true);
    $allow_remove = (bool) ($field['allowRemove'] ?? true);
    $allow_sort = (bool) ($field['allowSort'] ?? true);

    echo '<div class="comkani-repeater" data-comkani-repeater data-max="' . esc_attr((string) $max) . '">';
    echo '<div data-comkani-repeater-items>';

    foreach ($items as $index => $item) {
        comkani_page_editor_render_repeater_item(
            $field,
            is_array($item) ? $item : [],
            $name,
            $id,
            (string) $index,
            $allow_remove,
            $allow_sort
        );
    }

    echo '</div>';

    if ($allow_add) {
        echo '<button type="button" class="button button-secondary" data-comkani-repeater-add>Ajouter</button>';
    }

    echo '<template data-comkani-repeater-template>';
    comkani_page_editor_render_repeater_item(
        $field,
        [],
        $name,
        $id,
        '__INDEX__',
        $allow_remove,
        $allow_sort
    );
    echo '</template>';
    echo '</div>';
}

/**
 * Page editor render repeater item : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_render_repeater_item(
    array $field,
    array $item,
    string $name,
    string $id,
    string $index,
    bool $allow_remove,
    bool $allow_sort
): void {
    echo '<div class="comkani-repeater-item" data-comkani-repeater-item>';
    echo '<div class="comkani-repeater-toolbar">';

    if ($allow_sort) {
        echo '<button type="button" class="button-link" data-comkani-repeater-up>Monter</button> ';
        echo '<button type="button" class="button-link" data-comkani-repeater-down>Descendre</button>';
    }

    if ($allow_remove) {
        echo '<button type="button" class="button-link-delete" data-comkani-repeater-remove>Supprimer</button>';
    }

    echo '</div>';

    foreach ((array) ($field['fields'] ?? []) as $subfield) {
        if (!is_array($subfield)) {
            continue;
        }

        $subkey = sanitize_key((string) ($subfield['key'] ?? ''));

        if ($subkey === '') {
            continue;
        }

        $subtype = sanitize_key((string) ($subfield['type'] ?? 'text'));
        $subname = $name . '[' . $index . '][' . $subkey . ']';
        $subid = $id . '-' . $index . '-' . $subkey;
        $subvalue = $item[$subkey] ?? ($subfield['default'] ?? '');

        if ($subtype === 'hidden') {
            comkani_page_editor_render_control($subfield, $subvalue, $subname, $subid);
            continue;
        }

        echo '<div class="comkani-repeater-field">';
        echo '<label class="comkani-page-label" for="' . esc_attr($subid) . '">';
        echo esc_html((string) ($subfield['label'] ?? $subkey));
        echo '</label>';
        comkani_page_editor_render_control($subfield, $subvalue, $subname, $subid);
        echo '</div>';
    }

    echo '</div>';
}

/**
 * Page editor sanitize link url : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_sanitize_link_url(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (strpos($value, '/') === 0 && strpos($value, '//') !== 0) {
        return sanitize_text_field($value);
    }

    if (strpos($value, '#') === 0) {
        return '#' . sanitize_title(substr($value, 1));
    }

    return esc_url_raw($value, ['http', 'https', 'mailto', 'tel']);
}

/**
 * Page editor sanitize value : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_sanitize_value(array $field, $value)
{
    $type = sanitize_key((string) ($field['type'] ?? 'text'));

    if ($type === 'repeater') {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        $max = max(0, (int) ($field['max'] ?? 0));

        foreach (array_values($value) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $clean = [];

            foreach ((array) ($field['fields'] ?? []) as $subfield) {
                if (!is_array($subfield)) {
                    continue;
                }

                $subkey = sanitize_key((string) ($subfield['key'] ?? ''));

                if ($subkey === '') {
                    continue;
                }

                $clean[$subkey] = comkani_page_editor_sanitize_value(
                    $subfield,
                    $item[$subkey] ?? ($subfield['default'] ?? '')
                );
            }

            if (comkani_page_editor_value_has_content($clean)) {
                $items[] = $clean;
            }

            if ($max > 0 && count($items) >= $max) {
                break;
            }
        }

        return $items;
    }

    if ($type === 'link') {
        $value = is_array($value) ? $value : [];

        return [
            'label' => sanitize_text_field((string) ($value['label'] ?? '')),
            'url' => comkani_page_editor_sanitize_link_url((string) ($value['url'] ?? '')),
        ];
    }

    if (in_array($type, ['image', 'media', 'video'], true)) {
        $attachment_id = absint($value);
        if ($attachment_id === 0) {
            return 0;
        }
        if (get_post_type($attachment_id) !== 'attachment') {
            return 0;
        }
        $mime = (string) get_post_mime_type($attachment_id);
        if ($type === 'image' && strpos($mime, 'image/') !== 0) {
            return 0;
        }
        if ($type === 'video' && strpos($mime, 'video/') !== 0) {
            return 0;
        }
        if ($type === 'media' && strpos($mime, 'image/') !== 0 && strpos($mime, 'video/') !== 0) {
            return 0;
        }
        return $attachment_id;
    }

    if ($type === 'textarea') {
        return sanitize_textarea_field((string) $value);
    }

    if ($type === 'url') {
        return esc_url_raw((string) $value);
    }

    if ($type === 'email') {
        return sanitize_email((string) $value);
    }

    if ($type === 'checkbox') {
        return !empty($value) ? '1' : '';
    }

    if ($type === 'select') {
        $allowed = array_map('strval', array_keys((array) ($field['options'] ?? [])));
        $value = (string) $value;

        return in_array($value, $allowed, true) ? $value : '';
    }

    return sanitize_text_field((string) $value);
}

/**
 * Page editor value has content : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_value_has_content($value): bool
{
    if (is_array($value)) {
        foreach ($value as $item) {
            if (comkani_page_editor_value_has_content($item)) {
                return true;
            }
        }

        return false;
    }

    return $value !== '' && $value !== 0 && $value !== null;
}

/**
 * Page editor save fields : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_save_fields(int $post_id): void
{
    if (!comkani_page_editor_enabled()) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $nonce = isset($_POST['comkani_page_fields_nonce'])
        ? sanitize_text_field((string) wp_unslash($_POST['comkani_page_fields_nonce']))
        : '';

    if ($nonce === '' || !wp_verify_nonce($nonce, 'comkani_save_page_fields_' . $post_id)) {
        return;
    }

    $page_slug = comkani_page_editor_post_slug($post_id);
    $fields = comkani_page_editor_fields($page_slug);

    if ($fields === []) {
        return;
    }

    $submitted = isset($_POST['comkani_page_fields']) && is_array($_POST['comkani_page_fields'])
        ? wp_unslash($_POST['comkani_page_fields'])
        : [];

    foreach ($fields as $field_key => $field) {
        $value = comkani_page_editor_sanitize_value(
            $field,
            $submitted[$field_key] ?? ''
        );

        update_post_meta(
            $post_id,
            comkani_page_editor_meta_key($page_slug, $field_key),
            $value
        );
    }
}

add_action('save_post_page', 'comkani_page_editor_save_fields');

/**
 * Page editor enqueue assets : fonction du module editor.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_page_editor_enqueue_assets(string $hook_suffix): void
{
    if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();

    if (!$screen || $screen->post_type !== 'page') {
        return;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;

    if ($post_id < 1) {
        return;
    }

    $page_slug = comkani_page_editor_post_slug($post_id);

    if (comkani_page_editor_page_config($page_slug) === []) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'comkani-page-editor',
        comkani_core_url('assets/editor.js'),
        [],
        comkani_core_asset_version('assets/editor.js'),
        true
    );

    wp_localize_script(
        'comkani-page-editor',
        'comkaniPageEditor',
        [
            'metaboxOrder' => comkani_page_editor_meta_box_order($page_slug),
        ]
    );
}

add_action('admin_enqueue_scripts', 'comkani_page_editor_enqueue_assets');
