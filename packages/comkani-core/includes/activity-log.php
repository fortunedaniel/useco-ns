<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/activity-log.php
// Rôle : journalise les actions utiles réalisées dans le back-office ComKani
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - journalise uniquement les actions humaines utiles
// - identifie le type de contenu, la page / ressource et les champs modifiés
// - conserve un snapshot avant modification pour permettre un retour arrière
// - permet la restauration uniquement aux comptes agence autorisés
// - force la journalisation des restaurations, même pour les comptes agence exclus
// - permet à l’agence de remettre le journal à zéro à chaque jalon de livraison
// - conserve la date et l’auteur de la dernière remise à zéro hors du journal
//
// Ce qu'il ne fait pas :
// - ne journalise pas les autosaves, révisions et brouillons automatiques
// - ne donne jamais au client la possibilité de vider le journal
// - ne supprime aucun contenu WordPress lors d'une remise à zéro
// Fichiers liés :
// - voir les imports, appels et chemins référencés directement dans ce fichier
//
// =============================================================================
defined('ABSPATH') || exit;

if (!defined('COMKANI_ACTIVITY_DB_VERSION')) {
    define('COMKANI_ACTIVITY_DB_VERSION', '1.1.0');
}

/**
 * Activity table name : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_table_name(): string
{
    global $wpdb;

    return $wpdb->prefix . 'comkani_activity_log';
}

/**
 * Activity install : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_install(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = comkani_activity_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        username varchar(60) NOT NULL DEFAULT '',
        action varchar(80) NOT NULL,
        object_type varchar(40) NOT NULL DEFAULT '',
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        object_label varchar(190) NOT NULL DEFAULT '',
        details longtext NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY action (action),
        KEY object_type (object_type),
        KEY object_id (object_id),
        KEY created_at (created_at)
    ) {$charset};";

    dbDelta($sql);
    update_option('comkani_activity_db_version', COMKANI_ACTIVITY_DB_VERSION, false);
}

/**
 * Activity agency logins : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_agency_logins(): array
{
    $logins = comkani_core_get('activityLog.excludedUsers', []);

    if (!is_array($logins)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map(
        static fn($login): string => strtolower(trim((string) $login)),
        $logins
    ))));
}

/**
 * Activity is agency user : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_is_agency_user(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }

    $user = get_userdata($user_id);

    if (!$user instanceof WP_User) {
        return false;
    }

    return in_array(
        strtolower((string) $user->user_login),
        comkani_activity_agency_logins(),
        true
    );
}

/**
 * Activity is excluded user : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_is_excluded_user(int $user_id): bool
{
    return comkani_activity_is_agency_user($user_id);
}

/**
 * Activity can restore : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_can_restore(): bool
{
    $user_id = get_current_user_id();

    return $user_id > 0
        && current_user_can('comkani_restore_activity')
        && current_user_can('manage_options')
        && comkani_activity_is_agency_user($user_id);
}

/**
 * Indique si l'utilisateur courant peut remettre le journal à zéro.
 *
 * La même protection que pour la restauration est utilisée : la capacité
 * technique ne suffit pas, le compte doit également être identifié comme
 * compte agence dans la configuration du projet.
 *
 * @return bool true uniquement pour un administrateur agence autorisé.
 */
function comkani_activity_can_clear(): bool
{
    return comkani_activity_can_restore();
}

/**
 * Activity log : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log(
    string $action,
    string $object_type = '',
    int $object_id = 0,
    string $object_label = '',
    array $details = [],
    ?int $user_id = null,
    bool $force = false
): void {
    global $wpdb;

    $user_id = $user_id ?? get_current_user_id();

    // Le journal doit représenter les actions humaines. Les actions système
    // sont ignorées, sauf événement explicitement forcé (ex. restauration).
    if (!$force && $user_id <= 0) {
        return;
    }

    if (!$force && $user_id > 0 && comkani_activity_is_excluded_user($user_id)) {
        return;
    }

    $user = $user_id > 0 ? get_userdata($user_id) : null;
    $username = $user instanceof WP_User ? (string) $user->user_login : 'system';

    $wpdb->insert(
        comkani_activity_table_name(),
        [
            'user_id' => max(0, $user_id),
            'username' => $username,
            'action' => sanitize_key($action),
            'object_type' => sanitize_key($object_type),
            'object_id' => max(0, $object_id),
            'object_label' => sanitize_text_field($object_label),
            'details' => $details === []
                ? null
                : wp_json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => current_time('mysql', true),
        ],
        ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
    );
}

/**
 * Activity log login : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log_login(string $user_login, WP_User $user): void
{
    comkani_activity_log('login', 'user', (int) $user->ID, $user_login, [], (int) $user->ID);
}
add_action('wp_login', 'comkani_activity_log_login', 10, 2);

/**
 * Activity log logout : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log_logout(): void
{
    $user_id = get_current_user_id();

    if ($user_id <= 0) {
        return;
    }

    $user = get_userdata($user_id);
    comkani_activity_log(
        'logout',
        'user',
        $user_id,
        $user instanceof WP_User ? (string) $user->user_login : '',
        [],
        $user_id
    );
}
add_action('wp_logout', 'comkani_activity_log_logout');

/**
 * Activity supported post type : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_supported_post_type(string $post_type): bool
{
    return in_array($post_type, ['page', 'post', 'comkani_faq', 'comkani_client', 'comkani_step'], true);
}

/**
 * Indique si une métadonnée appartient au périmètre restaurable ComKani.
 *
 * @param string $key Clé de métadonnée WordPress.
 * @return bool true lorsque la valeur doit être incluse dans le snapshot.
 */
function comkani_activity_tracks_meta_key(string $key): bool
{
    return $key === '_thumbnail_id'
        || str_starts_with($key, '_comkani_page_')
        || in_array(
            $key,
            [
                '_comkani_client_url',
                '_comkani_step_text',
                '_comkani_shared_clients',
                '_comkani_shared_steps',
            ],
            true
        );
}

/**
 * Activity snapshot : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_snapshot(int $post_id): array
{
    $post = get_post($post_id);

    if (!$post instanceof WP_Post || !comkani_activity_supported_post_type($post->post_type)) {
        return [];
    }

    $all_meta = get_post_meta($post_id);
    $meta = [];

    foreach ($all_meta as $key => $values) {
        if (comkani_activity_tracks_meta_key((string) $key)) {
            $meta[$key] = array_map('maybe_unserialize', (array) $values);
        }
    }

    $taxonomies = [];

    foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
        $term_ids = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);

        if (!is_wp_error($term_ids)) {
            $taxonomies[$taxonomy] = array_values(array_map('intval', $term_ids));
        }
    }

    return [
        'post' => [
            'post_title' => (string) $post->post_title,
            'post_content' => (string) $post->post_content,
            'post_excerpt' => (string) $post->post_excerpt,
            'post_status' => (string) $post->post_status,
            'post_name' => (string) $post->post_name,
        ],
        'meta' => $meta,
        'taxonomies' => $taxonomies,
    ];
}

/**
 * Activity snapshot changed fields : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_snapshot_changed_fields(array $before, array $after): array
{
    $changes = [];

    $labels = [
        'post_title' => 'Titre',
        'post_content' => 'Contenu',
        'post_excerpt' => 'Extrait',
        'post_status' => 'Statut',
        'post_name' => 'Permalien',
    ];

    foreach ($labels as $key => $label) {
        if (($before['post'][$key] ?? null) !== ($after['post'][$key] ?? null)) {
            $changes[] = $label;
        }
    }

    $before_meta = (array) ($before['meta'] ?? []);
    $after_meta = (array) ($after['meta'] ?? []);
    $meta_keys = array_unique(array_merge(array_keys($before_meta), array_keys($after_meta)));

    foreach ($meta_keys as $key) {
        if (maybe_serialize($before_meta[$key] ?? null) === maybe_serialize($after_meta[$key] ?? null)) {
            continue;
        }

        $meta_labels = [
            '_thumbnail_id' => 'Image mise en avant',
            '_comkani_client_url' => 'URL du client',
            '_comkani_step_text' => 'Texte de l’étape',
            '_comkani_shared_clients' => 'Clients affichés',
            '_comkani_shared_steps' => 'Étapes affichées',
        ];

        if (isset($meta_labels[$key])) {
            $changes[] = $meta_labels[$key];
            continue;
        }

        $field = preg_replace('/^_comkani_page_[^_]+_/', '', (string) $key);
        $field = str_replace('_', ' ', (string) $field);
        $changes[] = 'Champ ComKani : ' . $field;
    }

    if (maybe_serialize($before['taxonomies'] ?? []) !== maybe_serialize($after['taxonomies'] ?? [])) {
        $changes[] = 'Catégories / taxonomies';
    }

    return array_values(array_unique($changes));
}

$GLOBALS['comkani_activity_before_update'] = [];

/**
 * Activity capture before update : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_capture_before_update(int $post_id, array $data): void
{
    $post = get_post($post_id);

    if (!$post instanceof WP_Post || !comkani_activity_supported_post_type($post->post_type)) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status === 'auto-draft') {
        return;
    }

    $GLOBALS['comkani_activity_before_update'][$post_id] = comkani_activity_snapshot($post_id);
}
add_action('pre_post_update', 'comkani_activity_capture_before_update', 10, 2);

/**
 * Activity log post save : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log_post_save(int $post_id, WP_Post $post, bool $update): void
{
    if (
        wp_is_post_revision($post_id)
        || wp_is_post_autosave($post_id)
        || !comkani_activity_supported_post_type($post->post_type)
        || $post->post_status === 'auto-draft'
    ) {
        return;
    }

    if (!$update) {
        comkani_activity_log(
            'content_created',
            $post->post_type,
            $post_id,
            get_the_title($post_id),
            ['status' => (string) $post->post_status]
        );
        return;
    }

    $before = $GLOBALS['comkani_activity_before_update'][$post_id] ?? [];
    unset($GLOBALS['comkani_activity_before_update'][$post_id]);

    if ($before === []) {
        return;
    }

    $after = comkani_activity_snapshot($post_id);
    $changed_fields = comkani_activity_snapshot_changed_fields($before, $after);

    if ($changed_fields === []) {
        return;
    }

    comkani_activity_log(
        'content_updated',
        $post->post_type,
        $post_id,
        get_the_title($post_id),
        [
            'changed_fields' => $changed_fields,
            'before' => $before,
        ]
    );
}
add_action('save_post', 'comkani_activity_log_post_save', 200, 3);

/**
 * Activity log post trash : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log_post_trash(int $post_id): void
{
    $post = get_post($post_id);

    if (!$post instanceof WP_Post || !comkani_activity_supported_post_type($post->post_type) || $post->post_status === 'auto-draft') {
        return;
    }

    comkani_activity_log('content_trashed', $post->post_type, $post_id, get_the_title($post_id));
}
add_action('wp_trash_post', 'comkani_activity_log_post_trash');

/**
 * Activity log post delete : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log_post_delete(int $post_id): void
{
    $post = get_post($post_id);

    if (!$post instanceof WP_Post || !comkani_activity_supported_post_type($post->post_type) || $post->post_status === 'auto-draft') {
        return;
    }

    comkani_activity_log('content_deleted', $post->post_type, $post_id, get_the_title($post_id));
}
add_action('before_delete_post', 'comkani_activity_log_post_delete');

/**
 * Activity log attachment : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log_attachment(int $post_id, string $action): void
{
    $attachment = get_post($post_id);

    comkani_activity_log(
        $action,
        'media',
        $post_id,
        $attachment instanceof WP_Post ? (string) $attachment->post_title : ''
    );
}

add_action('add_attachment', static function (int $post_id): void {
    comkani_activity_log_attachment($post_id, 'media_added');
});
add_action('edit_attachment', static function (int $post_id): void {
    comkani_activity_log_attachment($post_id, 'media_updated');
});
add_action('delete_attachment', static function (int $post_id): void {
    comkani_activity_log_attachment($post_id, 'media_deleted');
});

/**
 * Activity log profile update : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log_profile_update(int $user_id, WP_User $old_user_data): void
{
    $user = get_userdata($user_id);

    if (!$user instanceof WP_User) {
        return;
    }

    $changes = [];

    if ((string) $old_user_data->user_email !== (string) $user->user_email) {
        $changes[] = 'Adresse e-mail';
    }

    if ($changes === []) {
        return;
    }

    comkani_activity_log(
        'user_updated',
        'user',
        $user_id,
        (string) $user->user_login,
        ['changed_fields' => $changes]
    );
}
add_action('profile_update', 'comkani_activity_log_profile_update', 100, 2);

/**
 * Activity log role change : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_log_role_change(int $user_id, string $role, array $old_roles): void
{
    $user = get_userdata($user_id);

    comkani_activity_log(
        'user_role_changed',
        'user',
        $user_id,
        $user instanceof WP_User ? (string) $user->user_login : '',
        [
            'old_roles' => array_values(array_map('sanitize_key', $old_roles)),
            'new_role' => sanitize_key($role),
        ]
    );
}
add_action('set_user_role', 'comkani_activity_log_role_change', 100, 3);

/**
 * Activity action label : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_action_label(string $action): string
{
    $labels = [
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'content_created' => 'Contenu créé',
        'content_updated' => 'Contenu modifié',
        'content_trashed' => 'Contenu placé dans la corbeille',
        'content_deleted' => 'Contenu supprimé',
        'content_restored' => 'Version restaurée',
        'content_untrashed' => 'Contenu restauré depuis la corbeille',
        'media_added' => 'Média ajouté',
        'media_updated' => 'Média modifié',
        'media_deleted' => 'Média supprimé',
        'user_updated' => 'Compte modifié',
        'user_role_changed' => 'Rôle modifié',
        'request_updated' => 'Demande modifiée',
    ];

    return $labels[$action] ?? $action;
}

/**
 * Activity object type label : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_object_type_label(string $type): string
{
    $labels = [
        'page' => 'Page',
        'post' => 'Ressource',
        'comkani_faq' => 'FAQ',
        'comkani_client' => 'Client',
        'comkani_step' => 'Étape',
        'media' => 'Média',
        'user' => 'Compte',
        'request' => 'Demande',
    ];

    return $labels[$type] ?? ucfirst($type);
}

/**
 * Activity restore snapshot : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_restore_snapshot(int $post_id, array $snapshot): bool
{
    $post = get_post($post_id);

    if (!$post instanceof WP_Post || $snapshot === []) {
        return false;
    }

    $post_data = (array) ($snapshot['post'] ?? []);
    $post_data['ID'] = $post_id;

    $updated = wp_update_post(wp_slash($post_data), true);

    if (is_wp_error($updated)) {
        return false;
    }

    $current_meta = get_post_meta($post_id);
    $snapshot_meta = (array) ($snapshot['meta'] ?? []);

    foreach (array_keys($current_meta) as $key) {
        if (comkani_activity_tracks_meta_key((string) $key)) {
            if (!array_key_exists($key, $snapshot_meta)) {
                delete_post_meta($post_id, $key);
            }
        }
    }

    foreach ($snapshot_meta as $key => $values) {
        delete_post_meta($post_id, (string) $key);

        foreach ((array) $values as $value) {
            add_post_meta($post_id, (string) $key, $value);
        }
    }

    foreach ((array) ($snapshot['taxonomies'] ?? []) as $taxonomy => $term_ids) {
        if (taxonomy_exists((string) $taxonomy)) {
            wp_set_object_terms(
                $post_id,
                array_values(array_map('intval', (array) $term_ids)),
                (string) $taxonomy,
                false
            );
        }
    }

    clean_post_cache($post_id);

    return true;
}

/**
 * Activity handle restore : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_handle_restore(): void
{
    if (!comkani_activity_can_restore()) {
        wp_die('Cette restauration est réservée aux administrateurs de l’Agence ComKani.');
    }

    $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;
    check_admin_referer('comkani_activity_restore_' . $activity_id);

    global $wpdb;

    $table = comkani_activity_table_name();
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $activity_id)
    );

    if (!$row || (string) $row->action !== 'content_updated' || (int) $row->object_id <= 0) {
        wp_die('Cette entrée ne peut pas être restaurée.');
    }

    $details = json_decode((string) $row->details, true);
    $before = is_array($details) && isset($details['before']) && is_array($details['before'])
        ? $details['before']
        : [];

    if ($before === []) {
        wp_die('Aucune version précédente n’est disponible pour cette entrée.');
    }

    $post_id = (int) $row->object_id;
    $current_snapshot = comkani_activity_snapshot($post_id);

    if (!comkani_activity_restore_snapshot($post_id, $before)) {
        wp_die('La restauration n’a pas pu être appliquée.');
    }

    comkani_activity_log(
        'content_restored',
        (string) $row->object_type,
        $post_id,
        (string) $row->object_label,
        [
            'restored_activity_id' => $activity_id,
            'before_restore' => $current_snapshot,
        ],
        get_current_user_id(),
        true
    );

    wp_safe_redirect(add_query_arg(
        [
            'page' => 'comkani-activity',
            'comkani_restored' => 1,
        ],
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_comkani_activity_restore', 'comkani_activity_handle_restore');

/**
 * Restaure un contenu encore présent dans la corbeille WordPress.
 *
 * Une suppression définitive n'est pas récupérable par ce mécanisme.
 * L'opération est réservée aux administrateurs agence habilités, avec nonce
 * et vérification du droit WordPress sur le contenu cible.
 *
 * @return void Redirige après succès ou interrompt la requête en cas d'erreur.
 */
function comkani_activity_handle_untrash(): void
{
    if (!comkani_activity_can_restore()) {
        wp_die('Cette restauration est réservée aux administrateurs de l’Agence ComKani.');
    }

    $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0;
    check_admin_referer('comkani_activity_untrash_' . $activity_id);

    global $wpdb;
    $table = comkani_activity_table_name();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $activity_id
    ));

    if (!$row || (string) $row->action !== 'content_trashed' || (int) $row->object_id <= 0) {
        wp_die('Cette entrée ne correspond pas à un contenu placé dans la corbeille.');
    }

    $post_id = (int) $row->object_id;
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_status !== 'trash') {
        wp_die('Le contenu n’est plus dans la corbeille. Il est peut-être déjà restauré ou définitivement supprimé.');
    }
    if (!current_user_can('edit_post', $post_id) || !current_user_can('delete_post', $post_id)) {
        wp_die('Vous n’avez pas les droits nécessaires sur ce contenu.');
    }

    if (!wp_untrash_post($post_id)) {
        wp_die('WordPress n’a pas pu restaurer ce contenu.');
    }

    comkani_activity_log(
        'content_untrashed',
        (string) $row->object_type,
        $post_id,
        (string) $row->object_label,
        ['restored_activity_id' => $activity_id],
        get_current_user_id(),
        true
    );

    wp_safe_redirect(add_query_arg(
        ['page' => 'comkani-activity', 'comkani_untrashed' => 1],
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_comkani_activity_untrash', 'comkani_activity_handle_untrash');

/**
 * Refuse les anciennes requêtes de remise à zéro du journal.
 *
 * Le point d'entrée demeure enregistré pour neutraliser les anciens
 * formulaires et liens. Aucun historique n'est effacé.
 *
 * @return void Interrompt la requête.
 */
function comkani_activity_handle_clear(): void
{
    wp_die('La remise à zéro destructive du journal a été désactivée. Utilisez les filtres de la page Activité.');
}
add_action('admin_post_comkani_activity_clear', 'comkani_activity_handle_clear');

/**
 * Activity render admin page : fonction du module activity-log.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_activity_render_admin_page(): void
{
    if (!current_user_can('comkani_view_activity_log')) {
        wp_die('Vous n’avez pas l’autorisation d’accéder à cette page.');
    }

    global $wpdb;

    $table = comkani_activity_table_name();
    $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // Les valeurs de filtre ne sont jamais interpolées dans la requête SQL.
    $allowed_actions = [
        'login', 'logout', 'content_created', 'content_updated',
        'content_trashed', 'content_deleted', 'content_restored',
        'content_untrashed', 'media_added', 'media_updated', 'media_deleted',
        'user_updated', 'user_role_changed', 'request_updated',
    ];
    $selected_action = isset($_GET['activity_action']) && is_string($_GET['activity_action'])
        ? sanitize_key(wp_unslash($_GET['activity_action']))
        : '';
    if (!in_array($selected_action, $allowed_actions, true)) {
        $selected_action = '';
    }
    $from = isset($_GET['activity_from']) && is_string($_GET['activity_from'])
        ? sanitize_text_field(wp_unslash($_GET['activity_from']))
        : '';
    $to = isset($_GET['activity_to']) && is_string($_GET['activity_to'])
        ? sanitize_text_field(wp_unslash($_GET['activity_to']))
        : '';
    foreach (['from', 'to'] as $field) {
        $date = $$field;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $$field = '';
        }
    }

    $where = [];
    $params = [];
    if ($selected_action !== '') {
        $where[] = 'action = %s';
        $params[] = $selected_action;
    }
    // created_at est enregistré en GMT ; convertir les bornes locales en GMT.
    $tz = wp_timezone();
    if ($from !== '') {
        $start_date = new DateTimeImmutable($from . ' 00:00:00', $tz);
        $where[] = 'created_at >= %s';
        $params[] = $start_date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    if ($to !== '') {
        $end_date = new DateTimeImmutable($to . ' 23:59:59', $tz);
        $where[] = 'created_at <= %s';
        $params[] = $end_date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    $where_sql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
    $count_sql = "SELECT COUNT(*) FROM {$table}{$where_sql}";
    $total = (int) $wpdb->get_var($params !== [] ? $wpdb->prepare($count_sql, $params) : $count_sql);
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}{$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
        ...array_merge($params, [$per_page, $offset])
    ));

    echo '<div class="wrap comkani-admin-page">';
    echo '<h1>Journal des activités réalisées sur le site</h1>';
    echo '<p>Historique conservé sans remise à zéro destructive. La restauration est réservée aux comptes agence autorisés.</p>';
    echo '<form method="get" class="comkani-activity-toolbar">';
    echo '<input type="hidden" name="page" value="comkani-activity">';
    echo '<label for="comkani-activity-action">Action </label>';
    echo '<select id="comkani-activity-action" name="activity_action">';
    echo '<option value="">Toutes les actions</option>';
    foreach ($allowed_actions as $action) {
        echo '<option value="' . esc_attr($action) . '" ' . selected($selected_action, $action, false) . '>' . esc_html(comkani_activity_action_label($action)) . '</option>';
    }
    echo '</select> ';
    echo '<label for="comkani-activity-from">Du </label><input id="comkani-activity-from" type="date" name="activity_from" value="' . esc_attr($from) . '"> ';
    echo '<label for="comkani-activity-to">Au </label><input id="comkani-activity-to" type="date" name="activity_to" value="' . esc_attr($to) . '"> ';
    echo '<button type="submit" class="button button-primary">Filtrer</button> ';
    echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=comkani-activity')) . '">Réinitialiser les filtres</a>';
    echo '</form>';

    if (!empty($_GET['comkani_restored'])) {
        echo '<div class="notice notice-success is-dismissible"><p>La version précédente a été restaurée.</p></div>';
    }
    if (!empty($_GET['comkani_untrashed'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Le contenu a été restauré depuis la corbeille.</p></div>';
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Type</th><th>Élément</th><th>Détails</th><th>Retour arrière</th></tr></thead><tbody>';

    if ($rows === []) {
        echo '<tr><td colspan="7">Aucune activité enregistrée.</td></tr>';
    }

    foreach ($rows as $row) {
        $local_date = get_date_from_gmt((string) $row->created_at, 'd/m/Y à H:i:s');
        $object = trim((string) $row->object_label);
        $object = $object !== '' ? $object : (string) $row->object_type;
        $details = json_decode((string) $row->details, true);
        $details = is_array($details) ? $details : [];
        $changed_fields = isset($details['changed_fields']) && is_array($details['changed_fields'])
            ? $details['changed_fields']
            : [];

        $edit_link = '';
        if ((int) $row->object_id > 0 && in_array((string) $row->object_type, ['page', 'post', 'comkani_faq', 'comkani_client', 'comkani_step'], true)) {
            $edit_link = get_edit_post_link((int) $row->object_id, 'raw') ?: '';
        }

        echo '<tr>';
        echo '<td>' . esc_html($local_date) . '</td>';
        echo '<td>' . esc_html((string) $row->username) . '</td>';
        echo '<td>' . esc_html(comkani_activity_action_label((string) $row->action)) . '</td>';
        echo '<td>' . esc_html(comkani_activity_object_type_label((string) $row->object_type)) . '</td>';
        echo '<td>';
        if ($edit_link !== '') {
            echo '<a href="' . esc_url($edit_link) . '">' . esc_html($object) . '</a>';
        } else {
            echo esc_html($object);
        }
        echo '</td>';
        echo '<td>' . esc_html($changed_fields !== [] ? implode(', ', $changed_fields) : '—') . '</td>';
        echo '<td>';

        if (
            comkani_activity_can_restore()
            && (string) $row->action === 'content_updated'
            && isset($details['before'])
            && is_array($details['before'])
        ) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Restaurer cette version ? L’état actuel restera tracé dans l’activité.\');">';
            echo '<input type="hidden" name="action" value="comkani_activity_restore">';
            echo '<input type="hidden" name="activity_id" value="' . esc_attr((string) $row->id) . '">';
            wp_nonce_field('comkani_activity_restore_' . (int) $row->id);
            echo '<button type="submit" class="button button-secondary">Restaurer</button>';
            echo '</form>';
        } elseif (
            comkani_activity_can_restore()
            && (string) $row->action === 'content_trashed'
            && ($trashed_post = get_post((int) $row->object_id)) instanceof WP_Post
            && $trashed_post->post_status === 'trash'
            && current_user_can('edit_post', (int) $row->object_id)
            && current_user_can('delete_post', (int) $row->object_id)
        ) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Restaurer ce contenu depuis la corbeille ?\');">';
            echo '<input type="hidden" name="action" value="comkani_activity_untrash">';
            echo '<input type="hidden" name="activity_id" value="' . esc_attr((string) $row->id) . '">';
            wp_nonce_field('comkani_activity_untrash_' . (int) $row->id);
            echo '<button type="submit" class="button button-secondary">Restaurer depuis la corbeille</button>';
            echo '</form>';
        } else {
            echo '—';
        }

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    $pages = (int) ceil($total / $per_page);

    if ($pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo wp_kses_post(paginate_links([
            'base' => add_query_arg(['paged' => '%#%', 'activity_action' => $selected_action, 'activity_from' => $from, 'activity_to' => $to]),
            'current' => $page,
            'total' => $pages,
        ]));
        echo '</div></div>';
    }

    echo '</div>';
}
