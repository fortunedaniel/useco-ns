<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/requests-privacy.php
// Rôle : intègre les demandes ComKani aux outils RGPD de WordPress
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - exporte les demandes associées à une adresse e-mail
// - efface les demandes associées à une adresse e-mail
// - nettoie automatiquement les demandes finalisées arrivées à rétention
// - ne stocke pas l'adresse IP dans la base des demandes
//
// Ce qu'il ne fait pas :
// - ne dépend pas de Node.js ou Vite en production
// - ne contient pas de contenu client codé en dur sauf valeur de repli explicitement prévue
//
// Fichiers liés :
// - packages/comkani-core/includes/requests.php
// - config/src/project.ts
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Privacy exporter : fonction du module requests-privacy.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_privacy_exporter(array $exporters): array
{
    $exporters['comkani-requests'] = [
        'exporter_friendly_name' => 'Demandes du site',
        'callback' => 'comkani_privacy_export_requests',
    ];

    return $exporters;
}

add_filter('wp_privacy_personal_data_exporters', 'comkani_privacy_exporter');

/**
 * Privacy export requests : fonction du module requests-privacy.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_privacy_export_requests(string $email, int $page = 1): array
{
    global $wpdb;

    $table = comkani_requests_table_name();
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE client_email = %s ORDER BY created_at DESC LIMIT 100 OFFSET %d",
            sanitize_email($email),
            max(0, ($page - 1) * 100)
        )
    );
    $data = [];

    foreach ((array) $items as $item) {
        $data[] = [
            'group_id' => 'comkani-requests',
            'group_label' => 'Demandes envoyées via le site',
            'item_id' => 'comkani-request-' . (int) $item->id,
            'data' => [
                ['name' => 'Type', 'value' => (string) $item->request_type],
                ['name' => 'Date', 'value' => (string) $item->created_at],
                ['name' => 'Nom', 'value' => (string) $item->client_name],
                ['name' => 'E-mail', 'value' => (string) $item->client_email],
                ['name' => 'Téléphone', 'value' => (string) $item->client_phone],
                ['name' => 'Message', 'value' => (string) $item->message],
            ],
        ];
    }

    return [
        'data' => $data,
        'done' => count($items) < 100,
    ];
}

/**
 * Privacy eraser : fonction du module requests-privacy.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_privacy_eraser(array $erasers): array
{
    $erasers['comkani-requests'] = [
        'eraser_friendly_name' => 'Demandes du site',
        'callback' => 'comkani_privacy_erase_requests',
    ];

    return $erasers;
}

add_filter('wp_privacy_personal_data_erasers', 'comkani_privacy_eraser');

/**
 * Privacy erase requests : fonction du module requests-privacy.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_privacy_erase_requests(string $email, int $page = 1): array
{
    global $wpdb;

    $table = comkani_requests_table_name();
    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE client_email = %s",
            sanitize_email($email)
        )
    );
    $removed = false;

    foreach ((array) $ids as $id) {
        if (comkani_request_delete((int) $id)) {
            $removed = true;
        }
    }

    return [
        'items_removed' => $removed,
        'items_retained' => false,
        'messages' => [],
        'done' => true,
    ];
}

/**
 * Requests schedule cleanup : fonction du module requests-privacy.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_schedule_cleanup(): void
{
    if (!wp_next_scheduled('comkani_requests_daily_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'comkani_requests_daily_cleanup');
    }
}

add_action('init', 'comkani_requests_schedule_cleanup');

/**
 * Requests cleanup : fonction du module requests-privacy.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_cleanup(): void
{
    global $wpdb;

    $days = max(30, (int) comkani_core_get('requests.retentionDays', 365));
    $before = current_datetime()->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
    $table = comkani_requests_table_name();
    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE updated_at < %s AND status IN ('closed', 'accepted', 'declined', 'completed', 'cancelled', 'archived')",
            $before
        )
    );

    foreach ((array) $ids as $id) {
        comkani_request_delete((int) $id);
    }
}

add_action('comkani_requests_daily_cleanup', 'comkani_requests_cleanup');
