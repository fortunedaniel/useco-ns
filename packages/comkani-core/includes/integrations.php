<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/integrations.php
// Rôle : détecte les intégrations tierces utiles au Starter
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - détecte Yoast SEO et Wordfence sans dépendre de leurs structures internes
// - fournit les états utilisés dans le dashboard technique ComKani
// - évite de modifier automatiquement des options privées susceptibles de changer
//
// Ce qu'il ne fait pas :
// - ne dépend pas de Node.js ou Vite en production
// - ne contient pas de contenu client codé en dur sauf valeur de repli explicitement prévue
//
// Fichiers liés :
// - packages/comkani-core/includes/admin.php
// - scripts/wp-setup.php
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Integration status : fonction du module integrations.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_integration_status(): array
{
    return [
        'yoast' => [
            'active' => defined('WPSEO_VERSION'),
            'label' => 'Yoast SEO',
        ],
        'wordfence' => [
            'active' => defined('WORDFENCE_VERSION') || class_exists('wordfence'),
            'label' => 'Wordfence',
        ],
        'wpvivid' => [
            'active' => defined('WPVIVID_PLUGIN_DIR') || class_exists('WPvivid'),
            'label' => 'WPvivid',
        ],
    ];
}
