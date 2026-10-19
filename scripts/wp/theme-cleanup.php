<?php

// =============================================================================
// Fichier : scripts/wp/theme-cleanup.php
// Rôle : conserver uniquement le thème actif et un thème WordPress de secours
//
// Ce qu'il fait :
// - détecte automatiquement le thème actif
// - choisit un thème Twenty Twenty installé comme thème de secours
// - affiche les thèmes qui seraient supprimés en mode contrôle
// - supprime uniquement les thèmes inactifs lorsque COMKANI_APPLY=1
// - supprime les options theme_mods associées aux thèmes réellement supprimés
//
// Ce qu'il ne fait pas :
// - ne supprime jamais le thème actif
// - ne supprime rien si aucun thème de secours valide n'est disponible
// - n'installe pas un thème depuis Internet
// - ne touche pas aux plugins ni aux contenus WordPress
// =============================================================================

defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    exit("Ce script doit être exécuté avec WP-CLI.\n");
}

require_once ABSPATH . 'wp-admin/includes/theme.php';

/**
 * Sélectionne un thème WordPress de secours parmi les thèmes installés.
 *
 * @param array<string, WP_Theme> $themes Liste des thèmes installés.
 * @param string $active Slug du thème actif.
 * @return string Slug du thème de secours, ou chaîne vide si aucun n'est disponible.
 */
function comkani_theme_cleanup_fallback(array $themes, string $active): string
{
    $candidates = array_keys(array_filter(
        $themes,
        static fn(WP_Theme $theme, string $slug): bool =>
            $slug !== $active && strpos($slug, 'twentytwenty') === 0,
        ARRAY_FILTER_USE_BOTH
    ));

    rsort($candidates, SORT_NATURAL);

    return $candidates[0] ?? '';
}

$apply = getenv('COMKANI_APPLY') === '1';
$active = get_stylesheet();
$template = get_template();
$themes = wp_get_themes();
$fallback = comkani_theme_cleanup_fallback(
    array_filter(
        $themes,
        static fn(WP_Theme $theme, string $slug): bool => $slug !== $template || $template === $active,
        ARRAY_FILTER_USE_BOTH
    ),
    $active
);

WP_CLI::log("Thème actif conservé : {$active}");
if ($template !== $active) {
    WP_CLI::log("Thème parent conservé : {$template}");
}

if ($fallback === '') {
    WP_CLI::error('Aucun thème WordPress Twenty Twenty de secours n’est installé. Aucun thème ne sera supprimé.');
}

WP_CLI::log("Thème de secours conservé : {$fallback}");

$targets = array_values(array_filter(
    array_keys($themes),
    static fn(string $slug): bool => $slug !== $active && $slug !== $template && $slug !== $fallback
));

if ($targets === []) {
    WP_CLI::success('Aucun ancien thème à supprimer.');
    return;
}

foreach ($targets as $slug) {
    if (!$apply) {
        WP_CLI::log("À supprimer : {$slug}");
        continue;
    }

    $result = delete_theme($slug);
    if (is_wp_error($result)) {
        WP_CLI::warning("Impossible de supprimer {$slug} : " . $result->get_error_message());
        continue;
    }

    delete_option('theme_mods_' . $slug);
    WP_CLI::log("Supprimé : {$slug}");
}

WP_CLI::success($apply
    ? 'Nettoyage des thèmes terminé.'
    : 'Contrôle des thèmes terminé. Relancer en mode application pour supprimer les thèmes listés.');
