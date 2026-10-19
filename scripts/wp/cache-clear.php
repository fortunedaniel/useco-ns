<?php

// =============================================================================
// Fichier : scripts/wp/cache-clear.php
// Rôle : purger les caches WordPress et les caches plugins connus
//
// Ce qu'il fait :
// - vide le cache objet WordPress
// - supprime les transients WordPress
// - appelle la purge ComKani Core lorsqu'elle est disponible
// - purge WP Rocket, Autoptimize et LiteSpeed lorsqu'ils sont présents
// - conserve une sortie WP-CLI lisible pour le diagnostic
//
// Ce qu'il ne fait pas :
// - ne supprime aucun contenu ni média
// - ne réinitialise pas OPcache sur l'hébergement
// - ne supprime pas manuellement des dossiers de cache serveur
// - ne remplace pas une purge CDN externe non connectée à WordPress
// =============================================================================

defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    exit("Ce script doit être exécuté avec WP-CLI.\n");
}

/**
 * Purge les caches accessibles depuis l'installation WordPress courante.
 *
 * @return string[] Liste des couches de cache effectivement purgées.
 */
function comkani_cli_clear_cache(): array
{
    $cleared = [];

    if (function_exists('comkani_clear_caches')) {
        comkani_clear_caches();
        $layers = get_option('comkani_cache_last_layers', []);

        return is_array($layers)
            ? array_values(array_map('strval', $layers))
            : ['ComKani Core'];
    }

    wp_cache_flush();
    $cleared[] = 'cache objet WordPress';

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

    return array_values(array_unique($cleared));
}

foreach (comkani_cli_clear_cache() as $layer) {
    WP_CLI::log("Purgé : {$layer}");
}

WP_CLI::success('Purge des caches terminée.');
