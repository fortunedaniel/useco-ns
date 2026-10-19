<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/capabilities.php
// Rôle : définit les rôles et permissions du back-office ComKani
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - ajoute les capacités métier aux administrateurs
// - crée le rôle Gestionnaire du site pour les clients
// - attribue les capacités FAQ, contenus partagés, demandes et cache
// - évite toute sécurité basée sur un nom d'utilisateur codé en dur
//
// Ce qu'il ne fait pas :
// - ne dépend pas de Node.js ou Vite en production
// - ne contient pas de contenu client codé en dur sauf valeur de repli explicitement prévue
//
// Fichiers liés :
// - config/security.json
// - packages/comkani-core/includes/admin.php
// - packages/comkani-core/includes/requests.php
// - packages/comkani-core/includes/faq.php
// - packages/comkani-core/includes/shared-content.php
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Core capabilities : fonction du module capabilities.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_core_capabilities(): array
{
    return [
        'comkani_manage_project',
        'comkani_manage_security',
        'comkani_manage_cache',
        'comkani_manage_requests',
        'comkani_manage_contacts',
        'comkani_manage_quotes',
        'comkani_manage_bookings',
        'comkani_manage_applications',
        'comkani_manage_faq',
        'comkani_manage_shared_content',
        'comkani_manage_notification_bar',
        'comkani_view_activity_log',
        'comkani_restore_activity',
    ];
}

/**
 * Core content caps : fonction du module capabilities.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_core_content_caps(): array
{
    return [
        'read' => true,
        'upload_files' => true,
        'edit_posts' => true,
        'edit_others_posts' => true,
        'edit_published_posts' => true,
        'edit_private_posts' => true,
        'read_private_posts' => true,
        'publish_posts' => true,
        'delete_posts' => true,
        'delete_others_posts' => true,
        'delete_published_posts' => true,
        'delete_private_posts' => true,
        'edit_pages' => true,
        'edit_others_pages' => true,
        'edit_published_pages' => true,
        'edit_private_pages' => true,
        'read_private_pages' => true,
        'publish_pages' => true,
        'delete_pages' => true,
        'delete_others_pages' => true,
        'delete_published_pages' => true,
        'delete_private_pages' => true,
        'manage_categories' => true,
        'comkani_manage_cache' => true,
        'comkani_manage_contacts' => true,
        'comkani_manage_quotes' => true,
        'comkani_manage_bookings' => true,
        'comkani_manage_applications' => true,
        'comkani_manage_faq' => true,
        'comkani_manage_shared_content' => true,
        'comkani_manage_notification_bar' => true,
        'comkani_manage_requests' => true,
    ];
}

/**
 * Core sync role : fonction du module capabilities.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_core_sync_role(string $role_key, string $label, array $caps): void
{
    $role = get_role($role_key);

    if (!$role) {
        add_role($role_key, $label, $caps);
        $role = get_role($role_key);
    }

    if (!$role) {
        return;
    }

    // Synchronise exactement les rôles ComKani afin qu'une ancienne
    // permission ne reste pas active après une évolution du Starter.
    foreach (array_keys((array) $role->capabilities) as $existing_capability) {
        if (!array_key_exists($existing_capability, $caps)) {
            $role->remove_cap($existing_capability);
        }
    }

    foreach ($caps as $capability => $granted) {
        $role->add_cap($capability, (bool) $granted);
    }
}

/**
 * Core install roles : fonction du module capabilities.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_core_install_roles(): void
{
    $administrator = get_role('administrator');

    if ($administrator) {
        foreach (comkani_core_capabilities() as $capability) {
            $administrator->add_cap($capability);
        }
    }

    $manager_caps = comkani_core_content_caps();

    if ((bool) comkani_core_get('roles.clientManagerCanManageMenus', false)) {
        $manager_caps['edit_theme_options'] = true;
    }

    comkani_core_sync_role(
        'comkani_client_manager',
        'Gestionnaire du site',
        $manager_caps
    );

    $manager = get_role('comkani_client_manager');

    if ($manager && !(bool) comkani_core_get('roles.clientManagerCanManageMenus', false)) {
        $manager->remove_cap('edit_theme_options');
    }

    $collaborator_caps = comkani_core_content_caps();
    $collaborator_caps['comkani_team_member'] = true;

    comkani_core_sync_role(
        'comkani_collaborator',
        'Collaborateur',
        $collaborator_caps
    );
}

/**
 * Core limit editable roles : fonction du module capabilities.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_core_limit_editable_roles(array $roles): array
{
    if (!current_user_can('administrator') && !current_user_can('manage_options')) {
        return $roles;
    }

    $allowed = [
        'administrator',
        'comkani_client_manager',
        'comkani_collaborator',
    ];

    return array_intersect_key($roles, array_flip($allowed));
}

add_filter('editable_roles', 'comkani_core_limit_editable_roles', 100);
