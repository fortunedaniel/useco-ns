<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/cache.php
// Rôle : centralise la purge des caches accessibles depuis WordPress
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - invalide uniquement les caches de performance ComKani identifiés
// - conserve les transients fonctionnels (notamment anti-abus formulaires)
// - déclenche un hook ComKani pour les intégrations de cache complémentaires
// - protège la purge manuelle avec une permission et un nonce WordPress
// - mémorise l'instant de la dernière purge pour l'écran Cache
//
// Ce qu'il ne fait pas :
// - ne supprime pas arbitrairement des fichiers du serveur
// - ne vide pas un cache serveur OVH ou IONOS inaccessible depuis WordPress
// - ne réinitialise pas OPcache sur un hébergement mutualisé
// - ne supprime pas les contenus, utilisateurs ou données métier du site
// Fichiers liés :
// - voir les imports, appels et chemins référencés directement dans ce fichier
//
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Invalide les données de performance recalculables du Core.
 *
 * Ne supprimer aucun transient global : certains sont des compteurs de
 * protection des formulaires ou des états temporaires de traitement.
 *
 * @return string[] Couches de performance ComKani invalidées.
 */
function comkani_clear_performance_transients(): array
{
    delete_transient('comkani_media_performance_summary');

    return ['ComKani : analyse des médias'];
}

/**
 * Purge les caches des extensions reconnues lorsqu'elles sont actives.
 *
 * L'intégration reste défensive : chaque fonction ou hook est vérifié avant
 * appel afin que le Core reste compatible avec un hébergement WordPress
 * classique, avec ou sans extension de cache.
 *
 * @return string[] Liste des couches de cache complémentaires purgées.
 */
function comkani_clear_plugin_caches(): array
{
    $cleared = [];

    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
        $cleared[] = 'WP Rocket';
    }

    if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
        autoptimizeCache::clearall();
        $cleared[] = 'Autoptimize';
    }

    if (has_action('litespeed_purge_all')) {
        do_action('litespeed_purge_all');
        $cleared[] = 'LiteSpeed Cache';
    }

    return $cleared;
}

/**
 * Purge les caches de performance sans effacer les états fonctionnels.
 *
 * Les caches objets persistants et tous les transients WordPress ne sont
 * jamais purgés globalement ici. Les intégrations tierces peuvent purger
 * leurs propres caches de pages selon leurs API officielles.
 *
 * @return void
 */
function comkani_clear_caches(): void
{
    $core_caches = comkani_clear_performance_transients();
    $plugin_caches = comkani_clear_plugin_caches();

    // Compatibilité avec les intégrations ComKani existantes. Les extensions
    // abonnées à ce hook doivent respecter la même politique de purge ciblée.
    do_action('comkani_clear_cache');

    update_option(
        'comkani_cache_last_layers',
        array_values(array_unique(array_merge($core_caches, $plugin_caches))),
        false
    );

    update_option('comkani_cache_last_cleared_at', time(), false);
}

/**
 * Handle clear caches : fonction du module cache.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_handle_clear_caches(): void
{
    if (!current_user_can('comkani_manage_cache')) {
        wp_die('Vous n’avez pas l’autorisation de vider les caches.');
    }

    check_admin_referer('comkani_clear_caches');

    comkani_clear_caches();

    wp_safe_redirect(
        add_query_arg(
            'comkani_cache_cleared',
            '1',
            admin_url('admin.php?page=comkani-cache')
        )
    );

    exit;
}

add_action(
    'admin_post_comkani_clear_caches',
    'comkani_handle_clear_caches'
);

/**
 * Cache admin notice : fonction du module cache.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_cache_admin_notice(): void
{
    if (!current_user_can('comkani_manage_cache')) {
        return;
    }

    $cache_cleared = isset($_GET['comkani_cache_cleared'])
        ? sanitize_key(wp_unslash($_GET['comkani_cache_cleared']))
        : '';

    if ($cache_cleared !== '1') {
        return;
    }

    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>Les caches de performance compatibles ont été purgés. Les données temporaires fonctionnelles sont conservées.</p>';
    echo '</div>';
}

add_action(
    'admin_notices',
    'comkani_cache_admin_notice'
);
