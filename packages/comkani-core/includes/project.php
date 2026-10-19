<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/project.php
// Rôle : permet au ComKani Core de lire la configuration du projet
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - réutilise comkani_project_get() lorsque le thème l'expose
// - fournit un secours sûr si le Core est chargé avant le thème
// - évite de dupliquer la logique de chargement de la configuration compilée
//
// Ce qu'il ne fait pas :
// - ne dépend pas de Node.js ou Vite en production
// - ne contient pas de contenu client codé en dur sauf valeur de repli explicitement prévue
//
// Fichiers liés :
// - inc/project.php
// - config/generated/project-config.php
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Core get : fonction du module project.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_core_get(string $path, $default = null)
{
    if (function_exists('comkani_project_get')) {
        return comkani_project_get($path, $default);
    }

    static $config = null;

    if (!is_array($config)) {
        $config = [];
        $path_to_config = get_template_directory() . '/config/generated/project-config.php';

        if (is_readable($path_to_config)) {
            $loaded = require $path_to_config;

            if (is_array($loaded)) {
                $config = $loaded;
            }
        }
    }

    $value = $config;

    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

/**
 * Core component enabled : fonction du module project.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_core_component_enabled(string $component): bool
{
    return (bool) comkani_core_get('components.' . $component, false);
}
