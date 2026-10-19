<?php

// =============================================================================
// Fichier : scripts/wp/database-cleanup.php
// Rôle : contrôler et nettoyer prudemment les données techniques WordPress
//
// Ce qu'il fait :
// - fonctionne uniquement via WP-CLI
// - contrôle les révisions, brouillons automatiques et métadonnées orphelines
// - supprime les transients expirés
// - peut appliquer le nettoyage sûr lorsque COMKANI_APPLY=1
// - ne dépend jamais d'un préfixe de tables écrit en dur
//
// Ce qu'il ne fait pas :
// - ne vide jamais wp_posts, wp_terms ou wp_options en masse
// - ne supprime pas les contenus publiés, utilisateurs ou médias
// - ne supprime pas les tables d'une extension sans règle explicite
// - ne remplace pas une sauvegarde préalable avant livraison
//
// Fichiers liés :
// - scripts/wp/run.ts
// - docs/index.html
// =============================================================================

defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    exit("Ce script doit être exécuté avec WP-CLI.\n");
}

/**
 * Retourne le nombre de lignes correspondant à une requête SQL de comptage.
 *
 * @param string $sql Requête SQL retournant une valeur entière.
 * @return int Nombre de lignes détectées.
 */
function comkani_cleanup_count(string $sql): int
{
    global $wpdb;

    return (int) $wpdb->get_var($sql);
}

/**
 * Supprime les transients arrivés à expiration.
 *
 * Seuls les transients dont le timeout est dépassé sont supprimés. Les autres
 * caches restent en place et peuvent être purgés séparément par le script cache.
 *
 * @return int Nombre approximatif de transients expirés supprimés.
 */
function comkani_cleanup_expired_transients(): int
{
    global $wpdb;

    $now = time();
    $timeoutPrefix = '_transient_timeout_';
    $valuePrefix = '_transient_';

    $timeouts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            $wpdb->esc_like($timeoutPrefix) . '%',
            $now
        ),
        ARRAY_A
    );

    $count = 0;
    foreach ((array) $timeouts as $row) {
        $timeoutName = (string) ($row['option_name'] ?? '');
        if ($timeoutName === '') {
            continue;
        }

        $suffix = substr($timeoutName, strlen($timeoutPrefix));
        delete_option($timeoutName);
        delete_option($valuePrefix . $suffix);
        $count++;
    }

    return $count;
}

/**
 * Affiche un élément du rapport de nettoyage.
 *
 * @param string $label Libellé compréhensible de l'élément.
 * @param int $count Nombre d'éléments détectés.
 * @param bool $applied Indique si le nettoyage a été appliqué.
 * @return void
 */
function comkani_cleanup_report(string $label, int $count, bool $applied): void
{
    $status = $applied ? 'nettoyé' : 'à nettoyer';
    WP_CLI::log(sprintf('%s : %d (%s)', $label, $count, $status));
}

$apply = getenv('COMKANI_APPLY') === '1';

global $wpdb;

$revisions = comkani_cleanup_count(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
);
$autoDrafts = comkani_cleanup_count(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
);
$orphanPostmeta = comkani_cleanup_count(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
);
$orphanCommentmeta = comkani_cleanup_count(
    "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
);
$orphanTermmeta = comkani_cleanup_count(
    "SELECT COUNT(*) FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL"
);
$orphanRelationships = comkani_cleanup_count(
    "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL"
);

if ($apply) {
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
    $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL");
    $wpdb->query("DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL");
    $wpdb->query("DELETE tm FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL");
    $wpdb->query("DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL");
}

comkani_cleanup_report('Révisions', $revisions, $apply);
comkani_cleanup_report('Brouillons automatiques', $autoDrafts, $apply);
comkani_cleanup_report('Postmeta orphelines', $orphanPostmeta, $apply);
comkani_cleanup_report('Commentmeta orphelines', $orphanCommentmeta, $apply);
comkani_cleanup_report('Termmeta orphelines', $orphanTermmeta, $apply);
comkani_cleanup_report('Relations de taxonomie orphelines', $orphanRelationships, $apply);

if ($apply) {
    $expired = comkani_cleanup_expired_transients();
    comkani_cleanup_report('Transients expirés', $expired, true);
    WP_CLI::success('Nettoyage BDD sûr terminé.');
} else {
    WP_CLI::success('Contrôle BDD terminé. Relancer avec COMKANI_APPLY=1 pour appliquer uniquement le nettoyage sûr.');
}
