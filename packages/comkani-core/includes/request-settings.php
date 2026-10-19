<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/request-settings.php
// Rôle : rendre les messages et e-mails des formulaires administrables
// =============================================================================
defined('ABSPATH') || exit;

/**
 * Request settings defaults : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_settings_defaults(string $type): array
{
    $types = function_exists('comkani_request_types') ? comkani_request_types() : [];
    $label = isset($types[$type]['singular'])
        ? (string) $types[$type]['singular']
        : 'Demande';

    $confirmation_subject = 'Votre demande a bien été reçue';
    $confirmation_body = "Bonjour {{name}},\n\nNous avons bien reçu votre demande. Notre équipe reviendra vers vous dans les meilleurs délais.\n\nÀ bientôt,\n{{site_name}}";

    if ($type === 'application') {
        $confirmation_subject = 'Votre candidature a bien été reçue';
        $confirmation_body = "Bonjour {{name}},\n\nNous avons bien reçu votre candidature. Notre équipe reviendra vers vous si votre profil correspond à nos besoins.\n\nMerci pour votre intérêt,\n{{site_name}}";
    }

    if ($type === 'booking') {
        $confirmation_subject = 'Votre demande de rendez-vous a bien été reçue';
        $confirmation_body = "Bonjour {{name}},\n\nNous avons bien reçu votre demande de rendez-vous. Notre équipe reviendra vers vous rapidement pour la confirmer.\n\nÀ bientôt,\n{{site_name}}";
    }

    if ($type === 'quote') {
        $confirmation_subject = 'Votre demande de devis a bien été reçue';
        $confirmation_body = "Bonjour {{name}},\n\nNous avons bien reçu votre demande de devis. Notre équipe reviendra vers vous dans les meilleurs délais.\n\nÀ bientôt,\n{{site_name}}";
    }

    return [
        'recipient_email' => '',
        'front_success' => 'Votre demande a bien été envoyée.',
        'internal_subject' => '[{{site_name}}] ' . $label,
        'internal_body' => "Nouvelle demande depuis {{site_name}}\n\n{{request_details}}",
        'confirmation_enabled' => true,
        'confirmation_subject' => $confirmation_subject,
        'confirmation_body' => $confirmation_body,
    ];
}

/**
 * Request settings all : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_settings_all(): array
{
    $stored = get_option('comkani_request_messages', []);

    return is_array($stored) ? $stored : [];
}

/**
 * Request settings get : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_settings_get(string $type): array
{
    $all = comkani_request_settings_all();
    $saved = isset($all[$type]) && is_array($all[$type]) ? $all[$type] : [];

    return wp_parse_args($saved, comkani_request_settings_defaults($type));
}

/**
 * Request template values : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_template_values(array $data): array
{
    $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
    $appointment = '';

    if (($data['appointment_at'] ?? null) instanceof DateTimeImmutable) {
        $appointment = wp_date(
            'd/m/Y à H:i',
            $data['appointment_at']->getTimestamp(),
            wp_timezone()
        );
    }

    return [
        '{{site_name}}' => (string) get_bloginfo('name'),
        '{{name}}' => (string) ($data['client_name'] ?? ''),
        '{{email}}' => (string) ($data['client_email'] ?? ''),
        '{{phone}}' => (string) ($data['client_phone'] ?? ''),
        '{{company}}' => (string) ($payload['client_company'] ?? ''),
        '{{position}}' => (string) ($payload['client_position'] ?? $payload['application_position'] ?? ''),
        '{{subject}}' => (string) ($data['subject'] ?? ''),
        '{{message}}' => (string) ($data['message'] ?? ''),
        '{{appointment}}' => $appointment,
        '{{request_type}}' => (string) ($data['request_type'] ?? ''),
        '{{request_details}}' => function_exists('comkani_request_mail_message')
            ? comkani_request_mail_message($data)
            : '',
    ];
}

/**
 * Request render template : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_render_template(string $template, array $data): string
{
    return strtr($template, comkani_request_template_values($data));
}

/**
 * Request front message : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_front_message(string $type, string $status, string $default): string
{
    if ($status !== 'success') {
        return $default;
    }

    $settings = comkani_request_settings_get($type);
    $message = trim((string) ($settings['front_success'] ?? ''));

    return $message !== '' ? $message : $default;
}

/**
 * Request send confirmation email : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_send_confirmation_email(array $data): bool
{
    $type = sanitize_key((string) ($data['request_type'] ?? ''));
    $email = sanitize_email((string) ($data['client_email'] ?? ''));

    if ($type === '' || $email === '') {
        return false;
    }

    $settings = comkani_request_settings_get($type);

    if (empty($settings['confirmation_enabled'])) {
        return true;
    }

    $subject = comkani_request_render_template(
        (string) $settings['confirmation_subject'],
        $data
    );
    $body = comkani_request_render_template(
        (string) $settings['confirmation_body'],
        $data
    );

    return wp_mail(
        $email,
        $subject,
        $body,
        ['Content-Type: text/plain; charset=UTF-8']
    );
}

/**
 * Request settings admin page : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_settings_admin_page(): void
{
    if (!current_user_can('comkani_manage_requests')) {
        wp_die('Accès refusé.');
    }

    $types = comkani_request_types();
    $saved = isset($_GET['updated']) && sanitize_key(wp_unslash($_GET['updated'])) === '1';
    ?>
    <div class="wrap">
        <h1>E-mails et confirmations</h1>
        <p>Modifie les messages affichés après envoi, les notifications reçues par l’équipe et les confirmations envoyées aux visiteurs.</p>
        <p><strong>Variables disponibles :</strong> <code>{{site_name}}</code>, <code>{{name}}</code>, <code>{{email}}</code>, <code>{{phone}}</code>, <code>{{company}}</code>, <code>{{position}}</code>, <code>{{subject}}</code>, <code>{{message}}</code>, <code>{{appointment}}</code>, <code>{{request_details}}</code>.</p>

        <?php if ($saved) : ?>
            <div class="notice notice-success is-dismissible"><p>Réglages enregistrés.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="comkani_save_request_settings">
            <?php wp_nonce_field('comkani_save_request_settings', 'comkani_request_settings_nonce'); ?>

            <?php foreach ($types as $type => $definition) : ?>
                <?php
                if (!current_user_can((string) $definition['capability'])) {
                    continue;
                }
                $settings = comkani_request_settings_get($type);
                ?>
                <hr>
                <h2><?php echo esc_html((string) $definition['label']); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($type); ?>-front-success">Notification après envoi</label></th>
                        <td><input class="regular-text" id="<?php echo esc_attr($type); ?>-front-success" name="settings[<?php echo esc_attr($type); ?>][front_success]" type="text" value="<?php echo esc_attr((string) $settings['front_success']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($type); ?>-recipient">E-mail destinataire</label></th>
                        <td>
                            <input class="regular-text" id="<?php echo esc_attr($type); ?>-recipient" name="settings[<?php echo esc_attr($type); ?>][recipient_email]" type="email" value="<?php echo esc_attr((string) $settings['recipient_email']); ?>">
                            <p class="description">Vide = adresse e-mail d’administration WordPress.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($type); ?>-internal-subject">Objet du mail reçu</label></th>
                        <td><input class="large-text" id="<?php echo esc_attr($type); ?>-internal-subject" name="settings[<?php echo esc_attr($type); ?>][internal_subject]" type="text" value="<?php echo esc_attr((string) $settings['internal_subject']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($type); ?>-internal-body">Contenu du mail reçu</label></th>
                        <td><textarea class="large-text code" rows="9" id="<?php echo esc_attr($type); ?>-internal-body" name="settings[<?php echo esc_attr($type); ?>][internal_body]"><?php echo esc_textarea((string) $settings['internal_body']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Mail de confirmation</th>
                        <td>
                            <label>
                                <input name="settings[<?php echo esc_attr($type); ?>][confirmation_enabled]" type="checkbox" value="1" <?php checked(!empty($settings['confirmation_enabled'])); ?>>
                                Envoyer une confirmation automatique au visiteur
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($type); ?>-confirmation-subject">Objet de la confirmation</label></th>
                        <td><input class="large-text" id="<?php echo esc_attr($type); ?>-confirmation-subject" name="settings[<?php echo esc_attr($type); ?>][confirmation_subject]" type="text" value="<?php echo esc_attr((string) $settings['confirmation_subject']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($type); ?>-confirmation-body">Contenu de la confirmation</label></th>
                        <td><textarea class="large-text code" rows="9" id="<?php echo esc_attr($type); ?>-confirmation-body" name="settings[<?php echo esc_attr($type); ?>][confirmation_body]"><?php echo esc_textarea((string) $settings['confirmation_body']); ?></textarea></td>
                    </tr>
                </table>
            <?php endforeach; ?>

            <?php submit_button('Enregistrer les réglages'); ?>
        </form>
    </div>
    <?php
}

/**
 * Request settings save : fonction du module request-settings.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_settings_save(): void
{
    if (!current_user_can('comkani_manage_requests')) {
        wp_die('Accès refusé.');
    }

    check_admin_referer('comkani_save_request_settings', 'comkani_request_settings_nonce');

    $posted = isset($_POST['settings']) && is_array($_POST['settings'])
        ? wp_unslash($_POST['settings'])
        : [];
    $types = comkani_request_types();
    $clean = comkani_request_settings_all();

    foreach ($types as $type => $definition) {
        if (!current_user_can((string) $definition['capability'])) {
            continue;
        }

        $input = isset($posted[$type]) && is_array($posted[$type]) ? $posted[$type] : [];
        $defaults = comkani_request_settings_defaults($type);

        $clean[$type] = [
            'recipient_email' => sanitize_email((string) ($input['recipient_email'] ?? '')),
            'front_success' => sanitize_text_field((string) ($input['front_success'] ?? $defaults['front_success'])),
            'internal_subject' => sanitize_text_field((string) ($input['internal_subject'] ?? $defaults['internal_subject'])),
            'internal_body' => sanitize_textarea_field((string) ($input['internal_body'] ?? $defaults['internal_body'])),
            'confirmation_enabled' => !empty($input['confirmation_enabled']),
            'confirmation_subject' => sanitize_text_field((string) ($input['confirmation_subject'] ?? $defaults['confirmation_subject'])),
            'confirmation_body' => sanitize_textarea_field((string) ($input['confirmation_body'] ?? $defaults['confirmation_body'])),
        ];
    }

    update_option('comkani_request_messages', $clean, false);

    wp_safe_redirect(add_query_arg(
        ['page' => 'comkani-request-settings', 'updated' => '1'],
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_comkani_save_request_settings', 'comkani_request_settings_save');
