<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/setup.php
// Rôle : initialise les données techniques du ComKani Core
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - installe ou met à jour la table des demandes
// - installe les rôles et capacités ComKani
// - mémorise la version du Core installée
// - fonctionne sans hook d'activation grâce au chargement MU-plugin
//
// Ce qu'il ne fait pas :
// - ne dépend pas de Node.js ou Vite en production
// - ne contient pas de contenu client codé en dur sauf valeur de repli explicitement prévue
//
// Fichiers liés :
// - packages/comkani-core/includes/requests.php
// - packages/comkani-core/includes/capabilities.php
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Initialise les tables et les capacites lors de la premiere installation ou d'un changement de version.
 *
 * La version installee est enregistree seulement apres les operations d'installation.
 * Ne modifie ni les contenus editoriaux ni les fichiers du theme.
 *
 * @return void
 */
function comkani_core_maybe_setup(): void
{
    $installed = (string) get_option('comkani_core_version', '');

    if ($installed === COMKANI_CORE_VERSION) {
        return;
    }

    comkani_requests_install();
    comkani_activity_install();
    comkani_core_install_roles();
    update_option('comkani_core_version', COMKANI_CORE_VERSION, false);
}

add_action('admin_init', 'comkani_core_maybe_setup', 5);
add_action('init', 'comkani_requests_maybe_install', 5);
