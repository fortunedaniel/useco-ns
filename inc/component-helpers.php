<?php

// =============================================================================
// Fichier : inc/component-helpers.php
// Rôle : fournit les helpers partagés des composants front
//
// Objectif :
// - initialiser ou relier une responsabilité technique du thème WordPress
// - conserver une architecture compatible avec un hébergement WordPress classique
//
// Ce qu'il fait :
// - affiche les messages de résultat des formulaires
// - génère une modale native dialog réutilisable
// - affiche les images WordPress y compris les SVG
// - affiche les médias image, SVG ou vidéo avec un rendu commun
// - évite de dupliquer le même HTML entre les composants
//
// Ce qu'il ne fait pas :
// - ne rend pas Node.js nécessaire au fonctionnement du thème en production
// - ne doit pas contenir de logique éditoriale spécifique à une seule page lorsqu’un composant suffit
//
// Fichiers liés :
// - components/
// - resources/ts/shared/modal.ts
// - inc/uploads.php
// =============================================================================
defined('ABSPATH') || exit;

// Génère les attributs HTML simples d'un média
/**
 * Media attributes : fonction du module component-helpers.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_media_attributes(array $attributes): string
{
    $html = '';

    foreach ($attributes as $name => $value) {
        if ($value === null || $value === false) {
            continue;
        }

        $name = sanitize_key((string) $name);

        if ($name === '') {
            continue;
        }

        if ($value === true) {
            $html .= ' ' . esc_attr($name);
            continue;
        }

        $html .= sprintf(
            ' %s="%s"',
            esc_attr($name),
            esc_attr((string) $value)
        );
    }

    return $html;
}

// Retourne une image WordPress compatible avec les SVG
/**
 * Attachment image : fonction du module component-helpers.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_attachment_image(
    int $attachment_id,
    $size = 'full',
    array $attributes = []
): string {
    if ($attachment_id < 1) {
        return '';
    }

    $mime = (string) get_post_mime_type($attachment_id);
    $url = wp_get_attachment_url($attachment_id);
    $extension = $url
        ? strtolower((string) pathinfo(
            (string) parse_url($url, PHP_URL_PATH),
            PATHINFO_EXTENSION
        ))
        : '';
    $is_svg = $mime === 'image/svg+xml'
        || $extension === 'svg';

    if (!$is_svg) {
        $image = wp_get_attachment_image(
            $attachment_id,
            $size,
            false,
            $attributes
        );

        return is_string($image) ? $image : '';
    }

    if (!$url) {
        return '';
    }

    $attributes = array_merge(
        [
            'src' => $url,
            'alt' => (string) get_post_meta(
                $attachment_id,
                '_wp_attachment_image_alt',
                true
            ),
        ],
        $attributes
    );

    return '<img' . comkani_media_attributes($attributes) . '>';
}

// Retourne un média image, SVG ou vidéo
/**
 * Attachment media : fonction du module component-helpers.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_attachment_media(
    int $attachment_id,
    array $attributes = []
): string {
    if ($attachment_id < 1) {
        return '';
    }

    $mime = (string) get_post_mime_type($attachment_id);

    if (strpos($mime, 'video/') === 0) {
        $url = wp_get_attachment_url($attachment_id);

        if (!$url) {
            return '';
        }

        $attributes = array_merge(
            [
                'class' => 'media video',
                'controls' => true,
                'playsinline' => true,
                'preload' => 'metadata',
            ],
            $attributes
        );

        return sprintf(
            '<video%s><source src="%s" type="%s"></video>',
            comkani_media_attributes($attributes),
            esc_url($url),
            esc_attr($mime)
        );
    }

    $url = wp_get_attachment_url($attachment_id);
    $extension = $url
        ? strtolower((string) pathinfo(
            (string) parse_url($url, PHP_URL_PATH),
            PATHINFO_EXTENSION
        ))
        : '';

    if (
        strpos($mime, 'image/') === 0
        || $extension === 'svg'
    ) {
        return comkani_attachment_image(
            $attachment_id,
            'large',
            $attributes
        );
    }

    return '';
}

// Affiche le message de résultat d'un formulaire
/**
 * Request status message : fonction du module component-helpers.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_request_status_message(string $type): void
{
    $status = isset($_GET['request-status'])
        ? sanitize_key(wp_unslash($_GET['request-status']))
        : '';
    $request_type = isset($_GET['request-type'])
        ? sanitize_key(wp_unslash($_GET['request-type']))
        : '';

    if ($status === '' || $request_type !== $type) {
        return;
    }

    $messages = [
        'success' => ['success', 'Votre demande a bien été envoyée.'],
        'saved' => ['success', 'Votre demande a bien été enregistrée. Nous revenons vers vous rapidement.'],
        'validation-error' => ['error', 'Certaines informations sont invalides. Vérifiez le formulaire.'],
        'rate-limited' => ['error', 'Trop de tentatives ont été détectées. Réessayez plus tard.'],
        'invalid' => ['error', 'La demande n’a pas pu être vérifiée. Rechargez la page et réessayez.'],
        'error' => ['error', 'Une erreur est survenue. Réessayez ou contactez-nous directement.'],
    ];

    if (!isset($messages[$status])) {
        return;
    }

    [$class, $message] = $messages[$status];

    if (function_exists('comkani_request_front_message')) {
        $message = comkani_request_front_message($type, $status, $message);
    }

    echo '<p class="comkani-form-notice comkani-form-notice--' . esc_attr($class) . '" role="status">' . esc_html($message) . '</p>';
}

// Génère une modale native réutilisable
/**
 * Render component modal : fonction du module component-helpers.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_render_component_modal(
    string $id,
    string $title,
    callable $callback
): void {
    ?>
    <dialog
        class="comkani-modal"
        data-comkani-modal="<?php echo esc_attr($id); ?>"
        aria-labelledby="comkani-modal-title-<?php echo esc_attr($id); ?>"
    >
        <div class="comkani-modal-inner">
            <button
                type="button"
                class="comkani-modal-close"
                data-comkani-modal-close
                aria-label="Fermer la fenêtre"
            >×</button>

            <h2
                id="comkani-modal-title-<?php echo esc_attr($id); ?>"
                class="sr-only"
            >
                <?php echo esc_html($title); ?>
            </h2>

            <div class="comkani-modal-content">
                <?php $callback(); ?>
            </div>
        </div>
    </dialog>
    <?php
}
