<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/notification-bar.php
// Rôle : gérer plusieurs barres de notification planifiables
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Notification defaults : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_defaults(): array
{
    return [
        'id' => '',
        'enabled' => false,
        'message' => '',
        'link_label' => '',
        'link_url' => '',
        'start_at' => '',
        'end_at' => '',
        'version' => '1',
    ];
}

/**
 * Notification parse datetime : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_parse_datetime(string $value): ?DateTimeImmutable
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat(
        'Y-m-d\TH:i',
        $value,
        wp_timezone()
    );

    return $date instanceof DateTimeImmutable ? $date : null;
}

/**
 * Notifications get all : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notifications_get_all(): array
{
    $saved = get_option('comkani_notification_bars', []);

    if (is_array($saved) && $saved !== []) {
        return array_values(array_filter(
            array_map(
                static fn($item): array => wp_parse_args(
                    is_array($item) ? $item : [],
                    comkani_notification_defaults()
                ),
                $saved
            ),
            static fn(array $item): bool => (string) $item['id'] !== ''
        ));
    }

    $legacy = get_option('comkani_notification_bar', []);

    if (is_array($legacy) && trim((string) ($legacy['message'] ?? '')) !== '') {
        $legacy = wp_parse_args($legacy, comkani_notification_defaults());
        $legacy['id'] = wp_generate_uuid4();
        $legacy['version'] = (string) time();
        update_option('comkani_notification_bars', [$legacy], false);

        return [$legacy];
    }

    return [];
}

/**
 * Notifications save all : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notifications_save_all(array $notifications): void
{
    update_option(
        'comkani_notification_bars',
        array_values($notifications),
        false
    );
}

/**
 * Notification is active : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_is_active(array $notification): bool
{
    if (
        empty($notification['enabled'])
        || trim((string) $notification['message']) === ''
    ) {
        return false;
    }

    $now = new DateTimeImmutable('now', wp_timezone());
    $start = comkani_notification_parse_datetime((string) $notification['start_at']);
    $end = comkani_notification_parse_datetime((string) $notification['end_at']);

    if ($start && $now < $start) {
        return false;
    }

    if ($end && $now >= $end) {
        return false;
    }

    return true;
}

/**
 * Notification status : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_status(array $notification): string
{
    if (empty($notification['enabled'])) {
        return 'Inactive';
    }

    $now = new DateTimeImmutable('now', wp_timezone());
    $start = comkani_notification_parse_datetime((string) $notification['start_at']);
    $end = comkani_notification_parse_datetime((string) $notification['end_at']);

    if ($start && $now < $start) {
        return 'Planifiée';
    }

    if ($end && $now >= $end) {
        return 'Terminée';
    }

    return 'Active';
}

/**
 * Notification active : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_active(): ?array
{
    $active = array_values(array_filter(
        comkani_notifications_get_all(),
        'comkani_notification_is_active'
    ));

    if ($active === []) {
        return null;
    }

    usort(
        $active,
        static function (array $a, array $b): int {
            $a_start = comkani_notification_parse_datetime((string) $a['start_at']);
            $b_start = comkani_notification_parse_datetime((string) $b['start_at']);
            $a_time = $a_start ? $a_start->getTimestamp() : 0;
            $b_time = $b_start ? $b_start->getTimestamp() : 0;

            return $b_time <=> $a_time;
        }
    );

    return $active[0];
}

/**
 * Notification bar render : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_bar_render(): void
{
    $notification = comkani_notification_active();

    if (!$notification) {
        return;
    }

    $message = trim(
        (string) $notification['message']
    );

    $link_url = esc_url(
        (string) $notification['link_url']
    );

    $link_label = trim(
        (string) $notification['link_label']
    );
    ?>
    <aside
        class="notification-bar"
        data-notification-bar
        data-notification-id="<?php echo esc_attr((string) $notification['id']); ?>"
        data-notification-version="<?php echo esc_attr((string) $notification['version']); ?>"
        aria-label="Information importante"
    >
        <div class="notification-inner">
            <div class="notification-message">
                <div class="track" data-notification-track>
                    <div class="item" data-notification-item>
                        <span>
                            <?php echo esc_html($message); ?>
                        </span>

                        <?php if ($link_url !== '' && $link_label !== '') : ?>
                            <a href="<?php echo $link_url; ?>">
                                <?php echo esc_html($link_label); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <button
                class="notification-close"
                type="button"
                data-notification-close
                aria-label="Fermer la notification"
            >
                <span aria-hidden="true">×</span>
            </button>
        </div>
    </aside>
    <?php
}
add_action(
    'wp_body_open',
    'comkani_notification_bar_render',
    5
);

/**
 * Notification admin url : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_admin_url(array $args = []): string
{
    return add_query_arg(
        array_merge(['page' => 'comkani-notification'], $args),
        admin_url('admin.php')
    );
}

/**
 * Notification bar admin page : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_bar_admin_page(): void
{
    if (!current_user_can('comkani_manage_notification_bar')) {
        wp_die('Accès refusé.');
    }

    $notifications = comkani_notifications_get_all();
    $edit_id = isset($_GET['notification_id'])
        ? sanitize_text_field(wp_unslash($_GET['notification_id']))
        : '';
    $editing = comkani_notification_defaults();

    foreach ($notifications as $notification) {
        if ((string) $notification['id'] === $edit_id) {
            $editing = $notification;
            break;
        }
    }

    $saved = isset($_GET['updated']) && sanitize_key(wp_unslash($_GET['updated'])) === '1';
    $deleted = isset($_GET['deleted']) && sanitize_key(wp_unslash($_GET['deleted'])) === '1';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Notifications</h1>
        <a href="<?php echo esc_url(comkani_notification_admin_url(['new' => '1'])); ?>" class="page-title-action">Ajouter une notification</a>
        <hr class="wp-header-end">

        <p>Prépare plusieurs notifications à l’avance. Une seule barre est affichée à la fois. Si deux périodes se chevauchent, la notification dont la date de début est la plus récente est prioritaire.</p>

        <?php if ($saved) : ?>
            <div class="notice notice-success is-dismissible"><p>Notification enregistrée.</p></div>
        <?php endif; ?>

        <?php if ($deleted) : ?>
            <div class="notice notice-success is-dismissible"><p>Notification supprimée.</p></div>
        <?php endif; ?>

        <?php if ($notifications !== []) : ?>
            <table class="widefat striped" style="margin-top:20px">
                <thead>
                    <tr>
                        <th>Notification</th>
                        <th>État</th>
                        <th>Apparition</th>
                        <th>Disparition</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification) : ?>
                        <tr>
                            <td><strong><?php echo esc_html(wp_trim_words((string) $notification['message'], 12)); ?></strong></td>
                            <td><?php echo esc_html(comkani_notification_status($notification)); ?></td>
                            <td><?php echo esc_html((string) ($notification['start_at'] !== '' ? $notification['start_at'] : 'Immédiate')); ?></td>
                            <td><?php echo esc_html((string) ($notification['end_at'] !== '' ? $notification['end_at'] : 'Sans date')); ?></td>
                            <td>
                                <a href="<?php echo esc_url(comkani_notification_admin_url(['notification_id' => (string) $notification['id']])); ?>">Modifier</a>
                                |
                                <a href="<?php echo esc_url(wp_nonce_url(
                                    admin_url('admin-post.php?action=comkani_duplicate_notification&id=' . rawurlencode((string) $notification['id'])),
                                    'comkani_duplicate_notification_' . (string) $notification['id']
                                )); ?>">Dupliquer</a>
                                |
                                <a href="<?php echo esc_url(wp_nonce_url(
                                    admin_url('admin-post.php?action=comkani_delete_notification&id=' . rawurlencode((string) $notification['id'])),
                                    'comkani_delete_notification_' . (string) $notification['id']
                                )); ?>" onclick="return window.confirm('Supprimer cette notification ?');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php
        $show_form = $edit_id !== '' || isset($_GET['new']) || $notifications === [];
        if ($show_form) :
        ?>
            <hr style="margin:32px 0">
            <h2><?php echo esc_html($edit_id !== '' ? 'Modifier la notification' : 'Nouvelle notification'); ?></h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="comkani_save_notification_bar">
                <input type="hidden" name="id" value="<?php echo esc_attr((string) $editing['id']); ?>">
                <?php wp_nonce_field('comkani_save_notification_bar', 'comkani_notification_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">État</th>
                        <td>
                            <label>
                                <input name="enabled" type="checkbox" value="1" <?php checked(!empty($editing['enabled'])); ?>>
                                Activer cette notification
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="comkani-notification-message">Texte</label></th>
                        <td><input class="large-text" id="comkani-notification-message" name="message" type="text" maxlength="300" value="<?php echo esc_attr((string) $editing['message']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="comkani-notification-link-label">Texte du lien</label></th>
                        <td><input class="regular-text" id="comkani-notification-link-label" name="link_label" type="text" maxlength="80" value="<?php echo esc_attr((string) $editing['link_label']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="comkani-notification-link-url">URL du lien</label></th>
                        <td><input class="large-text" id="comkani-notification-link-url" name="link_url" type="url" value="<?php echo esc_attr((string) $editing['link_url']); ?>" placeholder="https://"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="comkani-notification-start">Date d’apparition</label></th>
                        <td><input id="comkani-notification-start" name="start_at" type="datetime-local" value="<?php echo esc_attr((string) $editing['start_at']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="comkani-notification-end">Date de disparition</label></th>
                        <td><input id="comkani-notification-end" name="end_at" type="datetime-local" value="<?php echo esc_attr((string) $editing['end_at']); ?>"></td>
                    </tr>
                </table>

                <?php submit_button($edit_id !== '' ? 'Mettre à jour la notification' : 'Créer la notification'); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Notification bar save : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_bar_save(): void
{
    if (!current_user_can('comkani_manage_notification_bar')) {
        wp_die('Accès refusé.');
    }

    check_admin_referer('comkani_save_notification_bar', 'comkani_notification_nonce');

    $id = sanitize_text_field((string) ($_POST['id'] ?? ''));
    $start_at = sanitize_text_field((string) ($_POST['start_at'] ?? ''));
    $end_at = sanitize_text_field((string) ($_POST['end_at'] ?? ''));

    if ($start_at !== '' && !comkani_notification_parse_datetime($start_at)) {
        $start_at = '';
    }

    if ($end_at !== '' && !comkani_notification_parse_datetime($end_at)) {
        $end_at = '';
    }

    $notification = [
        'id' => $id !== '' ? $id : wp_generate_uuid4(),
        'enabled' => !empty($_POST['enabled']),
        'message' => sanitize_text_field((string) ($_POST['message'] ?? '')),
        'link_label' => sanitize_text_field((string) ($_POST['link_label'] ?? '')),
        'link_url' => esc_url_raw((string) ($_POST['link_url'] ?? '')),
        'start_at' => $start_at,
        'end_at' => $end_at,
        'version' => (string) time(),
    ];

    $notifications = comkani_notifications_get_all();
    $updated = false;

    foreach ($notifications as $index => $existing) {
        if ((string) $existing['id'] === (string) $notification['id']) {
            $notifications[$index] = $notification;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $notifications[] = $notification;
    }

    comkani_notifications_save_all($notifications);

    wp_safe_redirect(comkani_notification_admin_url(['updated' => '1']));
    exit;
}
add_action('admin_post_comkani_save_notification_bar', 'comkani_notification_bar_save');

/**
 * Notification duplicate : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_duplicate(): void
{
    if (!current_user_can('comkani_manage_notification_bar')) {
        wp_die('Accès refusé.');
    }

    $id = sanitize_text_field((string) ($_GET['id'] ?? ''));
    check_admin_referer('comkani_duplicate_notification_' . $id);
    $notifications = comkani_notifications_get_all();

    foreach ($notifications as $notification) {
        if ((string) $notification['id'] !== $id) {
            continue;
        }

        $copy = $notification;
        $copy['id'] = wp_generate_uuid4();
        $copy['enabled'] = false;
        $copy['message'] = trim((string) $copy['message']) . ' (copie)';
        $copy['version'] = (string) time();
        $notifications[] = $copy;
        comkani_notifications_save_all($notifications);
        break;
    }

    wp_safe_redirect(comkani_notification_admin_url(['updated' => '1']));
    exit;
}
add_action('admin_post_comkani_duplicate_notification', 'comkani_notification_duplicate');

/**
 * Notification delete : fonction du module notification-bar.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_notification_delete(): void
{
    if (!current_user_can('comkani_manage_notification_bar')) {
        wp_die('Accès refusé.');
    }

    $id = sanitize_text_field((string) ($_GET['id'] ?? ''));
    check_admin_referer('comkani_delete_notification_' . $id);
    $notifications = array_values(array_filter(
        comkani_notifications_get_all(),
        static fn(array $notification): bool => (string) $notification['id'] !== $id
    ));

    comkani_notifications_save_all($notifications);

    wp_safe_redirect(comkani_notification_admin_url(['deleted' => '1']));
    exit;
}
add_action('admin_post_comkani_delete_notification', 'comkani_notification_delete');
