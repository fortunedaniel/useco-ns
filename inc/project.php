<?php

// =============================================================================
// Fichier : inc/project.php
// Rôle : expose la configuration centrale du projet
//
// Objectif :
// - initialiser ou relier une responsabilité technique du thème WordPress
// - conserver une architecture compatible avec un hébergement WordPress classique
//
// Ce qu'il fait :
// - charge config/generated/project-config.php
// - met la configuration en cache pendant la requête
// - fournit des helpers pour lire les valeurs du projet
// - permet de surcharger la configuration avec un filtre WordPress
//
// Ce qu'il ne fait pas :
// - ne rend pas Node.js nécessaire au fonctionnement du thème en production
// - ne doit pas contenir de logique éditoriale spécifique à une seule page lorsqu’un composant suffit
//
// Fichiers liés :
// - config/generated/project-config.php
// - packages/comkani-core/includes/project.php
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Project config : fonction du module project.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_project_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $path = get_template_directory() . '/config/generated/project-config.php';
    $config = [];

    if (is_readable($path)) {
        $loaded = require $path;

        if (is_array($loaded)) {
            $config = $loaded;
        }
    }

    $config = apply_filters('comkani_project_config', $config);

    return $config;
}

/**
 * Project get : fonction du module project.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_project_get(string $path, $default = null)
{
    $value = comkani_project_config();

    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

/**
 * Component enabled : fonction du module project.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_component_enabled(string $component): bool
{
    return (bool) comkani_project_get('components.' . $component, false);
}
