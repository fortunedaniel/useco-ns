<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/security.php
// Rôle : applique le durcissement de sécurité natif du Starter ComKani
//
// Ce qu'il fait :
// - désactive l'éditeur de fichiers WordPress
// - limite les fonctionnalités exposées selon config/src/project.ts
// - protège l'énumération des utilisateurs et les endpoints REST sensibles
// - ajoute des headers HTTP défensifs compatibles avec les hébergements mutualisés
// - délègue la validation des uploads techniques au thème
// - réserve les mots de passe d'application aux administrateurs ComKani
//
// Fichiers liés :
// - config/src/project.ts
// - packages/comkani-core/includes/admin.php
// - packages/comkani-core/includes/integrations.php
// - inc/uploads.php
// =============================================================================
defined('ABSPATH') || exit;

if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

/**
 * Security disable xmlrpc : fonction du module security.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_security_disable_xmlrpc(bool $enabled): bool
{
    return (bool) comkani_core_get('security.xmlrpc', false)
        ? $enabled
        : false;
}

add_filter('xmlrpc_enabled', 'comkani_security_disable_xmlrpc');

/**
 * Security application passwords : fonction du module security.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_security_application_passwords(bool $available): bool
{
    return (bool) comkani_core_get('security.applicationPasswords', true)
        ? $available
        : false;
}

add_filter('wp_is_application_passwords_available', 'comkani_security_application_passwords');

/**
 * Security application passwords for user : fonction du module security.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_security_application_passwords_for_user(
    bool $available,
    WP_User $user
): bool {
    if (!$available) {
        return false;
    }

    return user_can($user, 'comkani_manage_project');
}

add_filter(
    'wp_is_application_passwords_available_for_user',
    'comkani_security_application_passwords_for_user',
    10,
    2
);

/**
 * Security rest endpoints : fonction du module security.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_security_rest_endpoints(array $endpoints): array
{
    if (
        is_user_logged_in()
        || (bool) comkani_core_get('security.publicUserRestEndpoints', false)
    ) {
        return $endpoints;
    }

    unset($endpoints['/wp/v2/users']);
    unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);

    return $endpoints;
}

add_filter('rest_endpoints', 'comkani_security_rest_endpoints');

/**
 * Security block author enumeration : fonction du module security.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_security_block_author_enumeration(): void
{
    if (
        is_admin()
        || is_user_logged_in()
        || (bool) comkani_core_get('security.authorEnumeration', false)
    ) {
        return;
    }

    if (isset($_GET['author']) && absint($_GET['author']) > 0) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}

add_action('template_redirect', 'comkani_security_block_author_enumeration', 1);

/**
 * Security headers : fonction du module security.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

add_action('send_headers', 'comkani_security_headers');

/**
 * Security hide login errors : fonction du module security.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_security_hide_login_errors(): string
{
    return 'Les informations de connexion sont incorrectes.';
}

add_filter('login_errors', 'comkani_security_hide_login_errors');

/**
 * Security environment robots : fonction du module security.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_security_environment_robots(array $robots): array
{
    if (wp_get_environment_type() === 'production') {
        return $robots;
    }

    $robots['noindex'] = true;
    $robots['nofollow'] = true;

    return $robots;
}

add_filter('wp_robots', 'comkani_security_environment_robots');

// =============================================================================
// URL DE CONNEXION PERSONNALISÉE
// =============================================================================

/**
 * Retourne le slug de connexion configuré par l'agence.
 *
 * Le slug reste défini dans `config/security.json` et n'est pas modifiable
 * depuis le back-office client.
 *
 * @return string Slug nettoyé ou chaîne vide si la fonction est désactivée.
 */
/**
 * Exécute la fonction security login slug.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkani_security_login_slug(): string
{
    if (!(bool) comkani_core_get('login.enabled', false)) {
        return '';
    }

    return sanitize_title(
        (string) comkani_core_get('login.slug', '')
    );
}

/**
 * Enregistre la route WordPress correspondant à l'URL de connexion ComKani.
 *
 * Exemple :
 *   /acces-client-7f4k2/ -> wp-login.php
 *
 * @return void
 */
/**
 * Exécute la fonction security register login route.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkani_security_register_login_route(): void
{
    $slug = comkani_security_login_slug();

    if ($slug === '') {
        return;
    }

    add_rewrite_rule(
        '^' . preg_quote($slug, '/') . '/?$',
        'index.php?comkani_login=1',
        'top'
    );
}
add_action('init', 'comkani_security_register_login_route', 1);

/**
 * Autorise la variable interne utilisée par la route de connexion.
 *
 * @param string[] $vars Variables WordPress autorisées.
 * @return string[] Variables complétées.
 */
function comkani_security_login_query_var(array $vars): array
{
    $vars[] = 'comkani_login';

    return $vars;
}
add_filter('query_vars', 'comkani_security_login_query_var');

/**
 * Recharge les règles de réécriture lorsque le slug de connexion change.
 *
 * Cette opération n'est exécutée qu'une fois par changement de configuration,
 * jamais à chaque requête.
 *
 * @return void
 */
function comkani_security_maybe_flush_login_rules(): void
{
    $slug = comkani_security_login_slug();
    $stored = (string) get_option('comkani_login_slug_registered', '');

    if ($slug === $stored) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('comkani_login_slug_registered', $slug, false);
}
add_action('admin_init', 'comkani_security_maybe_flush_login_rules', 6);

/**
 * Charge l'écran WordPress de connexion depuis la route personnalisée.
 *
 * La constante permet au garde de `wp-login.php` de distinguer une requête
 * légitime provenant de la route ComKani d'un accès direct à l'ancienne URL.
 *
 * @return never|void
 */
function comkani_security_render_custom_login()
{
    if ((int) get_query_var('comkani_login') !== 1) {
        return;
    }

    if (!defined('COMKANI_CUSTOM_LOGIN_REQUEST')) {
        define('COMKANI_CUSTOM_LOGIN_REQUEST', true);
    }

    require ABSPATH . 'wp-login.php';
    exit;
}
add_action('template_redirect', 'comkani_security_render_custom_login', 0);

/**
 * Remplace les URLs WordPress générées vers wp-login.php par la route ComKani.
 *
 * @param string $url URL générée par WordPress.
 * @param string $path Chemin demandé à `site_url()`.
 * @return string URL éventuellement remplacée.
 */
function comkani_security_filter_login_site_url(string $url, string $path): string
{
    $slug = comkani_security_login_slug();

    if ($slug === '' || strpos($path, 'wp-login.php') !== 0) {
        return $url;
    }

    $query = parse_url($url, PHP_URL_QUERY);
    $custom = home_url('/' . $slug . '/');

    return is_string($query) && $query !== ''
        ? $custom . '?' . $query
        : $custom;
}
add_filter('site_url', 'comkani_security_filter_login_site_url', 20, 2);

/**
 * Remplace également les liens de récupération de mot de passe générés avec
 * `network_site_url()` sur une installation WordPress classique.
 *
 * @param string $url URL réseau générée par WordPress.
 * @param string $path Chemin demandé.
 * @return string URL éventuellement remplacée.
 */
function comkani_security_filter_login_network_url(string $url, string $path): string
{
    return comkani_security_filter_login_site_url($url, $path);
}
add_filter('network_site_url', 'comkani_security_filter_login_network_url', 20, 2);

/**
 * Bloque l'accès direct à wp-login.php lorsque la route personnalisée est active.
 *
 * Les requêtes provenant de la route ComKani sont explicitement autorisées.
 * Les utilisateurs déjà connectés restent également autorisés afin de ne pas
 * casser certains flux WordPress internes pendant la transition.
 *
 * @return void
 */
function comkani_security_block_direct_login(): void
{
    if (
        comkani_security_login_slug() === ''
        || defined('COMKANI_CUSTOM_LOGIN_REQUEST')
        || is_user_logged_in()
    ) {
        return;
    }

    status_header(404);
    nocache_headers();
    wp_die(
        'Page introuvable.',
        'Page introuvable',
        ['response' => 404]
    );
}
add_action('login_init', 'comkani_security_block_direct_login', 0);
