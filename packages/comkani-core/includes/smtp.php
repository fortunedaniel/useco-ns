<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/smtp.php
// Rôle : configurer PHPMailer utilisé par WordPress pour l'envoi des e-mails
//
// Objectif :
// - permettre aux projets ComKani d'utiliser un serveur SMTP sans plugin
// - conserver les identifiants sensibles dans wp-config.php
// - centraliser le transport e-mail pour tous les appels à wp_mail()
//
// Ce qu'il fait :
// - lit les constantes COMKANI_SMTP_*
// - configure PHPMailer via phpmailer_init
// - applique le serveur, le port, le chiffrement et l'authentification
// - définit l'adresse et le nom d'expédition
//
// Ce qu'il ne fait pas :
// - ne crée pas les comptes e-mail
// - ne configure pas SPF, DKIM ou DMARC
// - ne stocke aucun mot de passe dans WordPress
// - ne remplace pas la logique métier des formulaires
//
// Fichiers liés :
// - wp-config.php
// - packages/comkani-core/comkani-core.php
// - packages/comkani-core/includes/requests.php
// =============================================================================

defined('ABSPATH') || exit;

/**
 * Indique si le SMTP ComKani est activé.
 *
 * @return bool
 */
function comkani_smtp_enabled(): bool
{
    return defined('COMKANI_SMTP_ENABLED')
        && COMKANI_SMTP_ENABLED === true;
}

/**
 * Configure PHPMailer avec les constantes définies dans wp-config.php.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Instance PHPMailer WordPress.
 * @return void
 */
function comkani_smtp_configure(
    PHPMailer\PHPMailer\PHPMailer $phpmailer
): void {
    if (!comkani_smtp_enabled()) {
        return;
    }

    if (
        !defined('COMKANI_SMTP_HOST')
        || !defined('COMKANI_SMTP_PORT')
        || !defined('COMKANI_SMTP_USERNAME')
        || !defined('COMKANI_SMTP_PASSWORD')
    ) {
        return;
    }

    $phpmailer->isSMTP();

    $phpmailer->Host =
        (string) COMKANI_SMTP_HOST;

    $phpmailer->Port =
        (int) COMKANI_SMTP_PORT;

    $phpmailer->SMTPAuth = true;

    $phpmailer->Username =
        (string) COMKANI_SMTP_USERNAME;

    $phpmailer->Password =
        (string) COMKANI_SMTP_PASSWORD;

    if (
        defined('COMKANI_SMTP_ENCRYPTION')
        && COMKANI_SMTP_ENCRYPTION !== ''
    ) {
        $phpmailer->SMTPSecure =
            (string) COMKANI_SMTP_ENCRYPTION;
    }

    if (
        defined('COMKANI_SMTP_FROM_EMAIL')
        && COMKANI_SMTP_FROM_EMAIL !== ''
    ) {
        $phpmailer->From =
            sanitize_email(
                (string) COMKANI_SMTP_FROM_EMAIL
            );
    }

    if (
        defined('COMKANI_SMTP_FROM_NAME')
        && COMKANI_SMTP_FROM_NAME !== ''
    ) {
        $phpmailer->FromName =
            sanitize_text_field(
                (string) COMKANI_SMTP_FROM_NAME
            );
    }
}
add_action(
    'phpmailer_init',
    'comkani_smtp_configure'
);

/**
 * Force l'adresse d'expédition WordPress.
 *
 * @param string $email Adresse d'expédition par défaut.
 * @return string
 */
function comkani_smtp_from_email(string $email): string
{
    if (
        !comkani_smtp_enabled()
        || !defined('COMKANI_SMTP_FROM_EMAIL')
        || COMKANI_SMTP_FROM_EMAIL === ''
    ) {
        return $email;
    }

    return sanitize_email(
        (string) COMKANI_SMTP_FROM_EMAIL
    );
}
add_filter(
    'wp_mail_from',
    'comkani_smtp_from_email'
);

/**
 * Force le nom d'expédition WordPress.
 *
 * @param string $name Nom d'expédition par défaut.
 * @return string
 */
function comkani_smtp_from_name(string $name): string
{
    if (
        !comkani_smtp_enabled()
        || !defined('COMKANI_SMTP_FROM_NAME')
        || COMKANI_SMTP_FROM_NAME === ''
    ) {
        return $name;
    }

    return sanitize_text_field(
        (string) COMKANI_SMTP_FROM_NAME
    );
}
add_filter(
    'wp_mail_from_name',
    'comkani_smtp_from_name'
);