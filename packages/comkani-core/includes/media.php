<?php
// =============================================================================
// Fichier : packages/comkani-core/includes/media.php
// Rôle : centraliser la gestion des médias WordPress du Starter ComKani
//
// Objectif :
// - fournir une fonction réutilisable du ComKani Core
// - garder cette logique indépendante du contenu éditorial propre au projet
//
// Ce qu'il fait :
// - préfixe les nouveaux fichiers avec le slug du projet
// - gère l'affichage conditionnel des légendes dans l'administration
// - conserve les légendes en base même lorsqu'elles sont désactivées
// - permet de remplacer ou renommer un média sans changer son ID WordPress
// - optimise les images avec prévisualisation avant / après
// - permet de régler la qualité de compression avant optimisation
// - conserve les données éditoriales et régénère les métadonnées des images
//
// Ce qu'il ne fait pas :
// - ne dépend pas de Node.js ou Vite en production
// - ne contient pas de contenu client codé en dur sauf valeur de repli explicitement prévue
//
// Fichiers liés :
// - config/generated/project-config.php
// - packages/comkani-core/comkani-core.php
// - inc/uploads.php
// =============================================================================

defined('ABSPATH') || exit;

//
// Configuration
//

/**
 * Retourne la qualité de compression utilisée par défaut
 *
 * @return int
 */
function comkani_media_image_quality(): int
{
    return 82;
}

/**
 * Retourne la qualité minimale proposée dans le curseur
 *
 * @return int
 */
function comkani_media_image_quality_min(): int
{
    return 40;
}

/**
 * Retourne la qualité maximale proposée dans le curseur
 *
 * @return int
 */
function comkani_media_image_quality_max(): int
{
    return 100;
}

/**
 * Sécurise une valeur de qualité d'image
 *
 * @param int $quality Qualité demandée
 * @return int
 */
function comkani_media_sanitize_quality(int $quality): int
{
    return max(
        comkani_media_image_quality_min(),
        min(
            comkani_media_image_quality_max(),
            $quality
        )
    );
}

/**
 * Retourne la dimension maximale appliquée pendant l'optimisation
 *
 * @return int
 */
function comkani_media_max_dimension(): int
{
    return 2560;
}

/**
 * Retourne la durée de conservation d'une prévisualisation
 *
 * @return int
 */
function comkani_media_preview_ttl(): int
{
    return 30 * MINUTE_IN_SECONDS;
}

//
// Utilitaires
//

/**
 * Retourne le slug du projet utilisé pour nommer les médias
 *
 * @return string
 */
function comkani_media_project_slug(): string
{
    $project_slug = sanitize_title(
        (string) comkani_core_get(
            'project.slug',
            ''
        )
    );

    if (
        '' === $project_slug
        || 'comkani-starter' === $project_slug
    ) {
        return '';
    }

    return $project_slug;
}

/**
 * Ajoute le slug du projet au début d'un nom de média
 *
 * Le préfixe n'est ajouté qu'une seule fois
 *
 * @param string $basename Nom du fichier sans extension
 * @return string
 */
function comkani_media_prefix_basename(string $basename): string
{
    $basename = sanitize_title($basename);

    if ('' === $basename) {
        return '';
    }

    $project_slug = comkani_media_project_slug();

    if ('' === $project_slug) {
        return $basename;
    }

    $prefix = $project_slug . '-';

    if (
        $basename === $project_slug
        || 0 === strpos($basename, $prefix)
    ) {
        return $basename;
    }

    return $prefix . $basename;
}

/**
 * Construit l'URL de l'écran ComKani de gestion d'un média
 *
 * @param int $attachment_id ID du média
 * @return string
 */
function comkani_media_replace_url(int $attachment_id): string
{
    return add_query_arg(
        [
            'page' => 'comkani-media-replace',
            'attachment_id' => $attachment_id,
        ],
        admin_url('upload.php')
    );
}

/**
 * Construit la clé de transient utilisée pour la prévisualisation
 *
 * @param int $attachment_id ID du média
 * @return string
 */
function comkani_media_preview_key(int $attachment_id): string
{
    return 'comkani_media_preview_' . get_current_user_id() . '_' . $attachment_id;
}

/**
 * Nettoie un nom de fichier et lui applique le préfixe du projet
 *
 * @param string $filename Nom de fichier
 * @return string
 */
function comkani_sanitize_media_filename(string $filename): string
{
    $filename = sanitize_file_name($filename);
    $filename = preg_replace('/\.[a-zA-Z0-9]+$/', '', $filename);
    $filename = trim((string) $filename, '.-_');

    return comkani_media_prefix_basename($filename);
}

/**
 * Construit un titre lisible à partir d'un nom de fichier
 *
 * @param string $filename Nom de fichier
 * @return string
 */
function comkani_media_title_from_filename(string $filename): string
{
    $title = str_replace(['-', '_'], ' ', $filename);

    return ucfirst(trim($title));
}

/**
 * Indique si un type MIME correspond à une image
 *
 * @param string $mime Type MIME
 * @return bool
 */
function comkani_media_is_image_mime(string $mime): bool
{
    return 0 === strpos($mime, 'image/');
}

/**
 * Indique si un type MIME peut être optimisé par le module
 *
 * @param string $mime Type MIME
 * @return bool
 */
function comkani_media_is_optimizable_mime(string $mime): bool
{
    return in_array(
        $mime,
        [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/avif',
        ],
        true
    );
}

/**
 * Vérifie la compatibilité entre le média actuel et un fichier de remplacement
 *
 * @param string $old_mime Type MIME actuel
 * @param string $new_mime Nouveau type MIME
 * @return bool
 */
function comkani_media_mime_is_compatible(string $old_mime, string $new_mime): bool
{
    if (
        comkani_media_is_image_mime($old_mime)
        && comkani_media_is_image_mime($new_mime)
    ) {
        return true;
    }

    return $old_mime === $new_mime;
}

/**
 * Retourne l'extension correspondant à un type MIME
 *
 * @param string $mime Type MIME
 * @param string $file Chemin du fichier
 * @return string
 */
function comkani_media_extension(string $mime, string $file = ''): string
{
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
    ];

    if (isset($extensions[$mime])) {
        return $extensions[$mime];
    }

    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    return sanitize_key($extension);
}

/**
 * Retourne le fichier original d'une image lorsque WordPress en conserve un
 *
 * @param string $file Chemin du fichier
 * @param array $metadata Métadonnées WordPress du média
 * @return string
 */
function comkani_media_original_file(string $file, array $metadata): string
{
    if (empty($metadata['original_image'])) {
        return $file;
    }

    $original = path_join(
        dirname($file),
        wp_basename((string) $metadata['original_image'])
    );

    return file_exists($original) ? $original : $file;
}

/**
 * Retourne le nom actuel du média sans extension
 *
 * @param string $file Chemin du fichier
 * @param array $metadata Métadonnées WordPress du média
 * @return string
 */
function comkani_media_current_basename(string $file, array $metadata): string
{
    $source = comkani_media_original_file($file, $metadata);

    return pathinfo(wp_basename($source), PATHINFO_FILENAME);
}

/**
 * Convertit un chemin local du dossier uploads en URL publique
 *
 * @param string $file Chemin du fichier
 * @return string
 */
function comkani_media_file_url(string $file): string
{
    $uploads = wp_upload_dir();
    $basedir = wp_normalize_path((string) $uploads['basedir']);
    $file = wp_normalize_path($file);

    if (0 !== strpos($file, trailingslashit($basedir))) {
        return '';
    }

    $relative = ltrim(substr($file, strlen($basedir)), '/');

    return trailingslashit((string) $uploads['baseurl']) . str_replace('%2F', '/', rawurlencode($relative));
}

/**
 * Retourne le libellé lisible d'un format de fichier
 *
 * @param string $mime Type MIME
 * @return string
 */
function comkani_media_format_label(string $mime): string
{
    $labels = [
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/webp' => 'WebP',
        'image/avif' => 'AVIF',
        'image/gif' => 'GIF',
        'image/svg+xml' => 'SVG',
        'application/pdf' => 'PDF',
        'application/zip' => 'ZIP',
    ];

    return $labels[$mime] ?? strtoupper((string) substr(strrchr($mime, '/'), 1));
}

/**
 * Retourne les principales informations techniques d'un fichier
 *
 * @param string $file Chemin du fichier
 * @param string $mime Type MIME
 * @return array
 */
function comkani_media_file_info(string $file, string $mime): array
{
    $width = 0;
    $height = 0;

    if (file_exists($file) && comkani_media_is_image_mime($mime)) {
        $size = wp_getimagesize($file);

        if (is_array($size)) {
            $width = isset($size[0]) ? (int) $size[0] : 0;
            $height = isset($size[1]) ? (int) $size[1] : 0;
        }
    }

    $bytes = file_exists($file) ? (int) filesize($file) : 0;

    return [
        'file' => $file,
        'url' => comkani_media_file_url($file),
        'filename' => wp_basename($file),
        'mime' => $mime,
        'format' => comkani_media_format_label($mime),
        'bytes' => $bytes,
        'size' => size_format($bytes, 2),
        'width' => $width,
        'height' => $height,
    ];
}

/**
 * Retourne la plage de poids recommandée selon les dimensions du média
 *
 * @param array $info Informations techniques du média
 * @return array
 */
function comkani_media_weight_advice(array $info): array
{
    $max_dimension = max((int) $info['width'], (int) $info['height']);

    if ($max_dimension >= 1800) {
        $target = '250 à 500 Ko';
    } elseif ($max_dimension >= 1200) {
        $target = '150 à 350 Ko';
    } else {
        $target = '80 à 200 Ko';
    }

    return [
        'target' => $target,
        'heavy' => (int) $info['bytes'] > 700 * KB_IN_BYTES,
    ];
}

/**
 * Indique si l'hébergement permet l'enregistrement en WebP
 *
 * @return bool
 */
function comkani_media_webp_supported(): bool
{
    return wp_image_editor_supports(
        [
            'mime_type' => 'image/webp',
            'methods' => ['save'],
        ]
    );
}

/**
 * Retourne le message d'erreur associé à un statut du module
 *
 * @param string $status Statut du traitement
 * @return string
 */
function comkani_media_error_message(string $status): string
{
    $messages = [
        'no-change' => 'Aucune modification n’a été demandée.',
        'invalid-name' => 'Le nouveau nom de fichier n’est pas valide.',
        'missing-file' => 'Le fichier source est introuvable.',
        'upload-error' => 'Le nouveau fichier n’a pas pu être importé.',
        'incompatible-type' => 'Le nouveau fichier n’est pas compatible avec le type du média actuel.',
        'preview-error' => 'La prévisualisation n’a pas pu être générée.',
        'preview-expired' => 'La prévisualisation a expiré. Il faut la générer de nouveau.',
        'webp-unsupported' => 'La conversion WebP n’est pas disponible sur cet hébergement.',
        'optimization-unsupported' => 'Ce type d’image ne peut pas être optimisé avec le moteur WordPress actuel.',
        'backup-error' => 'La sauvegarde de sécurité du média n’a pas pu être créée.',
        'copy-error' => 'Le nouveau fichier n’a pas pu être préparé.',
        'update-error' => 'WordPress n’a pas pu mettre à jour le média.',
        'metadata-error' => 'Les nouvelles métadonnées du média n’ont pas pu être générées.',
    ];

    return $messages[$status] ?? 'Une erreur est survenue pendant la modification du média.';
}

/**
 * Redirige vers l'écran de gestion du média avec un statut
 *
 * @param int $attachment_id ID du média
 * @param string $status Statut du traitement
 * @return void
 */
function comkani_media_redirect(int $attachment_id, string $status): void
{
    wp_safe_redirect(
        add_query_arg(
            'comkani_status',
            sanitize_key($status),
            comkani_media_replace_url($attachment_id)
        )
    );

    exit;
}

/**
 * Retourne un SVG de la médiathèque sous forme inline
 *
 * @param int $attachment_id ID du média SVG
 * @return string
 */
function comkani_attachment_svg(int $attachment_id): string
{
    if (
        $attachment_id < 1
        || 'image/svg+xml' !== get_post_mime_type($attachment_id)
    ) {
        return '';
    }

    $file = get_attached_file($attachment_id);

    if (
        !$file
        || !is_readable($file)
    ) {
        return '';
    }

    $svg = file_get_contents($file);

    if (
        !is_string($svg)
        || '' === $svg
    ) {
        return '';
    }

    $allowed = [
        'svg' => [
            'xmlns' => true,
            'width' => true,
            'height' => true,
            'viewbox' => true,
            'fill' => true,
        ],
        'path' => [
            'd' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-miterlimit' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
            'fill-rule' => true,
            'clip-rule' => true,
            'opacity' => true,
        ],
    ];

    return wp_kses(
        $svg,
        $allowed
    );
}

//
// Administration
//

/**
 * Enregistre la page d'administration utilisée pour gérer un média
 *
 * @return void
 */
function comkani_register_media_replace_page(): void
{
    add_media_page(
        'Remplacer un média',
        'Remplacer un média',
        'upload_files',
        'comkani-media-replace',
        'comkani_render_media_replace_page'
    );

    remove_submenu_page('upload.php', 'comkani-media-replace');
}

add_action('admin_menu', 'comkani_register_media_replace_page');

/**
 * Ajoute l'action ComKani dans la liste de la médiathèque
 *
 * @param array $actions Actions disponibles
 * @param WP_Post $attachment Média concerné
 * @return array
 */
function comkani_media_row_actions(array $actions, WP_Post $attachment): array
{
    if (!current_user_can('edit_post', $attachment->ID)) {
        return $actions;
    }

    $actions['comkani_replace_media'] = sprintf(
        '<a href="%s">Remplacer ou optimiser</a>',
        esc_url(comkani_media_replace_url($attachment->ID))
    );

    return $actions;
}

add_filter('media_row_actions', 'comkani_media_row_actions', 10, 2);

/**
 * Ajoute le bouton de gestion ComKani dans les détails d'un média
 *
 * @param array $fields Champs du média
 * @param WP_Post $attachment Média concerné
 * @return array
 */
function comkani_attachment_replace_field(array $fields, WP_Post $attachment): array
{
    if (!current_user_can('edit_post', $attachment->ID)) {
        return $fields;
    }

    $fields['comkani_replace_media'] = [
        'label' => 'Gestion du fichier',
        'input' => 'html',
        'html' => sprintf(
            '<a class="button button-secondary" href="%s">Remplacer ou optimiser</a>',
            esc_url(comkani_media_replace_url($attachment->ID))
        ),
        'helps' => 'L’ID et les données éditoriales du média sont conservés',
    ];

    return $fields;
}

add_filter('attachment_fields_to_edit', 'comkani_attachment_replace_field', 10, 2);

/**
 * Ajoute la métabox de gestion sur l'écran d'édition d'une pièce jointe
 *
 * @param WP_Post $post Média concerné
 * @return void
 */
function comkani_register_media_metabox(WP_Post $post): void
{
    if (!current_user_can('edit_post', $post->ID)) {
        return;
    }

    add_meta_box(
        'comkani-media-actions',
        'Gestion du média',
        'comkani_render_media_metabox',
        'attachment',
        'side',
        'high'
    );
}

add_action('add_meta_boxes_attachment', 'comkani_register_media_metabox');

/**
 * Affiche la métabox de gestion du média
 *
 * @param WP_Post $post Média concerné
 * @return void
 */
function comkani_render_media_metabox(WP_Post $post): void
{
    printf(
        '<p><a class="button button-secondary" href="%s">Remplacer, renommer ou optimiser</a></p>',
        esc_url(comkani_media_replace_url($post->ID))
    );

    echo '<p class="description">L’ID, l’alt, la légende et la description restent associés au média.</p>';
}

//
// Prévisualisation
//

/**
 * Retourne le dossier temporaire utilisé pour les prévisualisations
 *
 * @return array|WP_Error
 */
function comkani_media_preview_storage()
{
    $uploads = wp_upload_dir();

    if (!empty($uploads['error'])) {
        return new WP_Error('preview-error', $uploads['error']);
    }

    $path = trailingslashit((string) $uploads['basedir']) . 'comkani-media-preview';
    $url = trailingslashit((string) $uploads['baseurl']) . 'comkani-media-preview';

    if (!wp_mkdir_p($path)) {
        return new WP_Error('preview-error', 'Impossible de créer le dossier de prévisualisation.');
    }

    return [
        'path' => $path,
        'url' => $url,
    ];
}

/**
 * Vérifie qu'un fichier appartient bien au dossier de prévisualisation
 *
 * @param string $file Chemin du fichier
 * @return bool
 */
function comkani_media_is_preview_file(string $file): bool
{
    $storage = comkani_media_preview_storage();

    if (is_wp_error($storage)) {
        return false;
    }

    $directory = trailingslashit(wp_normalize_path((string) $storage['path']));
    $file = wp_normalize_path($file);

    return 0 === strpos($file, $directory);
}

/**
 * Supprime la prévisualisation temporaire associée à un média
 *
 * @param int $attachment_id ID du média
 * @return void
 */
function comkani_media_clear_preview(int $attachment_id): void
{
    $key = comkani_media_preview_key($attachment_id);
    $preview = get_transient($key);

    if (is_array($preview)) {
        $files = [];

        if (!empty($preview['preview_file'])) {
            $files[] = (string) $preview['preview_file'];
        }

        if (!empty($preview['source_temporary']) && !empty($preview['source_file'])) {
            $files[] = (string) $preview['source_file'];
        }

        foreach (array_unique($files) as $file) {
            if (comkani_media_is_preview_file($file) && file_exists($file)) {
                wp_delete_file($file);
            }
        }
    }

    delete_transient($key);
}

/**
 * Supprime les fichiers de prévisualisation devenus obsolètes
 *
 * @return void
 */
function comkani_media_cleanup_stale_previews(): void
{
    $storage = comkani_media_preview_storage();

    if (is_wp_error($storage)) {
        return;
    }

    $files = array_merge(
        glob(trailingslashit($storage['path']) . 'source-*') ?: [],
        glob(trailingslashit($storage['path']) . 'preview-*') ?: []
    );

    foreach ($files as $file) {
        if (
            is_file($file)
            && filemtime($file)
            && filemtime($file) < time() - DAY_IN_SECONDS
        ) {
            wp_delete_file($file);
        }
    }
}

/**
 * Déplace un fichier importé vers le dossier temporaire de prévisualisation
 *
 * @param string $file Chemin du fichier
 * @param string $mime Type MIME
 * @return array|WP_Error
 */
function comkani_media_move_uploaded_source(string $file, string $mime)
{
    $storage = comkani_media_preview_storage();

    if (is_wp_error($storage)) {
        return $storage;
    }

    $extension = comkani_media_extension($mime, $file);

    if ('' === $extension) {
        return new WP_Error('upload-error', 'Extension de fichier introuvable.');
    }

    $destination = path_join(
        $storage['path'],
        'source-' . str_replace('-', '', wp_generate_uuid4()) . '.' . $extension
    );

    if (!@rename($file, $destination)) {
        if (!copy($file, $destination)) {
            return new WP_Error('upload-error', 'Impossible de préparer le fichier importé.');
        }

        wp_delete_file($file);
    }

    return [
        'file' => $destination,
        'url' => comkani_media_file_url($destination),
        'mime' => $mime,
        'temporary' => true,
    ];
}

/**
 * Crée une version optimisée temporaire sans modifier le média WordPress
 *
 * @param string $source_file Fichier source
 * @param string $source_mime Type MIME du fichier source
 * @param bool $optimize Indique si l'image doit être optimisée
 * @param bool $convert_webp Indique si une conversion WebP est demandée
 * @param int $quality Qualité de compression demandée
 * @return array|WP_Error
 */
function comkani_media_create_optimized_preview(
    string $source_file,
    string $source_mime,
    bool $optimize,
    bool $convert_webp,
    int $quality
) {
    if (!comkani_media_is_optimizable_mime($source_mime)) {
        return new WP_Error('optimization-unsupported');
    }

    if ($convert_webp && !comkani_media_webp_supported()) {
        return new WP_Error('webp-unsupported');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    $editor = wp_get_image_editor($source_file);

    if (is_wp_error($editor)) {
        return $editor;
    }

    $size = $editor->get_size();

    if (
        $optimize
        && is_array($size)
        && (
            (int) $size['width'] > comkani_media_max_dimension()
            || (int) $size['height'] > comkani_media_max_dimension()
        )
    ) {
        $resized = $editor->resize(
            comkani_media_max_dimension(),
            comkani_media_max_dimension(),
            false
        );

        if (is_wp_error($resized)) {
            return $resized;
        }
    }

    $quality = comkani_media_sanitize_quality($quality);
    $quality_result = $editor->set_quality($quality);

    if (is_wp_error($quality_result)) {
        return $quality_result;
    }

    $target_mime = $convert_webp ? 'image/webp' : $source_mime;
    $extension = comkani_media_extension($target_mime, $source_file);
    $storage = comkani_media_preview_storage();

    if (is_wp_error($storage)) {
        return $storage;
    }

    $destination = path_join(
        $storage['path'],
        'preview-' . str_replace('-', '', wp_generate_uuid4()) . '.' . $extension
    );

    $saved = $editor->save($destination, $target_mime);

    if (is_wp_error($saved) || empty($saved['path'])) {
        return is_wp_error($saved)
            ? $saved
            : new WP_Error('preview-error');
    }

    return [
        'file' => (string) $saved['path'],
        'mime' => !empty($saved['mime-type'])
            ? (string) $saved['mime-type']
            : $target_mime,
    ];
}

//
// Sécurisation des fichiers
//

/**
 * Collecte le fichier principal et toutes les variantes liées à un média
 *
 * @param string $attached_file Fichier principal du média
 * @param array $metadata Métadonnées WordPress du média
 * @param array $backup_sizes Tailles de sauvegarde WordPress
 * @return array
 */
function comkani_media_collect_attachment_files(
    string $attached_file,
    array $metadata,
    array $backup_sizes
): array {
    $directory = dirname($attached_file);
    $files = [$attached_file];

    if (!empty($metadata['original_image'])) {
        $files[] = path_join(
            $directory,
            wp_basename((string) $metadata['original_image'])
        );
    }

    if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size) {
            if (!empty($size['file'])) {
                $files[] = path_join(
                    $directory,
                    wp_basename((string) $size['file'])
                );
            }
        }
    }

    foreach ($backup_sizes as $size) {
        if (!empty($size['file'])) {
            $files[] = path_join(
                $directory,
                wp_basename((string) $size['file'])
            );
        }
    }

    return array_values(array_unique($files));
}

/**
 * Crée une sauvegarde temporaire des fichiers avant remplacement
 *
 * @param array $files Liste des fichiers
 * @return array|WP_Error
 */
function comkani_media_backup_files(array $files)
{
    $backups = [];

    foreach ($files as $file) {
        if (!file_exists($file)) {
            continue;
        }

        $temp = wp_tempnam(wp_basename($file));

        if (!$temp || !copy($file, $temp)) {
            foreach ($backups as $backup) {
                if (file_exists($backup)) {
                    wp_delete_file($backup);
                }
            }

            return new WP_Error('backup-error');
        }

        $backups[$file] = $temp;
    }

    return $backups;
}

/**
 * Supprime une liste de fichiers du disque
 *
 * @param array $files Liste des fichiers
 * @return void
 */
function comkani_media_delete_files(array $files): void
{
    foreach (array_unique($files) as $file) {
        if (file_exists($file)) {
            wp_delete_file($file);
        }
    }
}

/**
 * Restaure les fichiers sauvegardés en cas d'échec
 *
 * @param array $backups Sauvegardes temporaires
 * @return void
 */
function comkani_media_restore_backups(array $backups): void
{
    foreach ($backups as $original => $backup) {
        if (file_exists($backup)) {
            copy($backup, $original);
        }
    }
}

/**
 * Supprime les sauvegardes temporaires après traitement
 *
 * @param array $backups Sauvegardes temporaires
 * @return void
 */
function comkani_media_cleanup_backups(array $backups): void
{
    foreach ($backups as $backup) {
        if (file_exists($backup)) {
            wp_delete_file($backup);
        }
    }
}

/**
 * Prépare une copie temporaire du fichier candidat
 *
 * @param string $source_file Fichier source
 * @param string $mime Type MIME
 * @return string|WP_Error
 */
function comkani_media_stage_candidate(string $source_file, string $mime)
{
    $extension = comkani_media_extension($mime, $source_file);
    $temp = trailingslashit(get_temp_dir())
        . 'comkani-media-'
        . str_replace('-', '', wp_generate_uuid4())
        . '.'
        . $extension;

    if (!copy($source_file, $temp)) {
        return new WP_Error('copy-error');
    }

    return $temp;
}

/**
 * Restaure complètement l'état WordPress et les fichiers d'un média
 *
 * @param int $attachment_id ID du média
 * @param WP_Post $old_post État WordPress initial du média
 * @param string $old_file Chemin initial du fichier
 * @param array $old_metadata Métadonnées initiales
 * @param array $old_backup_sizes Tailles de sauvegarde initiales
 * @param array $backups Sauvegardes temporaires
 * @param array $new_files Nouveaux fichiers créés
 * @return void
 */
function comkani_media_restore_state(
    int $attachment_id,
    WP_Post $old_post,
    string $old_file,
    array $old_metadata,
    array $old_backup_sizes,
    array $backups,
    array $new_files
): void {
    comkani_media_delete_files($new_files);
    comkani_media_restore_backups($backups);

    wp_update_post(
        [
            'ID' => $attachment_id,
            'post_title' => $old_post->post_title,
            'post_name' => $old_post->post_name,
            'post_mime_type' => $old_post->post_mime_type,
        ]
    );

    update_attached_file($attachment_id, $old_file);

    if ($old_metadata) {
        wp_update_attachment_metadata($attachment_id, $old_metadata);
    } else {
        delete_post_meta($attachment_id, '_wp_attachment_metadata');
    }

    if ($old_backup_sizes) {
        update_post_meta(
            $attachment_id,
            '_wp_attachment_backup_sizes',
            $old_backup_sizes
        );
    } else {
        delete_post_meta($attachment_id, '_wp_attachment_backup_sizes');
    }

    clean_attachment_cache($attachment_id);
    comkani_media_cleanup_backups($backups);
}

/**
 * Applique définitivement un remplacement, un renommage ou une optimisation
 *
 * @param int $attachment_id ID du média
 * @param string $source_file Fichier source
 * @param string $new_mime Nouveau type MIME
 * @param string $requested_filename Nouveau nom demandé sans extension
 * @return true|WP_Error
 */
function comkani_media_commit_file(
    int $attachment_id,
    string $source_file,
    string $new_mime,
    string $requested_filename
) {
    $attachment = get_post($attachment_id);

    if (!$attachment || 'attachment' !== $attachment->post_type) {
        return new WP_Error('update-error');
    }

    $old_file = get_attached_file($attachment_id);

    if (!$old_file || !file_exists($old_file) || !file_exists($source_file)) {
        return new WP_Error('missing-file');
    }

    $old_mime = (string) $attachment->post_mime_type;

    if (!comkani_media_mime_is_compatible($old_mime, $new_mime)) {
        return new WP_Error('incompatible-type');
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $old_metadata = wp_get_attachment_metadata($attachment_id, true);
    $old_metadata = is_array($old_metadata) ? $old_metadata : [];

    $old_backup_sizes = get_post_meta(
        $attachment_id,
        '_wp_attachment_backup_sizes',
        true
    );
    $old_backup_sizes = is_array($old_backup_sizes) ? $old_backup_sizes : [];

    $old_files = comkani_media_collect_attachment_files(
        $old_file,
        $old_metadata,
        $old_backup_sizes
    );

    $candidate = comkani_media_stage_candidate($source_file, $new_mime);

    if (is_wp_error($candidate)) {
        return $candidate;
    }

    $basename = '' !== $requested_filename
        ? $requested_filename
        : comkani_media_current_basename($old_file, $old_metadata);

    $extension = comkani_media_extension($new_mime, $source_file);

    if ('' === $basename || '' === $extension) {
        wp_delete_file($candidate);
        return new WP_Error('invalid-name');
    }

    $directory = dirname($old_file);
    $desired_filename = sanitize_file_name($basename . '.' . $extension);
    $desired_file = path_join($directory, $desired_filename);
    $normalized_old_files = array_map('wp_normalize_path', $old_files);

    if (!in_array(wp_normalize_path($desired_file), $normalized_old_files, true)) {
        $desired_filename = wp_unique_filename($directory, $desired_filename);
        $desired_file = path_join($directory, $desired_filename);
    }

    $backups = comkani_media_backup_files($old_files);

    if (is_wp_error($backups)) {
        wp_delete_file($candidate);
        return $backups;
    }

    // Remplacement après sauvegarde
    comkani_media_delete_files($old_files);

    if (!copy($candidate, $desired_file)) {
        comkani_media_restore_backups($backups);
        comkani_media_cleanup_backups($backups);
        wp_delete_file($candidate);
        return new WP_Error('copy-error');
    }

    $post_data = [
        'ID' => $attachment_id,
        'post_mime_type' => $new_mime,
    ];

    if ('' !== $requested_filename) {
        $post_data['post_title'] = comkani_media_title_from_filename(
            $requested_filename
        );
        $post_data['post_name'] = sanitize_title($requested_filename);
    }

    $post_update = wp_update_post($post_data, true);

    if (is_wp_error($post_update)) {
        comkani_media_restore_state(
            $attachment_id,
            $attachment,
            $old_file,
            $old_metadata,
            $old_backup_sizes,
            $backups,
            [$desired_file]
        );
        wp_delete_file($candidate);
        return new WP_Error('update-error');
    }

    // update_attached_file() renvoie false lorsque la valeur de
    // _wp_attached_file ne change pas. C'est normal lors d'un remplacement
    // conservant exactement le même nom et le même emplacement (cas fréquent
    // pour une vidéo optimisée). On ne traite donc false comme une erreur que
    // si WordPress ne pointe réellement pas vers le fichier souhaité.
    $attached_file_updated = update_attached_file(
        $attachment_id,
        $desired_file
    );

    $attached_file_after_update = get_attached_file(
        $attachment_id
    );

    if (
        !$attached_file_updated
        && wp_normalize_path((string) $attached_file_after_update)
            !== wp_normalize_path($desired_file)
    ) {
        comkani_media_restore_state(
            $attachment_id,
            $attachment,
            $old_file,
            $old_metadata,
            $old_backup_sizes,
            $backups,
            [$desired_file]
        );
        wp_delete_file($candidate);
        return new WP_Error('update-error');
    }

    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    $new_metadata = wp_generate_attachment_metadata(
        $attachment_id,
        $desired_file
    );
    $new_metadata = is_array($new_metadata) ? $new_metadata : [];

    $new_attached_file = get_attached_file($attachment_id);
    $new_attached_file = $new_attached_file ?: $desired_file;

    $new_files = comkani_media_collect_attachment_files(
        $new_attached_file,
        $new_metadata,
        []
    );

    if (
        comkani_media_is_image_mime($new_mime)
        && 'image/svg+xml' !== $new_mime
        && !$new_metadata
    ) {
        comkani_media_restore_state(
            $attachment_id,
            $attachment,
            $old_file,
            $old_metadata,
            $old_backup_sizes,
            $backups,
            $new_files
        );
        wp_delete_file($candidate);
        return new WP_Error('metadata-error');
    }

    if ($new_metadata) {
        wp_update_attachment_metadata($attachment_id, $new_metadata);
    } else {
        delete_post_meta($attachment_id, '_wp_attachment_metadata');
    }

    delete_post_meta($attachment_id, '_wp_attachment_backup_sizes');
    clean_attachment_cache($attachment_id);
    comkani_media_cleanup_backups($backups);
    wp_delete_file($candidate);

    // Notification aux modules ComKani
    do_action(
        'comkani_media_replaced',
        $attachment_id,
        $old_file,
        $new_attached_file
    );

    return true;
}

//
// Interface
//

/**
 * Affiche la comparaison avant et après une optimisation
 *
 * @param array $preview Données de prévisualisation
 * @param int $attachment_id ID du média
 * @return void
 */
function comkani_render_media_preview(array $preview, int $attachment_id): void
{
    $before = $preview['before'];
    $after = $preview['after'];
    $difference = (int) $before['bytes'] - (int) $after['bytes'];
    $percent = (int) $before['bytes'] > 0
        ? ($difference / (int) $before['bytes']) * 100
        : 0;
    $advice = comkani_media_weight_advice($after);
    ?>
    <h2>Prévisualisation avant / après</h2>

    <div class="comkani-media-compare">
        <section class="comkani-media-card">
            <h3>Avant</h3>
            <?php if (!empty($before['url'])) : ?>
                <img src="<?php echo esc_url($before['url']); ?>" alt="">
            <?php endif; ?>
            <p><strong>Format :</strong> <?php echo esc_html($before['format']); ?></p>
            <p><strong>Dimensions :</strong> <?php echo esc_html($before['width'] . ' × ' . $before['height'] . ' px'); ?></p>
            <p><strong>Poids :</strong> <?php echo esc_html($before['size']); ?></p>
        </section>

        <section class="comkani-media-card">
            <h3>Après</h3>
            <?php if (!empty($after['url'])) : ?>
                <img src="<?php echo esc_url($after['url']); ?>" alt="">
            <?php endif; ?>
            <p><strong>Format :</strong> <?php echo esc_html($after['format']); ?></p>
            <p><strong>Dimensions :</strong> <?php echo esc_html($after['width'] . ' × ' . $after['height'] . ' px'); ?></p>
            <p><strong>Poids :</strong> <?php echo esc_html($after['size']); ?></p>
        </section>
    </div>

    <div class="notice notice-info inline comkani-media-gain">
        <p>
            <?php if ($difference >= 0) : ?>
                <strong>Gain :</strong>
                <?php echo esc_html(size_format($difference, 2)); ?>
                <?php echo esc_html(' (' . number_format_i18n($percent, 1) . ' %)'); ?>
            <?php else : ?>
                <strong>Attention :</strong>
                cette version est plus lourde de
                <?php echo esc_html(size_format(abs($difference), 2)); ?>
            <?php endif; ?>
        </p>
        <p>
            <strong>Conseil ComKani :</strong>
            pour ces dimensions, viser idéalement <?php echo esc_html($advice['target']); ?>.
            Au-delà de 700 Ko, une optimisation est généralement recommandée.
        </p>
    </div>

    <div class="comkani-media-actions">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="comkani_media_apply_preview">
            <input type="hidden" name="attachment_id" value="<?php echo esc_attr((string) $attachment_id); ?>">
            <?php wp_nonce_field('comkani_media_apply_preview_' . $attachment_id); ?>
            <?php submit_button('Appliquer cette optimisation', 'primary', 'submit', false); ?>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="comkani_media_cancel_preview">
            <input type="hidden" name="attachment_id" value="<?php echo esc_attr((string) $attachment_id); ?>">
            <?php wp_nonce_field('comkani_media_cancel_preview_' . $attachment_id); ?>
            <?php submit_button('Annuler', 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
}

/**
 * Affiche l'écran complet de gestion d'un média
 *
 * @return void
 */
function comkani_render_media_replace_page(): void
{
    if (!current_user_can('upload_files')) {
        wp_die('Vous n’avez pas l’autorisation de modifier les médias.');
    }

    comkani_media_cleanup_stale_previews();

    $attachment_id = isset($_GET['attachment_id'])
        ? absint($_GET['attachment_id'])
        : 0;
    $attachment = get_post($attachment_id);

    if (!$attachment || 'attachment' !== $attachment->post_type) {
        wp_die('Le média demandé est introuvable.');
    }

    if (!current_user_can('edit_post', $attachment_id)) {
        wp_die('Vous n’avez pas l’autorisation de modifier ce média.');
    }

    $file = get_attached_file($attachment_id);

    if (!$file || !file_exists($file)) {
        wp_die('Le fichier associé à ce média est introuvable.');
    }

    $metadata = wp_get_attachment_metadata($attachment_id, true);
    $metadata = is_array($metadata) ? $metadata : [];
    $info = comkani_media_file_info($file, (string) $attachment->post_mime_type);
    $advice = comkani_media_weight_advice($info);
    $preview = get_transient(comkani_media_preview_key($attachment_id));
    $status = isset($_GET['comkani_status'])
        ? sanitize_key($_GET['comkani_status'])
        : '';
    $alt = get_post_meta(
        $attachment_id,
        '_wp_attachment_image_alt',
        true
    );
    $optimizable = comkani_media_is_optimizable_mime(
        (string) $attachment->post_mime_type
    );
    ?>
    <div class="wrap comkani-media-admin">
        <h1>Remplacer, renommer ou optimiser le média</h1>

        <style>
            .comkani-media-admin{max-width:1200px}.comkani-media-summary{max-width:900px;margin:24px 0}.comkani-media-compare{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;max-width:1100px}.comkani-media-card{background:#fff;border:1px solid #dcdcde;padding:20px}.comkani-media-card img{display:block;width:100%;height:320px;object-fit:contain;background:#f6f7f7;margin:0 0 16px}.comkani-media-card p{margin:6px 0}.comkani-media-gain{max-width:1060px;margin:20px 0!important}.comkani-media-actions{display:flex;align-items:center;gap:12px;margin:20px 0}.comkani-media-options{max-width:900px;background:#fff;border:1px solid #dcdcde;padding:20px;margin:24px 0}.comkani-media-options h2{margin-top:0}.comkani-media-quality{margin:18px 0}.comkani-media-quality>label{display:block;font-weight:600;margin-bottom:8px}.comkani-media-quality-control{display:flex;align-items:center;gap:14px;max-width:520px}.comkani-media-quality-control input[type=range]{flex:1;min-width:220px}.comkani-media-quality-control output{min-width:56px;font-weight:600}@media(max-width:782px){.comkani-media-compare{grid-template-columns:1fr}.comkani-media-card img{height:auto}.comkani-media-actions{align-items:flex-start;flex-direction:column}.comkani-media-quality-control{align-items:flex-start;flex-direction:column}.comkani-media-quality-control input[type=range]{width:100%}}
        </style>

        <?php if ('success' === $status) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Le média a été mis à jour. Son ID et ses données éditoriales ont été conservés.</p>
            </div>
        <?php elseif ($status && 'preview' !== $status) : ?>
            <div class="notice notice-error">
                <p><?php echo esc_html(comkani_media_error_message($status)); ?></p>
            </div>
        <?php endif; ?>

        <table class="widefat striped comkani-media-summary">
            <tbody>
                <tr>
                    <th>ID WordPress</th>
                    <td><?php echo esc_html((string) $attachment_id); ?></td>
                </tr>
                <tr>
                    <th>Fichier actuel</th>
                    <td><?php echo esc_html($info['filename']); ?></td>
                </tr>
                <tr>
                    <th>Titre</th>
                    <td><?php echo esc_html($attachment->post_title); ?></td>
                </tr>
                <tr>
                    <th>Permalien</th>
                    <td><?php echo esc_html(get_attachment_link($attachment_id)); ?></td>
                </tr>
                <tr>
                    <th>Texte alternatif</th>
                    <td><?php echo esc_html((string) $alt); ?></td>
                </tr>
                <tr>
                    <th>Format</th>
                    <td><?php echo esc_html($info['format']); ?></td>
                </tr>
                <?php if ($info['width'] && $info['height']) : ?>
                    <tr>
                        <th>Dimensions</th>
                        <td><?php echo esc_html($info['width'] . ' × ' . $info['height'] . ' px'); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th>Poids</th>
                    <td>
                        <?php echo esc_html($info['size']); ?>
                        <?php if ($optimizable && $advice['heavy']) : ?>
                            <strong> · optimisation recommandée</strong>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if (is_array($preview) && !empty($preview['preview_file'])) : ?>
            <?php comkani_render_media_preview($preview, $attachment_id); ?>
        <?php else : ?>
            <form
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                enctype="multipart/form-data"
                class="comkani-media-options"
            >
                <input type="hidden" name="action" value="comkani_media_update">
                <input type="hidden" name="attachment_id" value="<?php echo esc_attr((string) $attachment_id); ?>">
                <?php wp_nonce_field('comkani_media_update_' . $attachment_id); ?>

                <h2>Modification du fichier</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="comkani_media_file">Nouveau fichier</label>
                        </th>
                        <td>
                            <input type="file" id="comkani_media_file" name="comkani_media_file">
                            <p class="description">Laisser vide pour conserver le fichier actuel</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="comkani_media_filename">Nouveau nom</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="comkani_media_filename"
                                name="comkani_media_filename"
                                class="regular-text"
                                placeholder="<?php echo esc_attr(comkani_media_current_basename($file, $metadata)); ?>"
                            >
                            <p class="description">
                                Sans extension. Le fichier, le titre et le permalien seront synchronisés automatiquement
                            </p>
                        </td>
                    </tr>

                    <?php if ($optimizable) : ?>
                        <tr>
                            <th scope="row">Optimisation</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="comkani_media_optimize" value="1">
                                    Optimiser l’image
                                </label>
                                <br>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="comkani_media_webp"
                                        value="1"
                                        <?php disabled(!comkani_media_webp_supported()); ?>
                                    >
                                    Convertir en WebP
                                </label>

                                <?php if (!comkani_media_webp_supported()) : ?>
                                    <p class="description">Conversion WebP indisponible sur cet hébergement</p>
                                <?php endif; ?>

                                <div class="comkani-media-quality">
                                    <label for="comkani_media_quality">
                                        Qualité de compression
                                    </label>
                                    <div class="comkani-media-quality-control">
                                        <input
                                            type="range"
                                            id="comkani_media_quality"
                                            name="comkani_media_quality"
                                            min="<?php echo esc_attr((string) comkani_media_image_quality_min()); ?>"
                                            max="<?php echo esc_attr((string) comkani_media_image_quality_max()); ?>"
                                            step="1"
                                            value="<?php echo esc_attr((string) comkani_media_image_quality()); ?>"
                                            data-comkani-quality-range
                                        >
                                        <output
                                            for="comkani_media_quality"
                                            data-comkani-quality-output
                                        >
                                            <?php echo esc_html((string) comkani_media_image_quality()); ?> %
                                        </output>
                                    </div>
                                    <p class="description">
                                        Plus la valeur est basse, plus le fichier peut être léger mais plus la compression est visible.
                                        La valeur règle la qualité de compression, pas un poids exact en Ko
                                    </p>
                                </div>
                                <p class="description">
                                    Dimension maximale lors de l’optimisation : <?php echo esc_html((string) comkani_media_max_dimension()); ?> px.
                                    Poids conseillé pour cette image : <?php echo esc_html($advice['target']); ?>.
                                    Éviter de dépasser 700 Ko sans nécessité particulière
                                </p>
                                <?php if ('image/png' === $attachment->post_mime_type) : ?>
                                    <p class="description">
                                        Pour un PNG photographique, la conversion WebP est généralement plus efficace qu’une simple recompression PNG
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>

                <p class="description">
                    Si une optimisation ou une conversion est demandée, un avant / après sera affiché avant toute modification du média
                </p>

                <?php submit_button('Continuer'); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

//
// Traitement
//

/**
 * Traite la demande de remplacement, renommage ou optimisation
 *
 * @return void
 */
function comkani_handle_media_update(): void
{
    if (!current_user_can('upload_files')) {
        wp_die('Vous n’avez pas l’autorisation de modifier les médias.');
    }

    $attachment_id = isset($_POST['attachment_id'])
        ? absint($_POST['attachment_id'])
        : 0;

    check_admin_referer('comkani_media_update_' . $attachment_id);

    $attachment = get_post($attachment_id);

    if (
        !$attachment
        || 'attachment' !== $attachment->post_type
        || !current_user_can('edit_post', $attachment_id)
    ) {
        wp_die('Le média demandé est invalide.');
    }

    $old_file = get_attached_file($attachment_id);

    if (!$old_file || !file_exists($old_file)) {
        comkani_media_redirect($attachment_id, 'missing-file');
    }

    $old_metadata = wp_get_attachment_metadata($attachment_id, true);
    $old_metadata = is_array($old_metadata) ? $old_metadata : [];

    $requested_filename = isset($_POST['comkani_media_filename'])
        ? comkani_sanitize_media_filename(
            (string) wp_unslash($_POST['comkani_media_filename'])
        )
        : '';

    $optimize = !empty($_POST['comkani_media_optimize']);
    $convert_webp = !empty($_POST['comkani_media_webp']);
    $quality = isset($_POST['comkani_media_quality'])
        ? comkani_media_sanitize_quality(
            absint($_POST['comkani_media_quality'])
        )
        : comkani_media_image_quality();
    $has_new_file = isset($_FILES['comkani_media_file'])
        && is_array($_FILES['comkani_media_file'])
        && UPLOAD_ERR_NO_FILE !== (int) $_FILES['comkani_media_file']['error'];

    if (!$has_new_file && '' === $requested_filename && !$optimize && !$convert_webp) {
        comkani_media_redirect($attachment_id, 'no-change');
    }

    comkani_media_clear_preview($attachment_id);

    $source_file = $old_file;
    $source_mime = (string) $attachment->post_mime_type;
    $source_temporary = false;

    if ($has_new_file) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload(
            $_FILES['comkani_media_file'],
            ['test_form' => false]
        );

        if (
            isset($upload['error'])
            || empty($upload['file'])
            || empty($upload['type'])
        ) {
            comkani_media_redirect($attachment_id, 'upload-error');
        }

        $source_mime = (string) $upload['type'];

        if (
            !comkani_media_mime_is_compatible(
                (string) $attachment->post_mime_type,
                $source_mime
            )
        ) {
            wp_delete_file((string) $upload['file']);
            comkani_media_redirect($attachment_id, 'incompatible-type');
        }

        $temporary_source = comkani_media_move_uploaded_source(
            (string) $upload['file'],
            $source_mime
        );

        if (is_wp_error($temporary_source)) {
            wp_delete_file((string) $upload['file']);
            comkani_media_redirect($attachment_id, 'upload-error');
        }

        $source_file = (string) $temporary_source['file'];
        $source_temporary = true;
    } elseif (!$optimize && !$convert_webp && '' !== $requested_filename) {
        $source_file = comkani_media_original_file($old_file, $old_metadata);
    }

    if ($optimize || $convert_webp) {
        if (!comkani_media_is_optimizable_mime($source_mime)) {
            if ($source_temporary && file_exists($source_file)) {
                wp_delete_file($source_file);
            }
            comkani_media_redirect($attachment_id, 'optimization-unsupported');
        }

        if ($convert_webp && !comkani_media_webp_supported()) {
            if ($source_temporary && file_exists($source_file)) {
                wp_delete_file($source_file);
            }
            comkani_media_redirect($attachment_id, 'webp-unsupported');
        }

        $preview_file = comkani_media_create_optimized_preview(
            $source_file,
            $source_mime,
            $optimize,
            $convert_webp,
            $quality
        );

        if (is_wp_error($preview_file)) {
            if ($source_temporary && file_exists($source_file)) {
                wp_delete_file($source_file);
            }
            comkani_media_redirect($attachment_id, 'preview-error');
        }

        $preview_mime = (string) $preview_file['mime'];
        $preview_path = (string) $preview_file['file'];

        set_transient(
            comkani_media_preview_key($attachment_id),
            [
                'source_file' => $source_file,
                'source_temporary' => $source_temporary,
                'preview_file' => $preview_path,
                'preview_mime' => $preview_mime,
                'requested_filename' => $requested_filename,
                'quality' => $quality,
                'before' => comkani_media_file_info($source_file, $source_mime),
                'after' => comkani_media_file_info($preview_path, $preview_mime),
            ],
            comkani_media_preview_ttl()
        );

        comkani_media_redirect($attachment_id, 'preview');
    }

    $updated = comkani_media_commit_file(
        $attachment_id,
        $source_file,
        $source_mime,
        $requested_filename
    );

    if ($source_temporary && file_exists($source_file)) {
        wp_delete_file($source_file);
    }

    if (is_wp_error($updated)) {
        comkani_media_redirect($attachment_id, $updated->get_error_code());
    }

    comkani_media_redirect($attachment_id, 'success');
}

add_action('admin_post_comkani_media_update', 'comkani_handle_media_update');

/**
 * Applique une prévisualisation validée au média d'origine
 *
 * @return void
 */
function comkani_handle_media_apply_preview(): void
{
    if (!current_user_can('upload_files')) {
        wp_die('Vous n’avez pas l’autorisation de modifier les médias.');
    }

    $attachment_id = isset($_POST['attachment_id'])
        ? absint($_POST['attachment_id'])
        : 0;

    check_admin_referer('comkani_media_apply_preview_' . $attachment_id);

    if (!current_user_can('edit_post', $attachment_id)) {
        wp_die('Vous n’avez pas l’autorisation de modifier ce média.');
    }

    $preview = get_transient(comkani_media_preview_key($attachment_id));

    if (
        !is_array($preview)
        || empty($preview['preview_file'])
        || !file_exists($preview['preview_file'])
    ) {
        comkani_media_clear_preview($attachment_id);
        comkani_media_redirect($attachment_id, 'preview-expired');
    }

    $updated = comkani_media_commit_file(
        $attachment_id,
        (string) $preview['preview_file'],
        (string) $preview['preview_mime'],
        (string) $preview['requested_filename']
    );

    if (is_wp_error($updated)) {
        comkani_media_redirect($attachment_id, $updated->get_error_code());
    }

    comkani_media_clear_preview($attachment_id);
    comkani_media_redirect($attachment_id, 'success');
}

add_action(
    'admin_post_comkani_media_apply_preview',
    'comkani_handle_media_apply_preview'
);

/**
 * Annule et supprime une prévisualisation en attente
 *
 * @return void
 */
function comkani_handle_media_cancel_preview(): void
{
    if (!current_user_can('upload_files')) {
        wp_die('Vous n’avez pas l’autorisation de modifier les médias.');
    }

    $attachment_id = isset($_POST['attachment_id'])
        ? absint($_POST['attachment_id'])
        : 0;

    check_admin_referer('comkani_media_cancel_preview_' . $attachment_id);

    if (!current_user_can('edit_post', $attachment_id)) {
        wp_die('Vous n’avez pas l’autorisation de modifier ce média.');
    }

    comkani_media_clear_preview($attachment_id);
    wp_safe_redirect(comkani_media_replace_url($attachment_id));
    exit;
}

add_action(
    'admin_post_comkani_media_cancel_preview',
    'comkani_handle_media_cancel_preview'
);


//
// Nommage automatique des nouveaux médias
//

/**
 * Préfixe les nouveaux médias avec le slug du projet
 *
 * Exemple :
 * equipe-accompagnement-administratif.webp
 * devient
 * yellow-office-equipe-accompagnement-administratif.webp
 *
 * @param array $file Fichier envoyé à WordPress
 * @return array
 */
function comkani_media_prefix_upload_filename(array $file): array
{
    if (empty($file['name'])) {
        return $file;
    }

    $filename = sanitize_file_name(
        (string) $file['name']
    );

    if ('' === $filename) {
        return $file;
    }

    $extension = strtolower(
        (string) pathinfo(
            $filename,
            PATHINFO_EXTENSION
        )
    );

    $basename = comkani_media_prefix_basename(
        (string) pathinfo(
            $filename,
            PATHINFO_FILENAME
        )
    );

    if ('' === $basename) {
        return $file;
    }

    $file['name'] = $basename;

    if ('' !== $extension) {
        $file['name'] .= '.' . $extension;
    }

    return $file;
}

add_filter(
    'wp_handle_upload_prefilter',
    'comkani_media_prefix_upload_filename',
    20
);

//
// Légende conditionnelle
//

/**
 * Indique si la légende d'un média doit être affichée
 *
 * Pour un ancien média qui possède déjà une légende mais aucun réglage ComKani,
 * la légende reste activée afin d'éviter une régression lors de la migration
 *
 * @param int $attachment_id ID du média
 * @return bool
 */
function comkani_media_caption_enabled(int $attachment_id): bool
{
    if ($attachment_id < 1) {
        return false;
    }

    $stored = get_post_meta(
        $attachment_id,
        '_comkani_caption_enabled',
        true
    );

    if ('' !== $stored) {
        return '1' === $stored;
    }

    $caption = (string) get_post_field(
        'post_excerpt',
        $attachment_id
    );

    return '' !== trim($caption);
}

/**
 * Ajoute la case permettant d'activer l'affichage de la légende
 *
 * Le champ natif de légende reste géré par WordPress.
 * Le JavaScript ComKani le masque tant que la case n'est pas cochée
 *
 * @param array   $fields Champs complémentaires du média
 * @param WP_Post $attachment Média concerné
 * @return array
 */
function comkani_media_add_caption_toggle(
    array $fields,
    WP_Post $attachment
): array {
    if (!current_user_can('edit_post', $attachment->ID)) {
        return $fields;
    }

    $enabled = comkani_media_caption_enabled(
        (int) $attachment->ID
    );

    $fields['comkani_caption_enabled'] = [
        'label' => 'Légende',
        'input' => 'html',
        'html' => sprintf(
            '<div class="comkani-caption-field" data-comkani-caption-field>'
            . '<input type="hidden" name="attachments[%1$d][comkani_caption_present]" value="1">'
            . '<label class="comkani-caption-toggle">'
            . '<input type="checkbox" name="attachments[%1$d][comkani_caption_enabled]" value="1" data-comkani-caption-toggle%2$s>'
            . '<span>Afficher la légende sur le site</span>'
            . '</label>'
            . '</div>',
            (int) $attachment->ID,
            checked(
                $enabled,
                true,
                false
            )
        ),
        'helps' => 'Le champ de texte apparaît uniquement lorsque cette option est activée',
    ];

    return $fields;
}

add_filter(
    'attachment_fields_to_edit',
    'comkani_media_add_caption_toggle',
    20,
    2
);

/**
 * Enregistre le choix d'affichage de la légende
 *
 * Désactiver l'option ne supprime pas le texte de légende enregistré
 *
 * @param array $post Données du média
 * @param array $attachment Données du formulaire
 * @return array
 */
function comkani_media_save_caption_toggle(
    array $post,
    array $attachment
): array {
    if (
        empty(
            $attachment['comkani_caption_present']
        )
    ) {
        return $post;
    }

    $attachment_id = absint(
        $post['ID'] ?? 0
    );

    if ($attachment_id < 1) {
        return $post;
    }

    update_post_meta(
        $attachment_id,
        '_comkani_caption_enabled',
        !empty(
            $attachment['comkani_caption_enabled']
        )
            ? '1'
            : '0'
    );

    return $post;
}

add_filter(
    'attachment_fields_to_save',
    'comkani_media_save_caption_toggle',
    20,
    2
);

/**
 * Masque la légende côté front lorsqu'elle est désactivée
 *
 * @param string|false $caption Légende du média
 * @param int $attachment_id ID du média
 * @return string|false
 */
function comkani_media_filter_caption(
    $caption,
    int $attachment_id
) {
    if (
        !comkani_media_caption_enabled(
            $attachment_id
        )
    ) {
        return '';
    }

    return $caption;
}

add_filter(
    'wp_get_attachment_caption',
    'comkani_media_filter_caption',
    10,
    2
);

//
// Interface d'administration
//

/**
 * Ajoute les styles nécessaires à l'interface des médias
 *
 * @return void
 */
function comkani_media_admin_styles(): void
{
    if (!current_user_can('upload_files')) {
        return;
    }
    ?>
    <style>
        .comkani-caption-field{display:flex;flex-direction:column;gap:10px}
        .comkani-caption-toggle{display:flex!important;align-items:center;justify-content:flex-start;gap:8px;margin:0!important;text-align:left}
        .comkani-caption-toggle input[type=checkbox]{flex:0 0 auto;margin:0!important}
        .comkani-caption-native-hidden{display:none!important}
    </style>
    <?php
}

add_action(
    'admin_head',
    'comkani_media_admin_styles'
);

/**
 * Ajoute les interactions de la légende et du curseur de qualité
 *
 * Le MutationObserver permet aussi le fonctionnement dans la modale médias,
 * dont le contenu est injecté dynamiquement par WordPress
 *
 * @return void
 */
function comkani_media_admin_scripts(): void
{
    if (!current_user_can('upload_files')) {
        return;
    }
    ?>
    <script>
        (() => {
            const findNativeCaptionField = (toggle) => {
                const attachmentDetails = toggle.closest(
                    '.attachment-details'
                );

                if (attachmentDetails) {
                    const field = attachmentDetails.querySelector(
                        '.setting[data-setting="caption"]'
                    );

                    if (field) {
                        return field;
                    }
                }

                const modal = toggle.closest(
                    '.media-modal-content'
                );

                if (modal) {
                    const field = modal.querySelector(
                        '.setting[data-setting="caption"]'
                    );

                    if (field) {
                        return field;
                    }
                }

                const compatibility = toggle.closest(
                    '.compat-item'
                );

                if (compatibility) {
                    const parent = compatibility.parentElement
                        ? compatibility.parentElement.parentElement
                        : null;

                    if (parent) {
                        const field = parent.querySelector(
                            '.setting[data-setting="caption"]'
                        );

                        if (field) {
                            return field;
                        }
                    }
                }

                const legacyCaption = document.querySelector(
                    '#attachment_caption, #post_excerpt'
                );

                if (legacyCaption) {
                    return legacyCaption.closest(
                        'tr, .form-field, .setting'
                    ) || legacyCaption;
                }

                return null;
            };

            const initCaptionFields = (root = document) => {
                root.querySelectorAll(
                    '[data-comkani-caption-toggle]'
                ).forEach((toggle) => {
                    const captionField = findNativeCaptionField(
                        toggle
                    );

                    if (!captionField) {
                        return;
                    }

                    const sync = () => {
                        captionField.classList.toggle(
                            'comkani-caption-native-hidden',
                            !toggle.checked
                        );
                    };

                    if (
                        toggle.dataset.comkaniBound !== '1'
                    ) {
                        toggle.addEventListener(
                            'change',
                            sync
                        );

                        toggle.dataset.comkaniBound = '1';
                    }

                    sync();
                });
            };

            const initQualityRanges = (root = document) => {
                root.querySelectorAll(
                    '[data-comkani-quality-range]'
                ).forEach((range) => {
                    const container = range.closest(
                        '.comkani-media-quality'
                    );

                    if (!container) {
                        return;
                    }

                    const output = container.querySelector(
                        '[data-comkani-quality-output]'
                    );

                    if (!output) {
                        return;
                    }

                    const sync = () => {
                        output.textContent =
                            `${range.value} %`;
                    };

                    if (
                        range.dataset.comkaniBound !== '1'
                    ) {
                        range.addEventListener(
                            'input',
                            sync
                        );

                        range.dataset.comkaniBound = '1';
                    }

                    sync();
                });
            };

            const init = () => {
                initCaptionFields();
                initQualityRanges();
            };

            const start = () => {
                init();

                const observer = new MutationObserver(
                    (mutations) => {
                        mutations.forEach((mutation) => {
                            mutation.addedNodes.forEach((node) => {
                                if (!(node instanceof Element)) {
                                    return;
                                }

                                if (node.matches('[data-comkani-caption-toggle]')) {
                                    initCaptionFields(node.parentElement || node);
                                } else if (node.querySelector('[data-comkani-caption-toggle]')) {
                                    initCaptionFields(node);
                                }

                                if (node.matches('[data-comkani-quality-range]')) {
                                    initQualityRanges(node.parentElement || node);
                                } else if (node.querySelector('[data-comkani-quality-range]')) {
                                    initQualityRanges(node);
                                }
                            });
                        });
                    }
                );

                observer.observe(
                    document.body,
                    {
                        childList: true,
                        subtree: true,
                    }
                );
            };

            if ('loading' === document.readyState) {
                document.addEventListener(
                    'DOMContentLoaded',
                    start
                );
            } else {
                start();
            }
        })();
    </script>
    <?php
}

add_action(
    'admin_footer',
    'comkani_media_admin_scripts',
    100
);
