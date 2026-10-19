<?php

// =============================================================================
// Fichier : inc/uploads.php
// Rôle : sécurise les formats de fichiers techniques du Starter ComKani
//
// Objectif :
// - initialiser ou relier une responsabilité technique du thème WordPress
// - conserver une architecture compatible avec un hébergement WordPress classique
//
// Ce qu'il fait :
// - conserve tous les formats WordPress déjà autorisés
// - autorise SVG et ZIP uniquement pour l'équipe ComKani / administrateurs
// - contrôle le contenu des SVG avant leur enregistrement
// - corrige la détection MIME des SVG validés par WordPress
//
// Ce qu'il ne fait pas :
// - ne remplace pas la liste native des formats WordPress
// - ne modifie pas les fichiers déjà présents dans la médiathèque
// - n'autorise pas SVG et ZIP pour les comptes clients standards
// Fichiers liés :
// - voir les imports, appels et chemins référencés directement dans ce fichier
//
// =============================================================================
defined('ABSPATH') || exit;

// Vérifie si l'utilisateur peut téléverser des fichiers techniques
/**
 * Can upload technical files : fonction du module uploads.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_can_upload_technical_files(): bool
{
    return current_user_can('comkani_manage_project')
        || current_user_can('manage_options');
}

// Vérifie si un format technique est activé pour le projet
/**
 * Technical upload enabled : fonction du module uploads.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_technical_upload_enabled(string $extension): bool
{
    if (!comkani_can_upload_technical_files()) {
        return false;
    }

    $config_keys = [
        'svg' => 'security.allowSvgForAgency',
        'zip' => 'security.allowZipForAgency',
    ];
    $config_key = $config_keys[$extension] ?? '';

    return $config_key !== ''
        && (bool) comkani_project_get($config_key, false);
}

// Ajoute uniquement les formats techniques activés pour le projet
/**
 * Allowed upload mimes : fonction du module uploads.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_allowed_upload_mimes(array $mimes): array
{
    unset($mimes['svg'], $mimes['zip']);

    if (comkani_technical_upload_enabled('svg')) {
        $mimes['svg'] = 'image/svg+xml';
    }

    if (comkani_technical_upload_enabled('zip')) {
        $mimes['zip'] = 'application/zip';
    }

    return $mimes;
}

add_filter('upload_mimes', 'comkani_allowed_upload_mimes', 100);

// Vérifie le contenu du SVG avant son enregistrement
/**
 * Validate svg file : fonction du module uploads.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_validate_svg_file(string $file_path)
{
    if (!is_readable($file_path)) {
        return new WP_Error(
            'comkani_svg_unreadable',
            'Le fichier SVG ne peut pas être lu.'
        );
    }

    $contents = file_get_contents($file_path);

    if ($contents === false || trim($contents) === '') {
        return new WP_Error(
            'comkani_svg_empty',
            'Le fichier SVG est vide ou invalide.'
        );
    }

    if (strlen($contents) > 5 * MB_IN_BYTES) {
        return new WP_Error(
            'comkani_svg_too_large',
            'Le fichier SVG dépasse la taille maximale autorisée de 5 Mo.'
        );
    }

    if (
        preg_match('/<!DOCTYPE/i', $contents)
        || preg_match('/<!ENTITY/i', $contents)
    ) {
        return new WP_Error(
            'comkani_svg_doctype',
            'Le SVG contient une déclaration XML non autorisée.'
        );
    }

    if (!class_exists('DOMDocument')) {
        return new WP_Error(
            'comkani_svg_dom_missing',
            'Le serveur ne permet pas de contrôler ce fichier SVG de manière sécurisée.'
        );
    }

    $previous = libxml_use_internal_errors(true);

    $document = new DOMDocument();
    $loaded = $document->loadXML(
        $contents,
        LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || !$document->documentElement) {
        return new WP_Error(
            'comkani_svg_invalid_xml',
            'Le fichier SVG contient un XML invalide.'
        );
    }

    if (strtolower($document->documentElement->localName) !== 'svg') {
        return new WP_Error(
            'comkani_svg_invalid_root',
            'Le fichier ne contient pas une racine SVG valide.'
        );
    }

    $forbidden_elements = [
        'script',
        'foreignobject',
        'iframe',
        'object',
        'embed',
        'audio',
        'video',
        'canvas',
        'applet',
    ];

    $xpath = new DOMXPath($document);

    foreach ($xpath->query('//*') as $element) {
        if (!$element instanceof DOMElement) {
            continue;
        }

        $element_name = strtolower($element->localName);

        if (in_array($element_name, $forbidden_elements, true)) {
            return new WP_Error(
                'comkani_svg_forbidden_element',
                sprintf(
                    'Le SVG contient un élément non autorisé : %s.',
                    $element->localName
                )
            );
        }

        if (!$element->hasAttributes()) {
            continue;
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (!$attribute instanceof DOMAttr) {
                continue;
            }

            $attribute_name = strtolower($attribute->name);
            $attribute_value = trim($attribute->value);

            if (strpos($attribute_name, 'on') === 0) {
                return new WP_Error(
                    'comkani_svg_event_attribute',
                    sprintf(
                        'Le SVG contient un attribut d’événement non autorisé : %s.',
                        $attribute->name
                    )
                );
            }

            if (
                preg_match('/^\s*javascript\s*:/i', $attribute_value)
                || preg_match('/data\s*:\s*text\/html/i', $attribute_value)
            ) {
                return new WP_Error(
                    'comkani_svg_unsafe_protocol',
                    'Le SVG contient une URL ou un protocole non autorisé.'
                );
            }

            if (
                in_array($attribute_name, ['href', 'xlink:href', 'src'], true)
                && $attribute_value !== ''
                && strpos($attribute_value, '#') !== 0
                && !preg_match(
                    '/^data:image\/(?:png|jpe?g|gif|webp);base64,/i',
                    $attribute_value
                )
            ) {
                return new WP_Error(
                    'comkani_svg_external_resource',
                    'Le SVG contient une ressource externe non autorisée.'
                );
            }

            if (
                $attribute_name === 'style'
                && preg_match(
                    '/(?:javascript\s*:|expression\s*\(|url\s*\(\s*[\'"]?\s*(?:https?:|\/\/|data:text\/html))/i',
                    $attribute_value
                )
            ) {
                return new WP_Error(
                    'comkani_svg_unsafe_style',
                    'Le SVG contient un style non autorisé.'
                );
            }
        }
    }

    foreach ($xpath->query('//style') as $style_node) {
        $style = (string) $style_node->textContent;

        if (
            preg_match(
                '/(?:javascript\s*:|expression\s*\(|url\s*\(\s*[\'"]?\s*(?:https?:|\/\/|data:text\/html))/i',
                $style
            )
        ) {
            return new WP_Error(
                'comkani_svg_unsafe_stylesheet',
                'Le SVG contient une feuille de style non autorisée.'
            );
        }
    }

    return true;
}

// Bloque les fichiers techniques non autorisés avant leur déplacement
/**
 * Upload prefilter : fonction du module uploads.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_upload_prefilter(array $file): array
{
    $extension = strtolower(
        (string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)
    );

    if (!in_array($extension, ['svg', 'zip'], true)) {
        return $file;
    }

    if (!comkani_technical_upload_enabled($extension)) {
        $file['error'] = 'Ce type de fichier n’est pas autorisé pour ce projet.';

        return $file;
    }

    if ($extension === 'svg') {
        $validation = comkani_validate_svg_file((string) ($file['tmp_name'] ?? ''));

        if (is_wp_error($validation)) {
            $file['error'] = $validation->get_error_message();
        }
    }

    return $file;
}

add_filter('wp_handle_upload_prefilter', 'comkani_upload_prefilter', 20);

// Aide WordPress à reconnaître les SVG déjà validés
/**
 * Check filetype and ext : fonction du module uploads.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_check_filetype_and_ext(
    array $data,
    string $file,
    string $filename,
    ?array $mimes = null,
    $real_mime = false
): array {
    if (!comkani_technical_upload_enabled('svg')) {
        return $data;
    }

    $extension = strtolower(
        (string) pathinfo($filename, PATHINFO_EXTENSION)
    );

    if ($extension !== 'svg') {
        return $data;
    }

    $validation = comkani_validate_svg_file($file);

    if (is_wp_error($validation)) {
        return $data;
    }

    $data['ext'] = 'svg';
    $data['type'] = 'image/svg+xml';
    $data['proper_filename'] = false;

    return $data;
}

add_filter(
    'wp_check_filetype_and_ext',
    'comkani_check_filetype_and_ext',
    20,
    5
);

// Autorise les SVG validés malgré le contrôle des formats image
/**
 * Allow supported technical mime upload : fonction du module uploads.
 *
 * @see Signature de la fonction pour les parametres et le type retourne.
 */
function comkani_allow_supported_technical_mime_upload(
    bool $prevent,
    ?string $mime_type
): bool {
    if (
        $mime_type === 'image/svg+xml'
        && comkani_technical_upload_enabled('svg')
    ) {
        return false;
    }

    return $prevent;
}

add_filter(
    'wp_prevent_unsupported_mime_type_uploads',
    'comkani_allow_supported_technical_mime_upload',
    20,
    2
);
