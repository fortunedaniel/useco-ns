<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/admin.php
// Rôle : construit le back-office WordPress adapté aux clients ComKani
//
// Ce qu'il fait :
// - applique l'identité visuelle bleue de l'Agence ComKani
// - ajoute les widgets Contact, Devis et Booking au dashboard
// - crée l'administration des demandes avec compteurs et statuts
// - masque les écrans techniques aux utilisateurs sans permission
// - simplifie la barre d'administration et retire les aides techniques
// - applique l'ordre du menu latéral défini dans config/security.json
// - ajoute un écran État du site réservé à l'Agence ComKani
// - ajoute un écran Cache accessible selon une permission dédiée
// - modifier le libellé des 'Articles'
//
// Fichiers liés :
// - packages/comkani-core/assets/admin.css
// - packages/comkani-core/assets/login.css
// - packages/comkani-core/includes/capabilities.php
// - packages/comkani-core/includes/requests.php
// - packages/comkani-core/includes/integrations.php
// - packages/comkani-core/includes/cache.php
// - config/security.json
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Admin enqueue assets : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_enqueue_assets(): void
{
    wp_enqueue_style(
        'comkani-admin',
        comkani_core_url('assets/admin.css'),
        [],
        comkani_core_asset_version('assets/admin.css')
    );

    wp_enqueue_style(
        'comkani-toolbar',
        comkani_core_url('assets/toolbar.css'),
        [],
        comkani_core_asset_version('assets/toolbar.css')
    );
}

add_action('admin_enqueue_scripts', 'comkani_admin_enqueue_assets');


/**
 * Toolbar enqueue front asset : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_toolbar_enqueue_front_asset(): void
{
    if (!is_user_logged_in() || !is_admin_bar_showing()) {
        return;
    }

    wp_enqueue_style(
        'comkani-toolbar',
        comkani_core_url('assets/toolbar.css'),
        [],
        comkani_core_asset_version('assets/toolbar.css')
    );
}

add_action('wp_enqueue_scripts', 'comkani_toolbar_enqueue_front_asset', 100);

/**
 * Login enqueue assets : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_login_enqueue_assets(): void
{
    wp_enqueue_style(
        'comkani-login',
        comkani_core_url('assets/login.css'),
        [],
        comkani_core_asset_version('assets/login.css')
    );
}

add_action('login_enqueue_scripts', 'comkani_login_enqueue_assets');

/**
 * Login header url : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_login_header_url(): string
{
    return 'https://comkani.fr/';
}

add_filter('login_headerurl', 'comkani_login_header_url');

/**
 * Login header text : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_login_header_text(): string
{
    return 'Agence ComKani';
}

add_filter('login_headertext', 'comkani_login_header_text');

/**
 * Admin footer text : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_footer_text(): string
{
    return 'Site développé et maintenu par <a href="https://comkani.fr/" target="_blank" rel="noopener noreferrer">l’Agence ComKani</a>.';
}

add_filter('admin_footer_text', 'comkani_admin_footer_text');

/**
 * Admin lowercase label : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_lowercase_label(string $label): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower(
            $label,
            'UTF-8'
        );
    }

    return strtolower($label);
}

/**
 * Admin customize post labels : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_customize_post_labels(): void
{
    global $wp_post_types;

    if (
        !isset($wp_post_types['post'])
        || !(bool) comkani_core_get(
            'wordpress.posts.enabled',
            false
        )
    ) {
        return;
    }

    $singular = trim(
        (string) comkani_core_get(
            'wordpress.posts.labels.singular',
            ''
        )
    );

    $plural = trim(
        (string) comkani_core_get(
            'wordpress.posts.labels.plural',
            ''
        )
    );

    if (
        $singular === ''
        || $plural === ''
    ) {
        return;
    }

    $gender = sanitize_key(
        (string) comkani_core_get(
            'wordpress.posts.labels.gender',
            'masculine'
        )
    );

    $feminine = $gender === 'feminine';

    $singular_lower = comkani_admin_lowercase_label(
        $singular
    );

    $plural_lower = comkani_admin_lowercase_label(
        $plural
    );

    $indefinite_article = $feminine
        ? 'une'
        : 'un';

    $definite_article = $feminine
        ? 'la'
        : 'le';

    $new_label = $feminine
        ? 'Nouvelle'
        : 'Nouveau';

    $all_label = $feminine
        ? 'Toutes les'
        : 'Tous les';

    $none_label = $feminine
        ? 'Aucune'
        : 'Aucun';

    $found_label = $feminine
        ? 'trouvée'
        : 'trouvé';

    $labels = $wp_post_types['post']->labels;

    $labels->name = $plural;
    $labels->singular_name = $singular;
    $labels->menu_name = $plural;
    $labels->name_admin_bar = $singular;

    $labels->add_new = 'Ajouter';
    $labels->add_new_item =
        'Ajouter '
        . $indefinite_article
        . ' '
        . $singular_lower;

    $labels->new_item =
        $new_label
        . ' '
        . $singular_lower;

    $labels->edit_item =
        'Modifier '
        . $definite_article
        . ' '
        . $singular_lower;

    $labels->view_item =
        'Voir '
        . $definite_article
        . ' '
        . $singular_lower;

    $labels->view_items =
        'Voir les '
        . $plural_lower;

    $labels->all_items =
        $all_label
        . ' '
        . $plural_lower;

    $labels->search_items =
        'Rechercher dans les '
        . $plural_lower;

    $labels->not_found =
        $none_label
        . ' '
        . $singular_lower
        . ' '
        . $found_label;

    $labels->not_found_in_trash =
        $none_label
        . ' '
        . $singular_lower
        . ' dans la corbeille';

    $labels->archives =
        'Archives des '
        . $plural_lower;

    $labels->filter_items_list =
        'Filtrer les '
        . $plural_lower;

    $labels->items_list_navigation =
        'Navigation des '
        . $plural_lower;

    $labels->items_list =
        'Liste des '
        . $plural_lower;
}

add_action(
    'init',
    'comkani_admin_customize_post_labels',
    100
);

/**
 * Admin menu : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_menu(): void
{
    $types = comkani_request_types();
    $pending_total = 0;

    foreach (array_keys($types) as $type) {
        $pending_total += comkani_requests_stats($type)['pending'];
    }

    $label = 'Demandes';

    if ($pending_total > 0) {
        $label .= sprintf(
            ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
            $pending_total
        );
    }

    if ($types !== []) {
        add_menu_page(
            'Demandes',
            $label,
            'comkani_manage_requests',
            'comkani-requests',
            'comkani_requests_admin_router',
            'dashicons-email-alt',
            25
        );

        foreach ($types as $type => $definition) {
            if (!current_user_can((string) $definition['capability'])) {
                continue;
            }

            add_submenu_page(
                'comkani-requests',
                (string) $definition['label'],
                (string) $definition['label'],
                (string) $definition['capability'],
                'comkani-requests-' . $type,
                static function () use ($type): void {
                    comkani_requests_render_admin_page($type);
                }
            );
        }

        add_submenu_page(
            'comkani-requests',
            'E-mails et confirmations',
            'E-mails',
            'comkani_manage_requests',
            'comkani-request-settings',
            'comkani_request_settings_admin_page'
        );

        remove_submenu_page('comkani-requests', 'comkani-requests');
    }

    if (current_user_can('comkani_manage_notification_bar')) {
        add_menu_page(
            'Notifications',
            'Notifications',
            'comkani_manage_notification_bar',
            'comkani-notification',
            'comkani_notification_bar_admin_page',
            'dashicons-megaphone',
            26
        );
    }

    if (current_user_can('comkani_manage_cache')) {
        add_menu_page(
            'ComKani',
            'ComKani',
            'comkani_manage_cache',
            'comkani',
            'comkani_render_comkani_landing_page',
            'dashicons-shield-alt',
            81
        );

        if (current_user_can('comkani_manage_project')) {
            add_submenu_page(
                'comkani',
                'État du site',
                'État du site',
                'comkani_manage_project',
                'comkani-status',
                'comkani_render_status_page'
            );
        }

        if (current_user_can('comkani_view_activity_log')) {
            add_submenu_page(
                'comkani',
                'Activité',
                'Activité',
                'comkani_view_activity_log',
                'comkani-activity',
                'comkani_activity_render_admin_page'
            );
        }

        if (function_exists('comkani_database_is_agency_user') && comkani_database_is_agency_user()) {
            add_submenu_page(
                'comkani',
                'Base de données',
                'Base de données',
                'comkani_manage_project',
                'comkani-database',
                'comkani_database_render_admin_page'
            );
        }

        add_submenu_page(
            'comkani',
            'Cache',
            'Cache',
            'comkani_manage_cache',
            'comkani-cache',
            'comkani_render_cache_page'
        );

        remove_submenu_page('comkani', 'comkani');
    }
}

add_action('admin_menu', 'comkani_admin_menu', 20);


/**
 * Render landing page : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_render_comkani_landing_page(): void
{
    if (current_user_can('comkani_manage_project')) {
        comkani_render_status_page();
        return;
    }

    comkani_render_cache_page();
}


/**
 * Requests admin router : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_admin_router(): void
{
    $types = comkani_request_types();

    foreach ($types as $type => $definition) {
        if (current_user_can((string) $definition['capability'])) {
            comkani_requests_render_admin_page($type);
            return;
        }
    }

    wp_die('Vous n’avez pas l’autorisation d’accéder à cette page.');
}

/**
 * Admin hide technical menus : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_hide_technical_menus(): void
{
    if (current_user_can('manage_options')) {
        return;
    }

    $pages = [
        'plugins.php',
        'themes.php',
        'tools.php',
        'options-general.php',
        'users.php',
        'edit-comments.php',
        'Wordfence',
        'wordfence',
        'wpseo_dashboard',
        'WPvivid',
        'wfls',
        'wordfence-ls',
    ];

    foreach ($pages as $page) {
        remove_menu_page($page);
    }

    global $menu;

    foreach ((array) $menu as $item) {
        $label = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : '';
        $slug = isset($item[2]) ? (string) $item[2] : '';

        if (
            stripos($label, 'Yoast') !== false
            || stripos($label, 'Login Security') !== false
            || stripos($slug, 'wpseo') !== false
            || stripos($slug, 'wfls') !== false
        ) {
            remove_menu_page($slug);
        }
    }
}

add_action('admin_menu', 'comkani_admin_hide_technical_menus', PHP_INT_MAX);

/**
 * Admin dashboard widgets : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_dashboard_widgets(): void
{
    foreach (comkani_request_types() as $type => $definition) {
        if (!current_user_can((string) $definition['capability'])) {
            continue;
        }

        wp_add_dashboard_widget(
            'comkani-dashboard-' . $type,
            (string) $definition['label'],
            static function () use ($type, $definition): void {
                $stats = comkani_requests_stats($type);
                $url = admin_url('admin.php?page=comkani-requests-' . $type);

                echo '<div class="comkani-dashboard-stats">';
                echo '<div><strong>' . esc_html((string) $stats['today']) . '</strong><span>Aujourd’hui</span></div>';
                echo '<div><strong>' . esc_html((string) $stats['pending']) . '</strong><span>À traiter</span></div>';
                echo '<div><strong>' . esc_html((string) $stats['month']) . '</strong><span>Ce mois</span></div>';
                echo '</div>';
                echo '<p><a class="button button-primary" href="' . esc_url($url) . '">Voir ' . esc_html(strtolower((string) $definition['label'])) . '</a></p>';
            }
        );
    }
}

add_action('wp_dashboard_setup', 'comkani_admin_dashboard_widgets');

/**
 * Requests render admin page : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_render_admin_page(string $type): void
{
    $definition = comkani_request_types()[$type] ?? null;

    if (!$definition || !current_user_can((string) $definition['capability'])) {
        wp_die('Action non autorisée.');
    }

    $request_id = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;

    echo '<div class="wrap comkani-admin-page">';

    if ($request_id > 0) {
        comkani_requests_render_detail($type, $request_id);
        echo '</div>';
        return;
    }

    $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $statuses = comkani_request_statuses($type);

    if ($status !== '' && !isset($statuses[$status])) {
        $status = '';
    }

    $stats = comkani_requests_stats($type);
    $result = comkani_requests_list($type, $status, $search, $page);

    echo '<h1>' . esc_html((string) $definition['label']) . '</h1>';
    echo '<div class="comkani-stat-grid">';

    foreach ([
        'Total' => $stats['total'],
        'Aujourd’hui' => $stats['today'],
        'Ce mois' => $stats['month'],
        'À traiter' => $stats['pending'],
    ] as $label => $value) {
        echo '<div class="comkani-stat-card"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
    }

    echo '</div>';
    echo '<form method="get" class="comkani-admin-filters">';
    echo '<input type="hidden" name="page" value="comkani-requests-' . esc_attr($type) . '">';
    echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Nom, e-mail, téléphone, message">';
    echo '<select name="status"><option value="">Tous les statuts</option>';

    foreach ($statuses as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html($label) . '</option>';
    }

    echo '</select>';
    echo '<button class="button button-primary" type="submit">Filtrer</button>';
    echo '</form>';
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Date</th><th>Client</th><th>Demande</th><th>Statut</th><th>Action</th></tr></thead><tbody>';

    if ($result['items'] === []) {
        echo '<tr><td colspan="5">Aucune demande trouvée.</td></tr>';
    }

    foreach ($result['items'] as $item) {
        $detail_url = add_query_arg([
            'page' => 'comkani-requests-' . $type,
            'request_id' => (int) $item->id,
        ], admin_url('admin.php'));
        $summary = $item->subject !== ''
            ? (string) $item->subject
            : wp_trim_words((string) $item->message, 15);

        if ($type === 'booking' && $item->appointment_at) {
            $summary = 'Rendez-vous : ' . esc_html(mysql2date('d/m/Y H:i', (string) $item->appointment_at));
        }

        echo '<tr>';
        echo '<td>' . esc_html(mysql2date('d/m/Y H:i', (string) $item->created_at)) . '</td>';
        echo '<td><strong>' . esc_html((string) $item->client_name) . '</strong><br><a href="mailto:' . esc_attr((string) $item->client_email) . '">' . esc_html((string) $item->client_email) . '</a></td>';
        echo '<td>' . esc_html($summary) . '</td>';
        echo '<td><span class="comkani-status-badge">' . esc_html($statuses[(string) $item->status] ?? (string) $item->status) . '</span></td>';
        echo '<td><a class="button" href="' . esc_url($detail_url) . '">Voir</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    if ($result['pages'] > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo wp_kses_post(paginate_links([
            'base' => add_query_arg('paged', '%#%'),
            'current' => $page,
            'total' => $result['pages'],
        ]));
        echo '</div></div>';
    }

    echo '</div>';
}

/**
 * Requests render detail : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_render_detail(string $type, int $request_id): void
{
    $request = comkani_request_get($request_id);

    if (!$request || $request->request_type !== $type) {
        echo '<div class="notice notice-error"><p>Cette demande est introuvable.</p></div>';
        return;
    }

    $statuses = comkani_request_statuses($type);
    $payload = json_decode((string) $request->payload, true);
    $payload = is_array($payload) ? $payload : [];

    echo '<p><a href="' . esc_url(admin_url('admin.php?page=comkani-requests-' . $type)) . '">← Retour aux demandes</a></p>';
    echo '<div class="comkani-request-detail">';
    echo '<h1>Demande #' . esc_html((string) $request->id) . '</h1>';
    echo '<div class="comkani-detail-grid">';

    $details = [
        'Reçue le' => mysql2date('d/m/Y H:i', (string) $request->created_at),
        'Nom' => (string) $request->client_name,
        'E-mail' => (string) $request->client_email,
        'Téléphone' => (string) $request->client_phone,
        'Objet' => (string) $request->subject,
        'Rendez-vous' => $request->appointment_at ? mysql2date('d/m/Y H:i', (string) $request->appointment_at) : '',
        'Source' => (string) $request->source_url,
        'Transmission' => (string) $request->delivery_status,
    ];

    foreach ($details as $label => $value) {
        if ($value === '') {
            continue;
        }

        echo '<div><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>';
    }

    echo '</div>';

    if ($request->message !== '') {
        echo '<h2>Message</h2><div class="comkani-message-box">' . nl2br(esc_html((string) $request->message)) . '</div>';
    }

    if ($payload !== []) {
        echo '<h2>Données complémentaires</h2><div class="comkani-detail-grid">';

        foreach ($payload as $key => $value) {
            if (in_array($key, ['request_type', 'client_name', 'client_email', 'client_phone', 'subject', 'message'], true)) {
                continue;
            }

            if (in_array($key, ['application_cv', 'application_cover_letter'], true) && is_array($value)) {
                $label = $key === 'application_cv' ? 'CV' : 'Lettre de motivation';
                $name = sanitize_file_name((string) ($value['name'] ?? 'document.pdf'));
                $download_url = wp_nonce_url(
                    add_query_arg([
                        'action' => 'comkani_application_download',
                        'request_id' => (int) $request->id,
                        'file' => $key,
                    ], admin_url('admin-post.php')),
                    'comkani_application_download_' . (int) $request->id . '_' . $key
                );

                echo '<div><span>' . esc_html($label) . '</span><strong><a class="button" href="' . esc_url($download_url) . '">Télécharger ' . esc_html($name) . '</a></strong></div>';
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }

            $label = match ($key) {
                'client_first_name' => 'Prénom',
                'client_last_name' => 'Nom',
                'application_position' => 'Poste recherché',
                'application_availability' => 'Disponibilité',
                default => ucwords(str_replace('_', ' ', $key)),
            };

            echo '<div><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }

        echo '</div>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="comkani-request-form">';
    echo '<input type="hidden" name="action" value="comkani_request_update">';
    echo '<input type="hidden" name="request_id" value="' . esc_attr((string) $request->id) . '">';
    wp_nonce_field('comkani_request_update_' . $request->id, 'comkani_request_nonce');
    echo '<label><span>Statut</span><select name="status">';

    foreach ($statuses as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected((string) $request->status, $value, false) . '>' . esc_html($label) . '</option>';
    }

    echo '</select></label>';
    echo '<label><span>Note interne</span><textarea name="internal_note" rows="6">' . esc_textarea((string) $request->internal_note) . '</textarea></label>';
    submit_button('Enregistrer');
    echo '</form>';

    $delete_url = wp_nonce_url(
        add_query_arg([
            'action' => 'comkani_request_delete',
            'request_id' => (int) $request->id,
        ], admin_url('admin-post.php')),
        'comkani_request_delete_' . $request->id,
        'comkani_delete_nonce'
    );
    echo '<p><a class="comkani-danger-link" href="' . esc_url($delete_url) . '" onclick="return confirm(\'Supprimer définitivement cette demande ?\');">Supprimer définitivement</a></p>';
    echo '</div>';
}

/**
 * Request handle admin update : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_handle_admin_update(): void
{
    $request_id = isset($_POST['request_id']) ? absint($_POST['request_id']) : 0;
    $request = comkani_request_get($request_id);

    if (!$request || !current_user_can(comkani_request_capability((string) $request->request_type))) {
        wp_die('Action non autorisée.');
    }

    $nonce = isset($_POST['comkani_request_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['comkani_request_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'comkani_request_update_' . $request_id)) {
        wp_die('Requête invalide.');
    }

    $status = sanitize_key((string) wp_unslash($_POST['status'] ?? 'new'));
    $note = sanitize_textarea_field((string) wp_unslash($_POST['internal_note'] ?? ''));
    comkani_request_update_admin($request_id, $status, $note);

    wp_safe_redirect(admin_url('admin.php?page=comkani-requests-' . $request->request_type . '&request_id=' . $request_id));
    exit;
}

add_action('admin_post_comkani_request_update', 'comkani_request_handle_admin_update');

/**
 * Request handle admin delete : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_handle_admin_delete(): void
{
    $request_id = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;
    $request = comkani_request_get($request_id);

    if (!$request || !current_user_can(comkani_request_capability((string) $request->request_type))) {
        wp_die('Action non autorisée.');
    }

    $nonce = isset($_GET['comkani_delete_nonce'])
        ? sanitize_text_field(wp_unslash($_GET['comkani_delete_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'comkani_request_delete_' . $request_id)) {
        wp_die('Requête invalide.');
    }

    $type = (string) $request->request_type;
    comkani_request_delete($request_id);
    wp_safe_redirect(admin_url('admin.php?page=comkani-requests-' . $type));
    exit;
}

add_action('admin_post_comkani_request_delete', 'comkani_request_handle_admin_delete');

/**
 * Affiche l'état technique synthétique du site pour l'Agence ComKani.
 *
 * Les contrôles sont répartis en deux groupes pour faciliter la lecture :
 * - environnement et sécurité WordPress
 * - services et maintenance du projet
 *
 * Cet écran est informatif. Il ne modifie aucune configuration serveur,
 * extension ou constante WordPress.
 *
 * @return void
 */
/**
 * Détermine si l'administration WordPress est effectivement servie en HTTPS.
 *
 * `is_ssl()` reste le contrôle principal. Les vérifications complémentaires
 * couvrent les environnements locaux ou les reverse proxies qui terminent le
 * TLS avant Apache/PHP et peuvent ne pas exposer HTTPS de manière classique.
 *
 * @return bool true lorsque l'environnement ou les URLs WordPress utilisent HTTPS.
 */
/**
 * Exécute la fonction admin is https.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkani_admin_is_https(): bool
{
    if (is_ssl()) {
        return true;
    }

    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }

    $forwarded_proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    if (in_array('https', array_map('trim', explode(',', $forwarded_proto)), true)) {
        return true;
    }

    $home_scheme = wp_parse_url((string) home_url('/'), PHP_URL_SCHEME);
    $site_scheme = wp_parse_url((string) site_url('/'), PHP_URL_SCHEME);

    return $home_scheme === 'https' && $site_scheme === 'https';
}

/**
 * Retourne le libellé d'état à afficher dans la page technique ComKani.
 *
 * @param bool $valid État du contrôle.
 * @param string $success Libellé lorsque le contrôle est valide.
 * @param string $warning Libellé lorsque le contrôle nécessite une vérification.
 * @return array{value:string,status:string}
 */
function comkani_admin_status_value(
    bool $valid,
    string $success,
    string $warning
): array {
    return [
        'value' => $valid ? $success : $warning,
        'status' => $valid ? 'success' : 'warning',
    ];
}


/**
 * Analyse rapidement les médias WordPress pour signaler les fichiers lourds.
 *
 * Le résultat est mis en cache pendant six heures afin de ne pas parcourir le
 * dossier uploads à chaque ouverture de l'écran État du site.
 *
 * @return array{images:int,videos:int,largest:int} Compteurs de vigilance.
 */
function comkani_admin_media_performance_summary(): array
{
    $cached = get_transient('comkani_media_performance_summary');

    if (is_array($cached)) {
        return [
            'images' => (int) ($cached['images'] ?? 0),
            'videos' => (int) ($cached['videos'] ?? 0),
            'largest' => (int) ($cached['largest'] ?? 0),
        ];
    }

    $uploads = wp_upload_dir();
    $base = (string) ($uploads['basedir'] ?? '');
    $summary = [
        'images' => 0,
        'videos' => 0,
        'largest' => 0,
    ];

    if ($base === '' || !is_dir($base)) {
        return $summary;
    }

    $image_extensions = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
    $video_extensions = ['mp4', 'webm', 'mov', 'm4v'];
    $scanned = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $base,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $scanned += 1;

        if ($scanned > 5000) {
            break;
        }

        $size = (int) $file->getSize();
        $summary['largest'] = max($summary['largest'], $size);
        $extension = strtolower((string) $file->getExtension());

        if (in_array($extension, $image_extensions, true) && $size > 2 * MB_IN_BYTES) {
            $summary['images'] += 1;
        }

        if (in_array($extension, $video_extensions, true) && $size > 15 * MB_IN_BYTES) {
            $summary['videos'] += 1;
        }
    }

    set_transient(
        'comkani_media_performance_summary',
        $summary,
        6 * HOUR_IN_SECONDS
    );

    return $summary;
}

/**
 * Retourne les principaux contrôles de livraison production.
 *
 * Les contrôles sont informatifs : ils n'empêchent jamais la génération des
 * packages préproduction ou production.
 *
 * @return array<int, array{label:string,value:string,status:string}> Contrôles.
 */
function comkani_admin_production_checks(): array
{
    $theme_dir = get_template_directory();
    $environment = wp_get_environment_type();

    $checks = [
        [
            'label' => 'HTTPS',
            'value' => comkani_admin_is_https() ? 'Actif' : 'À vérifier',
            'status' => comkani_admin_is_https() ? 'success' : 'warning',
        ],
        [
            'label' => 'WP_DEBUG',
            'value' => defined('WP_DEBUG') && WP_DEBUG ? 'Actif' : 'Désactivé',
            'status' => defined('WP_DEBUG') && WP_DEBUG && $environment === 'production'
                ? 'warning'
                : 'success',
        ],
        [
            'label' => '.git dans le thème',
            'value' => is_dir($theme_dir . '/.git') ? 'Présent' : 'Absent',
            'status' => is_dir($theme_dir . '/.git') && $environment === 'production'
                ? 'warning'
                : 'success',
        ],
        [
            'label' => 'node_modules dans le thème',
            'value' => is_dir($theme_dir . '/node_modules') ? 'Présent' : 'Absent',
            'status' => is_dir($theme_dir . '/node_modules') && $environment === 'production'
                ? 'warning'
                : 'success',
        ],
    ];

    return $checks;
}

/**
 * Affiche l'écran "État du site" réservé à l'Agence ComKani.
 *
 * L'écran présente les contrôles techniques sous forme de panneaux sobres,
 * proches des écrans Demandes et Contacts du Starter. Il ne modifie aucune
 * configuration : il fournit uniquement un état de lecture.
 *
 * @return void
 */
function comkani_render_status_page(): void
{
    if (!current_user_can('comkani_manage_project')) {
        wp_die('Action non autorisée.');
    }

    $integrations = comkani_integration_status();
    $environment = wp_get_environment_type();
    $is_https = comkani_admin_is_https();
    $is_debug = defined('WP_DEBUG') && WP_DEBUG;
    $is_file_edit_locked = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
    $media_performance = comkani_admin_media_performance_summary();
    $production_checks = comkani_admin_production_checks();

    $https = comkani_admin_status_value(
        $is_https,
        'Actif',
        'À vérifier'
    );

    $file_editor = comkani_admin_status_value(
        $is_file_edit_locked,
        'Bloqué',
        'À vérifier'
    );

    $groups = [
        [
            'title' => 'Environnement',
            'items' => [
                [
                    'label' => 'Type',
                    'value' => $environment,
                    'status' => 'neutral',
                ],
                [
                    'label' => 'HTTPS',
                    'value' => $https['value'],
                    'status' => $https['status'],
                ],
                [
                    'label' => 'WP_DEBUG',
                    'value' => $is_debug ? 'Actif' : 'Désactivé',
                    'status' => $is_debug && $environment === 'production'
                        ? 'warning'
                        : 'success',
                ],
                [
                    'label' => 'Éditeur de fichiers',
                    'value' => $file_editor['value'],
                    'status' => $file_editor['status'],
                ],
                [
                    'label' => 'URL de connexion',
                    'value' => function_exists('comkani_security_login_slug') && comkani_security_login_slug() !== ''
                        ? '/' . comkani_security_login_slug() . '/'
                        : 'WordPress standard',
                    'status' => function_exists('comkani_security_login_slug') && comkani_security_login_slug() !== ''
                        ? 'success'
                        : 'neutral',
                ],
            ],
        ],
        [
            'title' => 'Outils du projet',
            'items' => [
                [
                    'label' => 'Wordfence',
                    'value' => $integrations['wordfence']['active'] ? 'Actif' : 'Non détecté',
                    'status' => $integrations['wordfence']['active']
                        ? 'success'
                        : ($environment === 'local' ? 'neutral' : 'warning'),
                ],
                [
                    'label' => 'Yoast SEO',
                    'value' => $integrations['yoast']['active'] ? 'Actif' : 'Non détecté',
                    'status' => $integrations['yoast']['active'] ? 'success' : 'warning',
                ],
                [
                    'label' => 'Sauvegarde WPvivid',
                    'value' => $integrations['wpvivid']['active'] ? 'Actif' : 'Non détecté',
                    'status' => $integrations['wpvivid']['active']
                        ? 'success'
                        : ($environment === 'local' ? 'neutral' : 'warning'),
                ],
                [
                    'label' => 'ComKani Core',
                    'value' => COMKANI_CORE_VERSION,
                    'status' => 'success',
                ],
            ],
        ],
        [
            'title' => 'Performance',
            'items' => [
                [
                    'label' => 'Images > 2 Mo',
                    'value' => (string) $media_performance['images'],
                    'status' => $media_performance['images'] > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Vidéos > 15 Mo',
                    'value' => (string) $media_performance['videos'],
                    'status' => $media_performance['videos'] > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'CSS compilé',
                    'value' => file_exists(get_template_directory() . '/assets/build/main.css')
                        ? size_format((int) filesize(get_template_directory() . '/assets/build/main.css'))
                        : 'Non détecté',
                    'status' => 'neutral',
                ],
                [
                    'label' => 'JS compilé',
                    'value' => file_exists(get_template_directory() . '/assets/build/main.js')
                        ? size_format((int) filesize(get_template_directory() . '/assets/build/main.js'))
                        : 'Non détecté',
                    'status' => 'neutral',
                ],
            ],
        ],
        [
            'title' => 'Contrôles',
            'items' => $production_checks,
        ],
    ];

    echo '<div class="wrap comkani-admin-page comkani-status-page">';
    echo '<h1>État du site</h1>';

    echo '<div class="comkani-status-layout">';

    foreach ($groups as $group) {
        echo '<section class="comkani-status-panel">';
        echo '<h2>' . esc_html($group['title']) . '</h2>';
        echo '<div class="comkani-status-list">';

        foreach ($group['items'] as $item) {
            $status = sanitize_html_class((string) $item['status']);
            echo '<div class="comkani-status-row">';
            echo '<span class="comkani-status-label">' . esc_html($item['label']) . '</span>';
            echo '<span class="comkani-status-value is-' . esc_attr($status) . '">' . esc_html((string) $item['value']) . '</span>';
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';
    }

    echo '</div>';

    if ($environment === 'production') {
        echo '<div class="notice notice-info inline comkani-production-note"><p><strong>Contrôle production :</strong> le WAF Wordfence en mode étendu doit être validé sur le serveur de production, car sa configuration dépend du chemin réel du serveur.</p></div>';
    }

    echo '</div>';
}

/**
 * Render cache page : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_render_cache_page(): void
{
    if (!current_user_can('comkani_manage_cache')) {
        wp_die('Action non autorisée.');
    }

    $clear_cache_url = wp_nonce_url(
        admin_url('admin-post.php?action=comkani_clear_caches'),
        'comkani_clear_caches'
    );

    $last_clear = get_option('comkani_cache_last_cleared_at', 0);
    $last_clear_label = 'Jamais';
    $timestamp = 0;

    if (is_numeric($last_clear)) {
        $timestamp = (int) $last_clear;
    } elseif (is_string($last_clear) && $last_clear !== '') {
        // Compatibilité avec les anciennes versions qui enregistraient
        // une date locale WordPress au format MySQL.
        $legacy_gmt = get_gmt_from_date($last_clear);
        $legacy_timestamp = strtotime($legacy_gmt . ' UTC');

        if ($legacy_timestamp !== false) {
            $timestamp = $legacy_timestamp;
        }
    }

    if ($timestamp > 0) {
        $last_clear_label = wp_date(
            'd/m/Y à H:i',
            $timestamp,
            wp_timezone()
        );
    }

    echo '<div class="wrap comkani-admin-page">';
    echo '<h1>Gestion des caches de performance</h1>';
    echo '<div class="comkani-health-grid">';
    echo '<div class="comkani-health-card">';
    echo '<span>Date de la dernière purge</span>';
    echo '<strong>' . esc_html($last_clear_label) . '</strong>';
    echo '</div>';
    echo '</div>';

    echo '<h2>Purge de performance</h2>';
    echo '<p>Cette action actualise les caches de performance ComKani et déclenche les purges des extensions compatibles.</p>';
    echo '<p>Les compteurs de sécurité des formulaires et les autres données temporaires fonctionnelles sont conservés. Le cache objet WordPress n’est pas vidé globalement.</p>';
    echo '<p>ATTENTION : le cache objet WordPress n’est pas vidé globalement et aucune purge d’hébergement n’est réalisé.</p>';
    echo '<p><a class="button button-primary" href="' . esc_url($clear_cache_url) . '">Vider les caches</a></p>';
    echo '</div>';
}

/**
 * Limite le tableau de bord aux indicateurs utiles pour chaque profil.
 *
 * La sélection est identique pour tous les utilisateurs ; WordPress et les
 * capacités ComKani restent responsables des autorisations d'accès.
 * Les types de demandes proviennent de la configuration active du projet.
 * Les autres extensions ne peuvent pas imposer leurs widgets au dashboard.
 */
function comkani_admin_clean_client_dashboard(): void
{
    remove_action('welcome_panel', 'wp_welcome_panel');

    global $wp_meta_boxes;

    // Le widget natif est enregistré par WordPress selon ses propres droits.
    $allowed = ['dashboard_site_health'];

    // Contacts, devis, réservations, candidatures : modules actifs uniquement.
    foreach (comkani_request_types() as $type => $definition) {
        if (!current_user_can((string) $definition['capability'])) {
            continue;
        }
        $allowed[] = 'comkani-dashboard-' . $type;
    }

    foreach (['normal', 'side', 'column3', 'column4'] as $context) {
        foreach (['high', 'core', 'default', 'low'] as $priority) {
            if (empty($wp_meta_boxes['dashboard'][$context][$priority])) {
                continue;
            }

            foreach (array_keys($wp_meta_boxes['dashboard'][$context][$priority]) as $widget_id) {
                if (!in_array($widget_id, $allowed, true)) {
                    unset($wp_meta_boxes['dashboard'][$context][$priority][$widget_id]);
                }
            }
        }
    }
}

add_action('wp_dashboard_setup', 'comkani_admin_clean_client_dashboard', PHP_INT_MAX);

/**
 * Admin finalize client dashboard : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_finalize_client_dashboard(): void
{
    comkani_admin_clean_client_dashboard();
}

add_action('admin_head-index.php', 'comkani_admin_finalize_client_dashboard', PHP_INT_MAX);

/**
 * Admin custom menu order : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_custom_menu_order(bool $custom): bool
{
    return true;
}

add_filter('custom_menu_order', 'comkani_admin_custom_menu_order');

/**
 * Admin menu order : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_menu_order(array $menu_order): array
{
    $configured_order = comkani_core_get('admin.menuOrder', []);

    if (!is_array($configured_order) || $configured_order === []) {
        return $menu_order;
    }

    $menu_map = [
        'dashboard' => 'index.php',
        'comkani' => 'comkani',
        'pages' => 'edit.php?post_type=page',
        'posts' => 'edit.php',
        'media' => 'upload.php',
        'requests' => 'comkani-requests',
        'faq' => 'edit.php?post_type=comkani_faq',
        'comments' => 'edit-comments.php',
        'users' => 'users.php',
        'settings' => 'options-general.php',
        'tools' => 'tools.php',
        'appearance' => 'themes.php',
        'plugins' => 'plugins.php',
        'yoast' => 'wpseo_dashboard',
        'wpvivid' => 'WPvivid',
    ];
    $ordered = [];

    foreach ($configured_order as $menu_key) {
        if (!is_string($menu_key)) {
            continue;
        }

        $menu_slug = $menu_map[$menu_key] ?? $menu_key;

        if (in_array($menu_slug, $menu_order, true)) {
            $ordered[] = $menu_slug;
        }
    }

    foreach ($menu_order as $menu_slug) {
        if (!in_array($menu_slug, $ordered, true)) {
            $ordered[] = $menu_slug;
        }
    }

    return $ordered;
}

add_filter('menu_order', 'comkani_admin_menu_order', 999);

/**
 * Admin hide screen options : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_hide_screen_options(bool $show_screen): bool
{
    return false;
}

add_filter('screen_options_show_screen', 'comkani_admin_hide_screen_options');

/**
 * Admin remove help tabs : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_remove_help_tabs(): void
{
    $screen = get_current_screen();

    if (!$screen) {
        return;
    }

    $screen->remove_help_tabs();
    $screen->set_help_sidebar('');
}

add_action('current_screen', 'comkani_admin_remove_help_tabs', 100);


/**
 * Admin remove screen meta ui : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_remove_screen_meta_ui(): void
{
    echo '<style>#screen-meta,#screen-meta-links,#screen-options-link-wrap,#contextual-help-link-wrap{display:none!important}</style>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){["screen-meta","screen-meta-links","screen-options-link-wrap","contextual-help-link-wrap"].forEach(function(id){var node=document.getElementById(id);if(node){node.remove();}});});</script>';
}

add_action('admin_head', 'comkani_admin_remove_screen_meta_ui', 999);

/**
 * Admin bar customize : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_bar_customize(WP_Admin_Bar $admin_bar): void
{
    if (!is_user_logged_in()) {
        return;
    }

    $preserved_groups = [
        'root-default',
        'top-secondary',
    ];

    foreach ($admin_bar->get_nodes() as $node) {
        if (in_array($node->id, $preserved_groups, true)) {
            continue;
        }

        $admin_bar->remove_node($node->id);
    }

    $admin_bar->add_node([
        'id' => 'comkani-view-site',
        'title' => is_admin() ? 'Accéder au site' : 'Accéder au dashboard',
        'href' => is_admin() ? home_url('/') : admin_url(),
        'meta' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ],
    ]);

    $admin_bar->add_node([
        'id' => 'comkani-logout',
        'title' => 'Se déconnecter',
        'href' => wp_logout_url(wp_login_url()),
    ]);

    $admin_bar->add_node([
        'id' => 'comkani-support',
        'title' => 'CONTACTEZ VOTRE AGENCE',
        'href' => 'mailto:support-technique@comkani.fr?subject=' . rawurlencode(
            'Assistance - ' . get_bloginfo('name')
        ),
    ]);

    $site_icon = get_site_icon_url(32);

    if ($site_icon === '') {
        $site_icon = comkani_core_url('assets/logo-agence-comkani-white.svg');
    }

    $admin_bar->add_node([
        'id' => 'comkani-brand',
        'parent' => 'top-secondary',
        'title' => sprintf(
            '<span class="comkani-toolbar-brand-icon"><img src="%1$s" alt=""></span><span class="screen-reader-text">%2$s</span>',
            esc_url($site_icon),
            esc_html(get_bloginfo('name'))
        ),
        'meta' => [
            'class' => 'comkani-toolbar-static',
        ],
    ]);
}

add_action('admin_bar_menu', 'comkani_admin_bar_customize', 99999);

/**
 * Admin bar finalize : fonction du module admin.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_admin_bar_finalize(): void
{
    global $wp_admin_bar;

    if (!($wp_admin_bar instanceof WP_Admin_Bar)) {
        return;
    }

    $allowed_nodes = [
        'root-default',
        'top-secondary',
        'comkani-brand',
        'comkani-view-site',
        'comkani-logout',
        'comkani-support',
    ];

    foreach ($wp_admin_bar->get_nodes() as $node) {
        if (in_array($node->id, $allowed_nodes, true)) {
            continue;
        }

        $wp_admin_bar->remove_node($node->id);
    }
}

add_action('wp_before_admin_bar_render', 'comkani_admin_bar_finalize', PHP_INT_MAX);
