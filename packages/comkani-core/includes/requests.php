<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/requests.php
// Rôle : fournit le moteur commun des demandes Contact, Devis, Booking et Candidature
//
// Ce qu'il fait :
// - crée une table unique pour les demandes du site
// - sécurise, valide et enregistre les soumissions
// - envoie les notifications e-mail
// - gère les statuts, recherches, statistiques et réservations
// - expose les créneaux Booking via l'API REST WordPress
//
// Fichiers liés :
// - components/contact/contact.php
// - components/quote/quote.php
// - components/booking/booking.php
// - components/application/application.php
// - packages/comkani-core/includes/admin.php
// - packages/comkani-core/includes/requests-privacy.php
// =============================================================================
defined('ABSPATH') || exit;

if (!defined('COMKANI_REQUESTS_DB_VERSION')) {
    define('COMKANI_REQUESTS_DB_VERSION', '1.0.0');
}

/**
 * Requests table name : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_table_name(): string
{
    global $wpdb;

    return $wpdb->prefix . 'comkani_requests';
}

/**
 * Requests install : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_install(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = comkani_requests_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        request_type varchar(20) NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        status varchar(30) NOT NULL DEFAULT 'new',
        delivery_status varchar(20) NOT NULL DEFAULT 'pending',
        client_name varchar(120) NOT NULL,
        client_email varchar(254) NOT NULL,
        client_phone varchar(40) NOT NULL DEFAULT '',
        subject varchar(190) NOT NULL DEFAULT '',
        message text NULL,
        appointment_at datetime NULL,
        payload longtext NULL,
        source_url text NULL,
        internal_note text NULL,
        assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        processed_at datetime NULL,
        PRIMARY KEY  (id),
        KEY request_type (request_type),
        KEY status (status),
        KEY created_at (created_at),
        KEY appointment_at (appointment_at),
        KEY client_email (client_email)
    ) {$charset};";

    dbDelta($sql);
    update_option('comkani_requests_db_version', COMKANI_REQUESTS_DB_VERSION, false);
}

/**
 * Requests maybe install : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_maybe_install(): void
{
    global $wpdb;

    $version = (string) get_option('comkani_requests_db_version', '');
    $table = comkani_requests_table_name();
    $existing = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $table)
    );

    if ($version === COMKANI_REQUESTS_DB_VERSION && $existing === $table) {
        return;
    }

    comkani_requests_install();
}

/**
 * Request types : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_types(): array
{
    $types = [];

    if (comkani_core_component_enabled('contact')) {
        $types['contact'] = [
            'label' => 'Contacts',
            'singular' => 'Demande de contact',
            'capability' => 'comkani_manage_contacts',
        ];
    }

    if (comkani_core_component_enabled('quote')) {
        $types['quote'] = [
            'label' => 'Demandes de devis',
            'singular' => 'Demande de devis',
            'capability' => 'comkani_manage_quotes',
        ];
    }

    if (comkani_core_component_enabled('booking')) {
        $types['booking'] = [
            'label' => 'Réservations',
            'singular' => 'Réservation',
            'capability' => 'comkani_manage_bookings',
        ];
    }

    if (comkani_core_component_enabled('application')) {
        $types['application'] = [
            'label' => 'Candidatures',
            'singular' => 'Candidature',
            'capability' => 'comkani_manage_applications',
        ];
    }

    return $types;
}

/**
 * Request statuses : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_statuses(string $type): array
{
    if ($type === 'quote') {
        return [
            'new' => 'À traiter',
            'qualified' => 'Qualifié',
            'in_progress' => 'En cours',
            'sent' => 'Devis envoyé',
            'accepted' => 'Accepté',
            'declined' => 'Refusé',
            'archived' => 'Archivé',
        ];
    }

    if ($type === 'booking') {
        return [
            'new' => 'À traiter',
            'contacted' => 'Contacté',
            'confirmed' => 'Confirmé',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            'archived' => 'Archivé',
        ];
    }

    if ($type === 'application') {
        return [
            'new' => 'À traiter',
            'reviewing' => 'En cours d’étude',
            'contacted' => 'Contacté',
            'interview' => 'Entretien',
            'accepted' => 'Retenu',
            'declined' => 'Non retenu',
            'archived' => 'Archivé',
        ];
    }

    return [
        'new' => 'À traiter',
        'in_progress' => 'En cours',
        'replied' => 'Répondu',
        'closed' => 'Traité',
        'archived' => 'Archivé',
    ];
}

/**
 * Request capability : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_capability(string $type): string
{
    $types = comkani_request_types();

    return isset($types[$type]['capability'])
        ? (string) $types[$type]['capability']
        : 'comkani_manage_project';
}

/**
 * Request get recipient email : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_get_recipient_email(string $type = ''): string
{
    if ($type !== '' && function_exists('comkani_request_settings_get')) {
        $settings = comkani_request_settings_get($type);
        $managed = sanitize_email((string) ($settings['recipient_email'] ?? ''));

        if ($managed !== '') {
            return $managed;
        }
    }

    $configured = sanitize_email((string) comkani_core_get('requests.recipientEmail', ''));

    return $configured !== ''
        ? $configured
        : (string) get_option('admin_email', '');
}

// Prépare les sujets disponibles dans le formulaire Contact
/**
 * Contact subject options : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_contact_subject_options(): array
{
    $options = [];

    foreach ((array) comkani_core_get('contact.subjects', []) as $subject) {
        $subject = is_string($subject)
            ? [
                'value' => sanitize_title($subject),
                'label' => $subject,
            ]
            : (array) $subject;

        $value = sanitize_key((string) ($subject['value'] ?? ''));
        $label = sanitize_text_field((string) ($subject['label'] ?? ''));

        if ($value === '' || $label === '') {
            continue;
        }

        $options[$value] = $label;
    }

    if ($options !== []) {
        return $options;
    }

    // Utilise les services du projet lorsque les sujets ne sont pas configurés
    foreach ((array) comkani_core_get('serviceSections', []) as $section) {
        $section = (array) $section;
        $value = sanitize_key((string) ($section['anchor'] ?? ''));
        $label = sanitize_text_field((string) ($section['label'] ?? ''));

        if ($value === '' || $label === '') {
            continue;
        }

        $options[$value] = $label;
    }

    return $options;
}

/**
 * Request rate limit key : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_rate_limit_key(string $type): string
{
    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? (string) wp_unslash($_SERVER['REMOTE_ADDR'])
        : 'unknown';
    $salt = defined('AUTH_SALT') ? AUTH_SALT : wp_salt('auth');

    return 'comkani_rate_' . hash_hmac('sha256', $type . '|' . $ip, $salt);
}

/**
 * Request rate limit allows : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_rate_limit_allows(string $type): bool
{
    $limit = max(1, (int) comkani_core_get('requests.rateLimitPerHour', 5));
    $key = comkani_request_rate_limit_key($type);
    $count = (int) get_transient($key);

    if ($count >= $limit) {
        return false;
    }

    set_transient($key, $count + 1, HOUR_IN_SECONDS);

    return true;
}

/**
 * Request clean payload : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_clean_payload(array $source): array
{
    $clean = [];
    $excluded = [
        'action',
        'nonce',
        'website',
        'form_started_at',
        'request_type',
        'client_name',
        'client_email',
        'client_phone',
        'subject',
        'message',
        'privacy_consent',
    ];

    foreach ($source as $key => $value) {
        $key = sanitize_key((string) $key);

        if ($key === '' || in_array($key, $excluded, true)) {
            continue;
        }

        if (is_array($value)) {
            $clean[$key] = array_map(
                static fn($item): string => sanitize_text_field((string) $item),
                $value
            );
            continue;
        }

        $clean[$key] = sanitize_textarea_field((string) $value);
    }

    return $clean;
}

/**
 * Email domain is resolvable : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_email_domain_is_resolvable(string $email): bool
{
    $email = sanitize_email($email);

    if ($email === '' || !is_email($email)) {
        return false;
    }

    $at = strrpos($email, '@');

    if ($at === false) {
        return false;
    }

    $domain = strtolower(substr($email, $at + 1));

    if ($domain === '' || !function_exists('checkdnsrr')) {
        return true;
    }

    if (function_exists('idn_to_ascii')) {
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT);

        if (is_string($ascii) && $ascii !== '') {
            $domain = $ascii;
        }
    }

    return checkdnsrr($domain, 'MX')
        || checkdnsrr($domain, 'A')
        || checkdnsrr($domain, 'AAAA');
}

/**
 * Phone is french : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_phone_is_french(string $phone): bool
{
    $phone = trim($phone);

    if ($phone === '') {
        return true;
    }

    $normalized = preg_replace('/[\s.()\-]+/', '', $phone);

    if (!is_string($normalized)) {
        return false;
    }

    return (bool) preg_match('/^(?:0[1-9]\d{8}|\+33[1-9]\d{8}|0033[1-9]\d{8})$/', $normalized);
}

/**
 * Valide les champs communs d'une demande.
 *
 * @param array<string, mixed> $data Données normalisées de la demande.
 * @param bool $require_email Indique si l'adresse e-mail est obligatoire.
 * @return array<string, string> Erreurs de validation indexées par champ.
 */
function comkani_request_validate_common(array $data, bool $require_email = true): array
{
    $errors = [];

    if ($data['client_name'] === '' || mb_strlen($data['client_name']) < 2) {
        $errors['client_name'] = 'Indiquez votre nom.';
    }

    if ($require_email && ($data['client_email'] === '' || !is_email($data['client_email']))) {
        $errors['client_email'] = 'Indiquez une adresse e-mail valide.';
    } elseif ($data['client_email'] !== '' && !is_email($data['client_email'])) {
        $errors['client_email'] = 'Indiquez une adresse e-mail valide.';
    } elseif ($data['client_email'] !== '' && !comkani_email_domain_is_resolvable($data['client_email'])) {
        $errors['client_email'] = 'Le domaine de cette adresse e-mail ne semble pas recevoir d’e-mails.';
    }

    if ($data['client_phone'] !== '' && !comkani_phone_is_french($data['client_phone'])) {
        $errors['client_phone'] = 'Indiquez un numéro français valide, par exemple 06 12 34 56 78 ou +33 6 12 34 56 78.';
    }

    if (mb_strlen($data['message']) > 4000) {
        $errors['message'] = 'Le message est trop long.';
    }

    if (empty($data['privacy_consent'])) {
        $errors['privacy_consent'] = 'Le consentement à la politique de confidentialité est requis.';
    }

    return $errors;
}

// Valide les données envoyées depuis le formulaire Contact
/**
 * Contact validate : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_contact_validate(array $payload, string $subject): array
{
    $errors = [];
    $first_name = trim((string) ($payload['client_first_name'] ?? ''));
    $last_name = trim((string) ($payload['client_last_name'] ?? ''));
    $variant = sanitize_key((string) ($payload['contact_variant'] ?? ''));

    if ($first_name === '' || mb_strlen($first_name) < 2) {
        $errors['client_first_name'] = 'Indiquez votre prénom.';
    }

    if ($variant !== 'whatsapp' && ($last_name === '' || mb_strlen($last_name) < 2)) {
        $errors['client_last_name'] = 'Indiquez votre nom.';
    }

    $subjects = comkani_contact_subject_options();

    if ($subject === '' || !isset($subjects[$subject])) {
        $errors['subject'] = 'Choisissez un sujet valide.';
    }

    return $errors;
}

/**
 * Application allowed file types : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_allowed_file_types(): array
{
    return [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];
}

/**
 * Application validate file : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_validate_file(array $file, string $label, bool $required = true): array
{
    if ($file === [] || !isset($file['error'])) {
        return $required ? [$label . ' est requis.'] : [];
    }

    $error = (int) $file['error'];

    if ($error === UPLOAD_ERR_NO_FILE) {
        return $required ? [$label . ' est requis.'] : [];
    }

    if ($error !== UPLOAD_ERR_OK) {
        return [$label . ' n’a pas pu être envoyé.'];
    }

    $max_size = max(1, (int) comkani_core_get('application.maxFileSizeMb', 5)) * MB_IN_BYTES;
    $size = isset($file['size']) ? (int) $file['size'] : 0;

    if ($size < 1 || $size > $max_size) {
        return [sprintf('%s doit peser au maximum %d Mo.', $label, (int) ($max_size / MB_IN_BYTES))];
    }

    $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
    $name = isset($file['name']) ? sanitize_file_name((string) $file['name']) : '';

    if ($tmp_name === '' || $name === '' || !is_uploaded_file($tmp_name)) {
        return [$label . ' est invalide.'];
    }

    $configured_extensions = array_map(
        'strtolower',
        array_map('strval', (array) comkani_core_get(
            'application.allowedExtensions',
            ['pdf', 'docx', 'jpg', 'jpeg']
        ))
    );
    $known_types = comkani_application_allowed_file_types();
    $allowed_types = array_intersect_key(
        $known_types,
        array_flip($configured_extensions)
    );
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

    if (!isset($allowed_types[$extension])) {
        return [$label . ' doit être au format PDF, DOCX ou JPG.'];
    }

    $check = wp_check_filetype_and_ext(
        $tmp_name,
        $name,
        $allowed_types
    );
    $detected_ext = strtolower((string) ($check['ext'] ?? ''));
    $detected_type = strtolower((string) ($check['type'] ?? ''));
    $expected_type = strtolower((string) $allowed_types[$extension]);

    if (
        $detected_ext === ''
        || !isset($allowed_types[$detected_ext])
        || $detected_type !== $expected_type
    ) {
        return [$label . ' n’est pas un fichier PDF, DOCX ou JPG valide.'];
    }

    return [];
}

/**
 * Application private directory : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_private_directory(): string
{
    $uploads = wp_upload_dir();
    $directory = trailingslashit((string) $uploads['basedir']) . 'comkani-private/applications';

    if (!is_dir($directory)) {
        wp_mkdir_p($directory);
    }

    if (is_dir($directory)) {
        $htaccess = trailingslashit($directory) . '.htaccess';
        $index = trailingslashit($directory) . 'index.php';

        if (!file_exists($htaccess)) {
            @file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            );
        }

        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }

    return $directory;
}

/**
 * Application store file : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_store_file(array $file): array
{
    $directory = comkani_application_private_directory();

    if (!is_dir($directory) || !is_writable($directory)) {
        return [];
    }

    $original_name = sanitize_file_name((string) ($file['name'] ?? 'document.pdf'));
    $extension = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_types = comkani_application_allowed_file_types();

    if (!isset($allowed_types[$extension])) {
        return [];
    }

    $stored_name = wp_generate_uuid4() . '.' . $extension;
    $target = trailingslashit($directory) . $stored_name;
    $tmp_name = (string) ($file['tmp_name'] ?? '');

    if ($tmp_name === '' || !is_uploaded_file($tmp_name) || !move_uploaded_file($tmp_name, $target)) {
        return [];
    }

    @chmod($target, 0640);

    return [
        'name' => $original_name !== '' ? $original_name : 'document.' . $extension,
        'stored' => $stored_name,
    ];
}

/**
 * Application file path : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_file_path(array $file): string
{
    $stored = sanitize_file_name((string) ($file['stored'] ?? ''));

    if ($stored === '' || basename($stored) !== $stored) {
        return '';
    }

    $path = trailingslashit(comkani_application_private_directory()) . $stored;

    return is_file($path) ? $path : '';
}

/**
 * Application validate : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_validate(array $payload, array $files): array
{
    $errors = [];
    $first_name = trim((string) ($payload['client_first_name'] ?? ''));
    $last_name = trim((string) ($payload['client_last_name'] ?? ''));
    $position = trim((string) ($payload['application_position'] ?? ''));

    if ($first_name === '' || mb_strlen($first_name) < 2) {
        $errors['client_first_name'] = 'Indiquez votre prénom.';
    }

    if ($last_name === '' || mb_strlen($last_name) < 2) {
        $errors['client_last_name'] = 'Indiquez votre nom.';
    }

    if ($position === '' || mb_strlen($position) < 2) {
        $errors['application_position'] = 'Indiquez le poste ou le type de mission recherché.';
    }

    $cv_errors = comkani_application_validate_file(
        is_array($files['application_cv'] ?? null) ? $files['application_cv'] : [],
        'Le CV',
        (bool) comkani_core_get('application.cvRequired', true)
    );
    $cover_errors = comkani_application_validate_file(
        is_array($files['application_cover_letter'] ?? null) ? $files['application_cover_letter'] : [],
        'La lettre de motivation',
        (bool) comkani_core_get('application.coverLetterRequired', true)
    );

    if ($cv_errors !== []) {
        $errors['application_cv'] = implode(' ', $cv_errors);
    }

    if ($cover_errors !== []) {
        $errors['application_cover_letter'] = implode(' ', $cover_errors);
    }

    return $errors;
}

/**
 * Application delete payload files : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_delete_payload_files(array $payload): void
{
    foreach (['application_cv', 'application_cover_letter'] as $key) {
        $file = is_array($payload[$key] ?? null) ? $payload[$key] : [];
        $path = comkani_application_file_path($file);

        if ($path !== '') {
            @unlink($path);
        }
    }
}

/**
 * Application mail attachments : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_mail_attachments(array $payload): array
{
    $attachments = [];

    foreach (['application_cv', 'application_cover_letter'] as $key) {
        $file = is_array($payload[$key] ?? null) ? $payload[$key] : [];
        $path = comkani_application_file_path($file);

        if ($path !== '') {
            $attachments[] = $path;
        }
    }

    return $attachments;
}

/**
 * Booking parse datetime : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_booking_parse_datetime(string $date, string $time): ?DateTimeImmutable
{
    if ($date === '' || $time === '') {
        return null;
    }

    $datetime = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i',
        $date . ' ' . $time,
        wp_timezone()
    );

    $errors = DateTimeImmutable::getLastErrors();

    if ($datetime === false) {
        return null;
    }

    if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }

    return $datetime;
}

/**
 * Booking slot is available : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_booking_slot_is_available(string $date, string $time): bool
{
    global $wpdb;

    $datetime = comkani_booking_parse_datetime($date, $time);

    if (!$datetime) {
        return false;
    }

    $capacity = max(1, (int) comkani_core_get('booking.capacityPerSlot', 1));
    $table = comkani_requests_table_name();
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE request_type = 'booking' AND appointment_at = %s AND status NOT IN ('cancelled', 'archived')",
            $datetime->format('Y-m-d H:i:s')
        )
    );

    return $count < $capacity;
}

/**
 * Booking validate : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_booking_validate(array $payload): array
{
    $errors = [];
    $date = sanitize_text_field((string) ($payload['booking_date'] ?? ''));
    $time = sanitize_text_field((string) ($payload['booking_time'] ?? ''));
    $datetime = comkani_booking_parse_datetime($date, $time);
    $weekdays = array_map('intval', (array) comkani_core_get('booking.availableWeekdays', []));
    $slots = array_map('strval', (array) comkani_core_get('booking.timeSlots', []));

    if (!$datetime) {
        $errors['booking_date'] = 'Choisissez une date valide.';
        return $errors;
    }

    if (!in_array((int) $datetime->format('N'), $weekdays, true)) {
        $errors['booking_date'] = 'Cette journée n’est pas disponible.';
    }

    if (!in_array($time, $slots, true)) {
        $errors['booking_time'] = 'Choisissez un créneau valide.';
    }

    $minimum = current_datetime()->modify(
        '+' . max(0, (int) comkani_core_get('booking.minLeadHours', 4)) . ' hours'
    );
    $maximum = current_datetime()->modify(
        '+' . max(1, (int) comkani_core_get('booking.maxDaysAhead', 60)) . ' days'
    );

    if ($datetime < $minimum) {
        $errors['booking_time'] = 'Ce créneau est trop proche ou déjà passé.';
    }

    if ($datetime > $maximum) {
        $errors['booking_date'] = 'Cette date est trop éloignée.';
    }

    if ($errors === [] && !comkani_booking_slot_is_available($date, $time)) {
        $errors['booking_time'] = 'Ce créneau n’est plus disponible.';
    }

    return $errors;
}

/**
 * Request insert : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_insert(array $data): int
{
    global $wpdb;

    $now = current_time('mysql');
    $appointment = $data['appointment_at'] instanceof DateTimeImmutable
        ? $data['appointment_at']->format('Y-m-d H:i:s')
        : null;

    $inserted = $wpdb->insert(
        comkani_requests_table_name(),
        [
            'request_type' => $data['request_type'],
            'created_at' => $now,
            'updated_at' => $now,
            'status' => 'new',
            'delivery_status' => 'pending',
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'],
            'client_phone' => $data['client_phone'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'appointment_at' => $appointment,
            'payload' => wp_json_encode($data['payload'], JSON_UNESCAPED_UNICODE),
            'source_url' => esc_url_raw($data['source_url']),
            'internal_note' => '',
            'assigned_user_id' => 0,
            'processed_at' => null,
        ],
        [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%d', '%s',
        ]
    );

    return $inserted === false ? 0 : (int) $wpdb->insert_id;
}

/**
 * Request mail message : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_mail_message(array $data): string
{
    $lines = [
        'Type : ' . $data['request_type'],
        'Nom : ' . $data['client_name'],
        'E-mail : ' . $data['client_email'],
    ];

    if ($data['client_phone'] !== '') {
        $lines[] = 'Téléphone : ' . $data['client_phone'];
    }

    $payload = is_array($data['payload'] ?? null)
        ? $data['payload']
        : [];

    if (!empty($payload['client_company'])) {
        $lines[] = 'Entreprise : ' . $payload['client_company'];
    }

    if (!empty($payload['client_position'])) {
        $lines[] = 'Poste : ' . $payload['client_position'];
    }

    if (!empty($payload['application_position'])) {
        $lines[] = 'Poste recherché : ' . $payload['application_position'];
    }

    if (!empty($payload['application_availability'])) {
        $lines[] = 'Disponibilité : ' . $payload['application_availability'];
    }

    if ($data['appointment_at'] instanceof DateTimeImmutable) {
        $lines[] = 'Rendez-vous : ' . wp_date(
            'd/m/Y à H:i',
            $data['appointment_at']->getTimestamp(),
            wp_timezone()
        );
    }

    if ($data['subject'] !== '') {
        $lines[] = 'Sujet : ' . $data['subject'];
    }

    if ($data['message'] !== '') {
        $lines[] = '';
        $lines[] = 'Message :';
        $lines[] = $data['message'];
    }

    return implode("\n", $lines);
}

/**
 * Request send email : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_send_email(array $data): bool
{
    $type = sanitize_key((string) ($data['request_type'] ?? ''));
    $recipient = comkani_request_get_recipient_email($type);

    if ($recipient === '') {
        return false;
    }

    $types = comkani_request_types();
    $request_label = isset($types[$type]['singular'])
        ? (string) $types[$type]['singular']
        : $type;

    $subject = sprintf(
        '[%s] %s',
        get_bloginfo('name'),
        $request_label
    );
    $message = comkani_request_mail_message($data);

    if (function_exists('comkani_request_settings_get')) {
        $settings = comkani_request_settings_get($type);

        if (function_exists('comkani_request_render_template')) {
            $subject = comkani_request_render_template(
                (string) ($settings['internal_subject'] ?? $subject),
                $data
            );
            $message = comkani_request_render_template(
                (string) ($settings['internal_body'] ?? $message),
                $data
            );
        }
    }

    $attachments = $type === 'application'
        ? comkani_application_mail_attachments((array) ($data['payload'] ?? []))
        : [];

    return wp_mail(
        $recipient,
        $subject,
        $message,
        [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf(
                'Reply-To: %s <%s>',
                $data['client_name'],
                $data['client_email']
            ),
        ],
        $attachments
    );
}

/**
 * Request redirect : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_redirect(string $status, string $type): void
{
    $fallback = home_url('/');
    $referer = wp_get_referer();
    $url = $referer
        ? wp_validate_redirect($referer, $fallback)
        : $fallback;

    $url = remove_query_arg(['request-status', 'request-type'], $url);
    $url = add_query_arg([
        'request-status' => $status,
        'request-type' => $type,
    ], $url);

    wp_safe_redirect($url);
    exit;
}

/**
 * Handle request submission : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_handle_request_submission(): void
{
    if (
        !isset($_SERVER['REQUEST_METHOD'])
        || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST'
    ) {
        wp_die(
            'Méthode non autorisée.',
            'Requête invalide',
            ['response' => 405]
        );
    }

    $post = wp_unslash($_POST);
    $files = $_FILES;
    $type = sanitize_key((string) ($post['request_type'] ?? ''));
    $types = comkani_request_types();

    if (!isset($types[$type])) {
        wp_die(
            'Type de demande invalide.',
            'Requête invalide',
            ['response' => 400]
        );
    }

    // Vérifie les protections de la requête
    $nonce = sanitize_text_field((string) ($post['nonce'] ?? ''));

    if ($nonce === '' || !wp_verify_nonce($nonce, 'comkani_submit_' . $type)) {
        comkani_request_redirect('invalid', $type);
    }

    if (!empty($post['website'])) {
        comkani_request_redirect('success', $type);
    }

    $started_at = absint($post['form_started_at'] ?? 0);

    if ($started_at === 0 || time() - $started_at < 3) {
        comkani_request_redirect('invalid', $type);
    }

    if (!comkani_request_rate_limit_allows($type)) {
        comkani_request_redirect('rate-limited', $type);
    }

    // Prépare les données communes de la demande
    $payload = comkani_request_clean_payload($post);
    $client_name = sanitize_text_field((string) ($post['client_name'] ?? ''));
    $subject = sanitize_text_field((string) ($post['subject'] ?? ''));
    $contact_subject = '';

    // Prépare les données spécifiques au formulaire Contact
    if ($type === 'contact') {
        $first_name = sanitize_text_field(
            (string) ($post['client_first_name'] ?? '')
        );
        $last_name = sanitize_text_field(
            (string) ($post['client_last_name'] ?? '')
        );

        $client_name = trim($first_name . ' ' . $last_name);
        $contact_subject = sanitize_key((string) ($post['subject'] ?? ''));
        $subjects = comkani_contact_subject_options();
        $subject = isset($subjects[$contact_subject])
            ? $subjects[$contact_subject]
            : '';

        $payload['contact_subject'] = $contact_subject;
        $payload['contact_subject_label'] = $subject;
    }

    // Prépare les données spécifiques au formulaire Candidature
    if ($type === 'application') {
        $first_name = sanitize_text_field(
            (string) ($post['client_first_name'] ?? '')
        );
        $last_name = sanitize_text_field(
            (string) ($post['client_last_name'] ?? '')
        );
        $position = sanitize_text_field(
            (string) ($post['application_position'] ?? '')
        );

        $client_name = trim($first_name . ' ' . $last_name);
        $subject = $position !== ''
            ? 'Candidature - ' . $position
            : 'Candidature';
    }

    $data = [
        'request_type' => $type,
        'client_name' => $client_name,
        'client_email' => sanitize_email((string) ($post['client_email'] ?? '')),
        'client_phone' => sanitize_text_field((string) ($post['client_phone'] ?? '')),
        'subject' => $subject,
        'message' => sanitize_textarea_field((string) ($post['message'] ?? '')),
        'privacy_consent' => !empty($post['privacy_consent']),
        'appointment_at' => null,
        'payload' => $payload,
        'source_url' => wp_get_referer() ?: home_url('/'),
    ];

    // Valide les données avant enregistrement
    $contact_variant = $type === 'contact'
        ? sanitize_key((string) ($payload['contact_variant'] ?? ''))
        : '';
    $errors = comkani_request_validate_common(
        $data,
        $contact_variant !== 'whatsapp'
    );

    if (
        $contact_variant === 'whatsapp'
        && $data['client_email'] === ''
        && $data['client_phone'] === ''
    ) {
        $errors['client_contact'] = 'Indiquez au moins un e-mail ou un téléphone.';
    }

    if ($type === 'contact') {
        $errors = array_merge(
            $errors,
            comkani_contact_validate($payload, $contact_subject)
        );
    }

    if ($type === 'booking') {
        $booking_errors = comkani_booking_validate($payload);
        $errors = array_merge($errors, $booking_errors);
        $data['appointment_at'] = comkani_booking_parse_datetime(
            sanitize_text_field((string) ($post['booking_date'] ?? '')),
            sanitize_text_field((string) ($post['booking_time'] ?? ''))
        );
    }

    if ($type === 'quote' && mb_strlen((string) ($payload['project_type'] ?? '')) < 2) {
        $errors['project_type'] = 'Précisez le type de projet.';
    }

    if ($type === 'application') {
        $errors = array_merge(
            $errors,
            comkani_application_validate($payload, $files)
        );
    }

    if ($errors !== []) {
        comkani_request_redirect('validation-error', $type);
    }

    // Stocke les documents de candidature uniquement après validation
    if ($type === 'application') {
        $cv = comkani_application_store_file(
            is_array($files['application_cv'] ?? null) ? $files['application_cv'] : []
        );
        $cover_letter = comkani_application_store_file(
            is_array($files['application_cover_letter'] ?? null) ? $files['application_cover_letter'] : []
        );

        if ($cv === [] || $cover_letter === []) {
            comkani_application_delete_payload_files([
                'application_cv' => $cv,
                'application_cover_letter' => $cover_letter,
            ]);
            comkani_request_redirect('error', $type);
        }

        $payload['application_cv'] = $cv;
        $payload['application_cover_letter'] = $cover_letter;
        $data['payload'] = $payload;
    }

    // Enregistre la demande validée
    $request_id = comkani_request_insert($data);

    if ($request_id < 1) {
        if ($type === 'application') {
            comkani_application_delete_payload_files($payload);
        }
        comkani_request_redirect('error', $type);
    }

    // Envoie la notification interne liée à la demande
    $sent = comkani_request_send_email($data);
    comkani_request_update_delivery($request_id, $sent ? 'sent' : 'failed');

    // Envoie indépendamment l'accusé de réception au visiteur.
    // Une confirmation en échec ne supprime jamais la demande enregistrée.
    if (function_exists('comkani_request_send_confirmation_email')) {
        comkani_request_send_confirmation_email($data);
    }

    comkani_request_redirect($sent ? 'success' : 'saved', $type);
}

add_action('admin_post_comkani_submit_request', 'comkani_handle_request_submission');
add_action('admin_post_nopriv_comkani_submit_request', 'comkani_handle_request_submission');

/**
 * Request update delivery : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_update_delivery(int $id, string $status): bool
{
    global $wpdb;

    if (!in_array($status, ['pending', 'sent', 'failed'], true)) {
        return false;
    }

    return $wpdb->update(
        comkani_requests_table_name(),
        [
            'delivery_status' => $status,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $id],
        ['%s', '%s'],
        ['%d']
    ) !== false;
}

/**
 * Request get : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_get(int $id): ?object
{
    global $wpdb;

    if ($id < 1) {
        return null;
    }

    $table = comkani_requests_table_name();
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id)
    );

    return is_object($row) ? $row : null;
}

/**
 * Request update admin : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_update_admin(int $id, string $status, string $note): bool
{
    global $wpdb;

    $request = comkani_request_get($id);

    if (!$request) {
        return false;
    }

    $statuses = comkani_request_statuses((string) $request->request_type);

    if (!isset($statuses[$status])) {
        return false;
    }

    $processed_at = in_array($status, ['closed', 'accepted', 'declined', 'completed', 'cancelled', 'archived'], true)
        ? current_time('mysql')
        : null;

    $updated = $wpdb->update(
        comkani_requests_table_name(),
        [
            'status' => $status,
            'internal_note' => sanitize_textarea_field($note),
            'updated_at' => current_time('mysql'),
            'assigned_user_id' => get_current_user_id(),
            'processed_at' => $processed_at,
        ],
        ['id' => $id],
        ['%s', '%s', '%s', '%d', '%s'],
        ['%d']
    ) !== false;

    if ($updated && function_exists('comkani_activity_log')) {
        comkani_activity_log(
            'request_updated',
            (string) $request->request_type,
            $id,
            (string) $request->client_name,
            ['status' => $status]
        );
    }

    return $updated;
}

/**
 * Request delete : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_delete(int $id): bool
{
    global $wpdb;

    $request = comkani_request_get($id);

    if ($request && (string) $request->request_type === 'application') {
        $payload = json_decode((string) $request->payload, true);
        comkani_application_delete_payload_files(is_array($payload) ? $payload : []);
    }

    return $wpdb->delete(
        comkani_requests_table_name(),
        ['id' => $id],
        ['%d']
    ) !== false;
}

/**
 * Application download : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_application_download(): void
{
    $request_id = isset($_GET['request_id']) ? absint($_GET['request_id']) : 0;
    $file_key = isset($_GET['file']) ? sanitize_key(wp_unslash($_GET['file'])) : '';
    $request = comkani_request_get($request_id);

    if (
        !$request
        || (string) $request->request_type !== 'application'
        || !current_user_can('comkani_manage_applications')
    ) {
        wp_die('Action non autorisée.');
    }

    if (!in_array($file_key, ['application_cv', 'application_cover_letter'], true)) {
        wp_die('Document invalide.');
    }

    check_admin_referer('comkani_application_download_' . $request_id . '_' . $file_key);

    $payload = json_decode((string) $request->payload, true);
    $payload = is_array($payload) ? $payload : [];
    $file = is_array($payload[$file_key] ?? null) ? $payload[$file_key] : [];
    $path = comkani_application_file_path($file);

    if ($path === '') {
        wp_die('Document introuvable.');
    }

    $download_name = sanitize_file_name((string) ($file['name'] ?? 'document.pdf'));

    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . rawurlencode($download_name) . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

add_action('admin_post_comkani_application_download', 'comkani_application_download');

/**
 * Requests stats : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_stats(string $type): array
{
    global $wpdb;

    $table = comkani_requests_table_name();
    $today = current_time('Y-m-d');
    $month = current_time('Y-m') . '-01';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT
                COUNT(*) total,
                SUM(status = 'new') pending,
                SUM(created_at >= %s) today_count,
                SUM(created_at >= %s) month_count
            FROM {$table}
            WHERE request_type = %s",
            $today . ' 00:00:00',
            $month . ' 00:00:00',
            $type
        ),
        ARRAY_A
    );

    return [
        'total' => (int) ($row['total'] ?? 0),
        'pending' => (int) ($row['pending'] ?? 0),
        'today' => (int) ($row['today_count'] ?? 0),
        'month' => (int) ($row['month_count'] ?? 0),
    ];
}

/**
 * Requests list : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_requests_list(string $type, string $status, string $search, int $page, int $per_page = 20): array
{
    global $wpdb;

    $table = comkani_requests_table_name();
    $where = ['request_type = %s'];
    $values = [$type];

    if ($status !== '') {
        $where[] = 'status = %s';
        $values[] = $status;
    }

    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(
            client_name LIKE %s
            OR client_email LIKE %s
            OR client_phone LIKE %s
            OR subject LIKE %s
            OR message LIKE %s
            OR payload LIKE %s
        )';
        array_push(
            $values,
            $like,
            $like,
            $like,
            $like,
            $like,
            $like
        );
    }

    $where_sql = implode(' AND ', $where);
    $count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", ...$values);
    $total = (int) $wpdb->get_var($count_sql);
    $page = max(1, $page);
    $offset = ($page - 1) * $per_page;
    $list_values = array_merge($values, [$per_page, $offset]);
    $list_sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        ...$list_values
    );
    $items = $wpdb->get_results($list_sql);

    return [
        'items' => is_array($items) ? $items : [],
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $per_page)),
    ];
}

/**
 * Booking rest slots : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_booking_rest_slots(WP_REST_Request $request): WP_REST_Response
{
    $date = sanitize_text_field((string) $request->get_param('date'));
    $date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());

    if (!$date_object || $date_object->format('Y-m-d') !== $date) {
        return new WP_REST_Response(['slots' => []], 200);
    }

    $weekdays = array_map('intval', (array) comkani_core_get('booking.availableWeekdays', []));

    if (!in_array((int) $date_object->format('N'), $weekdays, true)) {
        return new WP_REST_Response(['slots' => []], 200);
    }

    $slots = [];

    foreach ((array) comkani_core_get('booking.timeSlots', []) as $time) {
        $time = (string) $time;
        $datetime = comkani_booking_parse_datetime($date, $time);

        if (!$datetime) {
            continue;
        }

        $minimum = current_datetime()->modify(
            '+' . max(0, (int) comkani_core_get('booking.minLeadHours', 4)) . ' hours'
        );
        $maximum = current_datetime()->modify(
            '+' . max(1, (int) comkani_core_get('booking.maxDaysAhead', 60)) . ' days'
        );

        if ($datetime < $minimum || $datetime > $maximum) {
            continue;
        }

        if (comkani_booking_slot_is_available($date, $time)) {
            $slots[] = $time;
        }
    }

    return new WP_REST_Response(['slots' => $slots], 200);
}

/**
 * Booking register rest routes : fonction du module requests.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_booking_register_rest_routes(): void
{
    if (!comkani_core_component_enabled('booking')) {
        return;
    }

    register_rest_route('comkani/v1', '/booking/slots', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'comkani_booking_rest_slots',
        'permission_callback' => '__return_true',
        'args' => [
            'date' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
}

add_action('rest_api_init', 'comkani_booking_register_rest_routes');
