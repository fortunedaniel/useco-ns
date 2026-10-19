<?php

// =============================================================================
// Fichier : inc/assets.php
// Rôle : charge les fichiers CSS et JavaScript compilés du thème
//
// Objectif :
// - initialiser ou relier une responsabilité technique du thème WordPress
// - conserver une architecture compatible avec un hébergement WordPress classique
//
// Ce qu'il fait :
// - charge les polices Fontshare déclarées dans config/src/project.ts
// - charge assets/build/main.css lorsqu'il existe
// - charge assets/build/main.js avec defer
// - versionne les fichiers avec leur date de modification
// - ne charge aucun outil Node ou Vite en production
//
// Ce qu'il ne fait pas :
// - ne rend pas Node.js nécessaire au fonctionnement du thème en production
// - ne doit pas contenir de logique éditoriale spécifique à une seule page lorsqu’un composant suffit
//
// Fichiers liés :
// - config/src/project.ts
// - resources/ts/main.js
// - resources/scss/style.scss
// - vite.config.js
// - assets/build/main.css
// - assets/build/main.js
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Asset version : fonction du module assets.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_asset_version(string $relative_path): string
{
    $path = get_template_directory() . '/' . ltrim($relative_path, '/');

    return is_file($path)
        ? (string) filemtime($path)
        : '1.0.0';
}

// Charge les polices Fontshare configurées pour le projet
/**
 * Enqueue fontshare fonts : fonction du module assets.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_enqueue_fontshare_fonts(): array
{
    $dependencies = [];

    foreach (['primary', 'secondary', 'tertiary'] as $role) {
        $source = strtolower(trim(
            (string) comkani_project_get(
                "designSystem.typography.{$role}.source",
                ''
            )
        ));

        if ($source !== 'fontshare') {
            continue;
        }

        $family = trim(
            (string) comkani_project_get(
                "designSystem.typography.{$role}.family",
                ''
            )
        );

        $weights = array_values(array_unique(array_filter(
            array_map(
                'intval',
                (array) comkani_project_get(
                    "designSystem.typography.{$role}.weights",
                    []
                )
            ),
            static fn(int $weight): bool => (
                $weight >= 100
                && $weight <= 900
            )
        )));

        if ($family === '' || $weights === []) {
            continue;
        }

        sort($weights, SORT_NUMERIC);

        $family_slug = sanitize_title($family);
        $handle = 'comkani-fontshare-' . $role;

        $url = sprintf(
            'https://api.fontshare.com/v2/css?f[]=%s@%s&display=swap',
            rawurlencode($family_slug),
            implode(',', $weights)
        );

        wp_enqueue_style(
            $handle,
            esc_url_raw($url),
            [],
            null
        );

        $dependencies[] = $handle;
    }

    return $dependencies;
}

// Charge les assets compilés du thème
/**
 * Enqueue assets : fonction du module assets.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_enqueue_assets(): void
{
    if (is_admin()) {
        return;
    }

    $css = 'assets/build/main.css';
    $js = 'assets/build/main.js';

    $style_dependencies = comkani_enqueue_fontshare_fonts();

    if (is_file(get_template_directory() . '/' . $css)) {
        wp_enqueue_style(
            'comkani-main',
            get_template_directory_uri() . '/' . $css,
            $style_dependencies,
            comkani_asset_version($css)
        );
    }

    if (is_file(get_template_directory() . '/' . $js)) {
        wp_enqueue_script(
            'comkani-main',
            get_template_directory_uri() . '/' . $js,
            [],
            comkani_asset_version($js),
            [
                'strategy' => 'defer',
                'in_footer' => true,
            ]
        );

        wp_localize_script('comkani-main', 'ComKaniProject', [
            'restUrl' => esc_url_raw(rest_url('comkani/v1/')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'booking' => [
                'availableWeekdays' => array_values(array_map(
                    'intval',
                    (array) comkani_project_get(
                        'booking.availableWeekdays',
                        []
                    )
                )),
            ],
        ]);
    }
}

add_action(
    'wp_enqueue_scripts',
    'comkani_enqueue_assets',
    20
);
