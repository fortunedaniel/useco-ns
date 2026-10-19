<?php

// =============================================================================
// Fichier : scripts/wp-setup.php
// Rôle : initialise la structure WordPress déclarée dans le projet
//
// Objectif :
// - automatiser une opération de développement de façon reproductible
// - conserver cette opération hors du serveur WordPress de production
//
// Ce qu'il fait :
// - crée ou met à jour les pages définies dans config/generated/project-config.php
// - marque les pages créées par ComKani pour permettre une synchronisation sûre
// - peut adopter explicitement des pages existantes avec COMKANI_SYNC_ADOPT=1
// - peut placer à la corbeille les anciennes pages gérées avec COMKANI_SYNC_PRUNE=1
// - configure la page d'accueil et la page des ressources
// - crée et synchronise les menus WordPress du projet
// - applique les réglages WordPress nécessaires via WP-CLI
//
// Ce qu'il ne fait pas :
// - ne s’exécute pas dans le navigateur du site public
// - ne rend pas Node.js obligatoire sur le serveur de production
// - ne supprime jamais définitivement une page WordPress
//
// Fichiers liés :
// - config/generated/project-config.php
// - inc/theme.php
// =============================================================================
defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    exit("Ce script doit être exécuté avec WP-CLI.\n");
}

// Charge la configuration compilée du projet
$theme_root = dirname(__DIR__);
$config_path = $theme_root . '/config/generated/project-config.php';

if (!is_readable($config_path)) {
    WP_CLI::error('Configuration compilée absente. Exécuter npm run config avant wp-setup.php.');
}

$config = require $config_path;

if (!is_array($config)) {
    WP_CLI::error('La configuration compilée est invalide.');
}

$project = (array) ($config['project'] ?? []);
$project_name = trim((string) ($project['name'] ?? ''));
$project_slug = sanitize_title((string) ($project['slug'] ?? ''));

if ($project_name === '' || $project_slug === '') {
    WP_CLI::error('project.name et project.slug sont obligatoires.');
}

if ($project_name === 'ComKani Starter' || $project_slug === 'comkani-starter') {
    WP_CLI::error('Le projet utilise encore l’identité générique du Starter.');
}

// Prépare la création et la mise à jour des pages
/**
 * Setup find page : fonction du module wp-setup.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_setup_find_page(string $slug): ?WP_Post
{
    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'name' => $slug,
        'numberposts' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);

    return $pages !== [] ? $pages[0] : null;
}

/**
 * Setup upsert page : fonction du module wp-setup.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_setup_upsert_page(array $definition, array $page_ids): int
{
    $title = trim((string) ($definition['title'] ?? ''));
    $slug = sanitize_title((string) ($definition['slug'] ?? ''));
    $parent_slug = sanitize_title((string) ($definition['parent'] ?? ''));

    if ($title === '' || $slug === '') {
        WP_CLI::error('Une page déclarée dans config/src/wordpress.ts n’a pas de title ou de slug.');
    }

    $parent_id = $parent_slug !== '' ? (int) ($page_ids[$parent_slug] ?? 0) : 0;
    $existing = comkani_setup_find_page($slug);

    if ($existing instanceof WP_Post) {
        $result = wp_update_post(wp_slash([
            'ID' => $existing->ID,
            'post_title' => $title,
            'post_name' => $slug,
            'post_parent' => $parent_id,
        ]), true);

        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }

        $already_managed = get_post_meta((int) $existing->ID, '_comkani_managed_page', true) === '1';
        $adopt_existing = getenv('COMKANI_SYNC_ADOPT') === '1';

        if ($already_managed || $adopt_existing) {
            update_post_meta((int) $existing->ID, '_comkani_managed_page', '1');
            update_post_meta((int) $existing->ID, '_comkani_page_slug', $slug);
        }

        WP_CLI::log("Page conservée/mise à jour : {$title}");
        return (int) $existing->ID;
    }

    $result = wp_insert_post(wp_slash([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
        'post_parent' => $parent_id,
        'post_content' => (string) ($definition['content'] ?? ''),
    ]), true);

    if (is_wp_error($result)) {
        WP_CLI::error($result->get_error_message());
    }

    update_post_meta((int) $result, '_comkani_managed_page', '1');
    update_post_meta((int) $result, '_comkani_page_slug', $slug);

    WP_CLI::log("Page créée : {$title}");
    return (int) $result;
}

/**
 * Place à la corbeille les anciennes pages gérées par ComKani.
 *
 * Le nettoyage est opt-in et ne concerne que les pages marquées avec
 * `_comkani_managed_page`. Une page WordPress créée manuellement par le client
 * ne peut donc pas être supprimée par une modification de configuration.
 *
 * @param string[] $activeSlugs Slugs actuellement déclarés dans la configuration.
 * @return void
 */
function comkani_setup_prune_managed_pages(array $activeSlugs): void
{
    if (getenv('COMKANI_SYNC_PRUNE') !== '1') {
        return;
    }

    $protectedIds = array_filter([
        (int) get_option('page_on_front'),
        (int) get_option('page_for_posts'),
        (int) get_option('wp_page_for_privacy_policy'),
    ]);

    $managed = get_posts([
        'post_type' => 'page',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'meta_key' => '_comkani_managed_page',
        'meta_value' => '1',
    ]);

    foreach ($managed as $page) {
        if (!$page instanceof WP_Post) {
            continue;
        }

        if (in_array((int) $page->ID, $protectedIds, true)) {
            continue;
        }

        $slug = sanitize_title((string) $page->post_name);
        if (in_array($slug, $activeSlugs, true)) {
            continue;
        }

        wp_trash_post((int) $page->ID);
        WP_CLI::warning("Page gérée déplacée dans la corbeille : {$page->post_title} ({$slug})");
    }
}

// Prépare la création et la synchronisation des menus
/**
 * Setup find page menu item : fonction du module wp-setup.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_setup_find_page_menu_item(int $menu_id, int $page_id): ?WP_Post
{
    $items = wp_get_nav_menu_items($menu_id, ['post_status' => 'any']);

    if (!is_array($items)) {
        return null;
    }

    foreach ($items as $item) {
        if ($item->object === 'page' && (int) $item->object_id === $page_id) {
            return $item;
        }
    }

    return null;
}

/**
 * Setup find custom menu item : fonction du module wp-setup.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_setup_find_custom_menu_item(int $menu_id, string $url): ?WP_Post
{
    $items = wp_get_nav_menu_items($menu_id, ['post_status' => 'any']);

    if (!is_array($items)) {
        return null;
    }

    $normalized_url = untrailingslashit($url);

    foreach ($items as $item) {
        if ($item->type !== 'custom') {
            continue;
        }

        if (untrailingslashit((string) $item->url) === $normalized_url) {
            return $item;
        }
    }

    return null;
}

/**
 * Setup resolve menu url : fonction du module wp-setup.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_setup_resolve_menu_url(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    if (strpos($url, '/') === 0) {
        return home_url($url);
    }

    return esc_url_raw($url);
}

/**
 * Setup sync menu : fonction du module wp-setup.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_setup_sync_menu(
    string $menu_name,
    string $location,
    array $entries,
    array $page_ids,
    array $legal_ids
): void {
    $menu = wp_get_nav_menu_object($menu_name);

    if (!$menu) {
        $menu_id = wp_create_nav_menu($menu_name);

        if (is_wp_error($menu_id)) {
            WP_CLI::error($menu_id->get_error_message());
        }

        $menu = wp_get_nav_menu_object((int) $menu_id);
        WP_CLI::log("Menu créé : {$menu_name}");
    }

    $menu_id = (int) $menu->term_id;

    // Traitement récursif : les sous-menus utilisent le vrai ID de l'élément parent.
    // Une entrée inconnue ne doit jamais être créée comme lien cassé.
    $sync_entries = static function (array $menu_entries, int $parent_item_id = 0) use (
        &$sync_entries, $menu_id, $menu_name, $page_ids, $legal_ids
    ): void {
        foreach ($menu_entries as $entry) {
            $entry = is_string($entry) ? ['page' => $entry] : (array) $entry;
            $children = isset($entry['children']) && is_array($entry['children'])
                ? $entry['children'] : [];
            $page_id = 0;
            if (!empty($entry['page'])) {
                $page_id = (int) ($page_ids[sanitize_title((string) $entry['page'])] ?? 0);
            } elseif (!empty($entry['legal'])) {
                $page_id = (int) ($legal_ids[sanitize_key((string) $entry['legal'])] ?? 0);
            }

            $item_id = 0;
            if ($page_id > 0) {
                $page = get_post($page_id);
                if (!$page instanceof WP_Post) {
                    WP_CLI::warning("Page absente dans le menu {$menu_name}.");
                    continue;
                }
                $label = trim((string) ($entry['label'] ?? $page->post_title));
                $existing = comkani_setup_find_page_menu_item($menu_id, $page_id);
                $old_id = $existing instanceof WP_Post ? (int) $existing->ID : 0;
                $item_id = wp_update_nav_menu_item($menu_id, $old_id, [
                    'menu-item-title' => $label,
                    'menu-item-object' => 'page',
                    'menu-item-object-id' => $page_id,
                    'menu-item-type' => 'post_type',
                    'menu-item-parent-id' => $parent_item_id,
                    'menu-item-status' => 'publish',
                ]);
            } elseif (!empty($entry['url'])) {
                $url = comkani_setup_resolve_menu_url((string) $entry['url']);
                $label = trim((string) ($entry['label'] ?? ''));
                if ($url === '' || $label === '') {
                    WP_CLI::warning("URL ou libellé manquant dans {$menu_name}.");
                    continue;
                }
                $existing = comkani_setup_find_custom_menu_item($menu_id, $url);
                $old_id = $existing instanceof WP_Post ? (int) $existing->ID : 0;
                $item_id = wp_update_nav_menu_item($menu_id, $old_id, [
                    'menu-item-title' => $label,
                    'menu-item-url' => $url,
                    'menu-item-type' => 'custom',
                    'menu-item-parent-id' => $parent_item_id,
                    'menu-item-status' => 'publish',
                ]);
            } else {
                WP_CLI::warning("Élément ignoré dans {$menu_name} : page, page légale ou URL introuvable.");
                continue;
            }
            if (is_wp_error($item_id)) {
                WP_CLI::error($item_id->get_error_message());
            }
            if ($children !== []) {
                $sync_entries($children, (int) $item_id);
            }
        }
    };
    $sync_entries($entries);

    $locations = get_theme_mod('nav_menu_locations', []);
    $locations[$location] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}

// Applique les réglages généraux WordPress
update_option('blogname', $project_name);
update_option('blogdescription', (string) ($project['tagline'] ?? ''));
update_option('timezone_string', (string) ($project['timezone'] ?? 'Europe/Paris'));
update_option('WPLANG', (string) ($project['language'] ?? 'fr_FR'));

// Crée ou met à jour les pages principales
$page_definitions = array_values((array) ($config['pages'] ?? []));
$page_ids = [];

$pending_pages = $page_definitions;
while ($pending_pages !== []) {
    $remaining = [];
    $progress = false;
    foreach ($pending_pages as $definition) {
        $parent_slug = sanitize_title((string) ($definition['parent'] ?? ''));
        if ($parent_slug !== '' && !isset($page_ids[$parent_slug])) {
            $remaining[] = $definition;
            continue;
        }
        $page_ids[sanitize_title((string) ($definition['slug'] ?? ''))]
            = comkani_setup_upsert_page((array) $definition, $page_ids);
        $progress = true;
    }
    if (!$progress) {
        WP_CLI::error('Pages : parent introuvable ou dépendance circulaire. Vérifier config/src/wordpress.ts.');
    }
    $pending_pages = $remaining;
}

// Configure la page d’accueil WordPress
foreach ($page_definitions as $definition) {
    if (empty($definition['home'])) {
        continue;
    }

    $home_slug = sanitize_title((string) ($definition['slug'] ?? ''));
    $home_id = (int) ($page_ids[$home_slug] ?? 0);

    if ($home_id > 0) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $home_id);
        WP_CLI::log("Page d’accueil : {$home_slug}");
    }

    break;
}

// Configure la page et les permaliens des articles
$wordpress_config = (array) ($config['wordpress'] ?? []);
$posts_config = (array) ($wordpress_config['posts'] ?? []);

if (!empty($posts_config['enabled'])) {
    $posts_page_slug = sanitize_title((string) ($posts_config['indexPage'] ?? ''));
    $permalink_structure = trim((string) ($posts_config['permalinkStructure'] ?? ''));

    if ($posts_page_slug === '') {
        WP_CLI::error(
            'wordpress.posts.indexPage est obligatoire lorsque les articles sont activés.'
        );
    }

    $posts_page_id = (int) ($page_ids[$posts_page_slug] ?? 0);

    if ($posts_page_id <= 0) {
        WP_CLI::error("La page des articles \"{$posts_page_slug}\" est introuvable.");
    }

    if ($permalink_structure === '') {
        WP_CLI::error(
            'wordpress.posts.permalinkStructure est obligatoire lorsque les articles sont activés.'
        );
    }

    if (
        strpos($permalink_structure, '/') !== 0
        || substr($permalink_structure, -1) !== '/'
        || !str_contains($permalink_structure, '%postname%')
    ) {
        WP_CLI::error(
            'wordpress.posts.permalinkStructure doit commencer et finir par "/" '
            . 'et contenir %postname%.'
        );
    }

    update_option('page_for_posts', $posts_page_id);

    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure($permalink_structure);

    WP_CLI::log("Page des articles : {$posts_page_slug}");
    WP_CLI::log("Structure des articles : {$permalink_structure}");
}

// Crée ou met à jour les pages légales
$legal_definitions = [
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
$legal_ids = [];

foreach ((array) ($config['legalPages'] ?? []) as $key => $enabled) {
    if ($enabled !== true || !isset($legal_definitions[$key])) {
        continue;
    }

    $definition = $legal_definitions[$key];
    $legal_content = (array) ($config['legalContent'] ?? []);

    if (isset($legal_content[$key])) {
        $definition['content'] = (string) $legal_content[$key];
    }

    $page_id = comkani_setup_upsert_page($definition, $page_ids);
    update_post_meta($page_id, '_wp_page_template', 'templates/page-legal.php');
    $legal_ids[$key] = $page_id;

    if ($key === 'privacyCookies') {
        update_option('wp_page_for_privacy_policy', $page_id);
    }
}

$active_page_slugs = array_values(array_filter(array_map(
    static fn(array $definition): string => sanitize_title((string) ($definition['slug'] ?? '')),
    $page_definitions
)));

foreach ($legal_definitions as $key => $definition) {
    if (($config['legalPages'][$key] ?? false) === true) {
        $active_page_slugs[] = sanitize_title((string) $definition['slug']);
    }
}

comkani_setup_prune_managed_pages(array_values(array_unique($active_page_slugs)));

// Synchronise les menus WordPress du projet
$menus = (array) ($config['menus'] ?? []);

// Synchronise la navigation principale
if (
    isset($menus['primary'])
    && is_array($menus['primary'])
) {
    comkani_setup_sync_menu(
        $project_name . ' - Navigation principale',
        'comkani_menu_primary',
        $menus['primary'],
        $page_ids,
        $legal_ids
    );
}

// Synchronise les groupes de navigation du pied de page
$footer = $menus['footer'] ?? [];

if (
    is_array($footer)
    && $footer !== []
) {
    foreach ($footer as $key => $group) {
        $key = sanitize_key((string) $key);
        $group = (array) $group;

        $label = trim(
            (string) (
                $group['label']
                ?? $key
            )
        );

        $entries = (array) (
            $group['items']
            ?? []
        );

        if (
            $key === ''
            || $entries === []
        ) {
            continue;
        }

        comkani_setup_sync_menu(
            $project_name
                . ' - Pied de page - '
                . $label,
            'comkani_menu_footer_' . $key,
            $entries,
            $page_ids,
            $legal_ids
        );
    }
}

// Synchronise la navigation des informations légales
if (
    isset($menus['legal'])
    && is_array($menus['legal'])
) {
    comkani_setup_sync_menu(
        $project_name . ' - Informations légales',
        'comkani_menu_legal',
        $menus['legal'],
        $page_ids,
        $legal_ids
    );
}

// Finalise les règles de permaliens WordPress
flush_rewrite_rules(true);

WP_CLI::success(
    'Configuration WordPress appliquée. Pages, articles, pages légales et menus sont prêts. '
    . 'Le contenu éditorial et juridique reste à finaliser avant la production.'
);
