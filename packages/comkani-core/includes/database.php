<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/database.php
// Rôle : fournir les outils agence de sauvegarde et de nettoyage de la BDD
//        WordPress depuis le back-office ComKani
//
// Objectif :
// - permettre à l'agence de contrôler et nettoyer une base avant livraison
// - créer une sauvegarde SQL avant toute opération destructive
// - identifier les reliquats historiques sans supprimer de données utiles
//
// Ce qu'il fait :
// - ajoute l'écran ComKani > Base de données pour les comptes agence
// - affiche un état des tables et contenus historiques détectés
// - exporte toutes les tables utilisant le préfixe WordPress courant
// - importe une sauvegarde SQL ComKani avec adaptation du préfixe et des URLs
// - crée une sauvegarde temporaire automatique avant chaque import
// - crée un fichier SQL téléchargeable compatible avec MySQL / MariaDB
// - nettoie les transients expirés, révisions et brouillons automatiques
// - supprime les anciennes tables connues uniquement lorsqu'elles sont vides
// - optimise les tables WordPress après le nettoyage
//
// Ce qu'il ne fait pas :
// - n'importe pas des dumps SQL arbitraires provenant d'un outil inconnu
// - ne modifie jamais le préfixe défini dans wp-config.php
// - ne remplace que l'URL source déclarée dans le dump ComKani
// - ne supprime jamais une ancienne table contenant encore des lignes
// - ne remplace pas une sauvegarde complète des fichiers et médias
//
// Fichiers liés :
// - packages/comkani-core/comkani-core.php
// - packages/comkani-core/includes/admin.php
// - config/security.json
// =============================================================================

defined('ABSPATH') || exit;

/**
 * Vérifie que le compte courant est un compte agence autorisé.
 *
 * La liste des comptes agence est centralisée dans
 * `activityLog.excludedUsers` afin d'éviter de dupliquer les identifiants
 * sensibles dans plusieurs modules du Core.
 *
 * @return bool true si le compte courant appartient à l'agence.
 */
function comkani_database_is_agency_user(): bool
{
    $user = wp_get_current_user();

    if (!$user instanceof WP_User || $user->ID <= 0) {
        return false;
    }

    $agency_logins = array_map(
        static fn($login): string => strtolower(trim((string) $login)),
        (array) comkani_core_get('activityLog.excludedUsers', [])
    );

    return current_user_can('manage_options')
        && current_user_can('comkani_manage_project')
        && in_array(strtolower((string) $user->user_login), $agency_logins, true);
}

/**
 * Retourne les anciennes tables connues pouvant être supprimées si elles sont
 * totalement vides.
 *
 * @return array<string, string> Nom de table sans préfixe => libellé humain.
 */
function comkani_database_legacy_tables(): array
{
    return [
        'comkani_booking_requests' => 'Ancienne table Booking ComKani',
        'wsal_metadata' => 'Ancien WP Activity Log - métadonnées',
        'wsal_occurrences' => 'Ancien WP Activity Log - événements',
    ];
}

/**
 * Retourne le nombre de lignes d'une table si elle existe.
 *
 * @param string $table Nom complet de la table.
 * @return int|null Nombre de lignes ou null si la table n'existe pas.
 */
function comkani_database_table_count(string $table): ?int
{
    global $wpdb;

    $exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $table)
    );

    if ($exists !== $table) {
        return null;
    }

    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM `" . esc_sql($table) . "`"
    );
}

/**
 * Retourne l'inventaire des reliquats BDD détectés.
 *
 * @return array<int, array<string, mixed>> Liste des éléments détectés.
 */
function comkani_database_inventory(): array
{
    global $wpdb;

    $items = [];

    foreach (comkani_database_legacy_tables() as $suffix => $label) {
        $table = $wpdb->prefix . $suffix;
        $count = comkani_database_table_count($table);

        if ($count === null) {
            continue;
        }

        $items[] = [
            'type' => 'table',
            'label' => $label,
            'target' => $table,
            'count' => $count,
            'safe' => $count === 0,
            'note' => $count === 0
                ? 'Table vide : suppression automatique possible.'
                : 'Table conservée : elle contient encore des données.',
        ];
    }

    $transient_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
    );

    if ($transient_count > 0) {
        $items[] = [
            'type' => 'temporary',
            'label' => 'Transients WordPress',
            'target' => $wpdb->options,
            'count' => $transient_count,
            'safe' => true,
            'note' => 'Données temporaires recréées automatiquement par WordPress ou les extensions.',
        ];
    }

    $revision_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
    );

    if ($revision_count > 0) {
        $items[] = [
            'type' => 'content',
            'label' => 'Révisions WordPress',
            'target' => $wpdb->posts,
            'count' => $revision_count,
            'safe' => true,
            'note' => 'Historique éditorial supprimable avant livraison après sauvegarde.',
        ];
    }

    $auto_draft_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
    );

    if ($auto_draft_count > 0) {
        $items[] = [
            'type' => 'content',
            'label' => 'Brouillons automatiques',
            'target' => $wpdb->posts,
            'count' => $auto_draft_count,
            'safe' => true,
            'note' => 'Brouillons techniques générés automatiquement par WordPress.',
        ];
    }

    $orphan_postmeta = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
    );

    if ($orphan_postmeta > 0) {
        $items[] = [
            'type' => 'orphan',
            'label' => 'Métadonnées de contenus orphelines',
            'target' => $wpdb->postmeta,
            'count' => $orphan_postmeta,
            'safe' => true,
            'note' => 'Métadonnées dont le contenu parent n’existe plus.',
        ];
    }

    $orphan_commentmeta = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
    );

    if ($orphan_commentmeta > 0) {
        $items[] = [
            'type' => 'orphan',
            'label' => 'Métadonnées de commentaires orphelines',
            'target' => $wpdb->commentmeta,
            'count' => $orphan_commentmeta,
            'safe' => true,
            'note' => 'Métadonnées dont le commentaire parent n’existe plus.',
        ];
    }

    $orphan_termmeta = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL"
    );

    if ($orphan_termmeta > 0) {
        $items[] = [
            'type' => 'orphan',
            'label' => 'Métadonnées de termes orphelines',
            'target' => $wpdb->termmeta,
            'count' => $orphan_termmeta,
            'safe' => true,
            'note' => 'Métadonnées dont le terme parent n’existe plus.',
        ];
    }

    $orphan_usermeta = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE u.ID IS NULL"
    );

    if ($orphan_usermeta > 0) {
        $items[] = [
            'type' => 'orphan',
            'label' => 'Métadonnées utilisateurs orphelines',
            'target' => $wpdb->usermeta,
            'count' => $orphan_usermeta,
            'safe' => true,
            'note' => 'Métadonnées dont l’utilisateur parent n’existe plus.',
        ];
    }

    $orphan_relationships = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL"
    );

    if ($orphan_relationships > 0) {
        $items[] = [
            'type' => 'orphan',
            'label' => 'Relations de taxonomies orphelines',
            'target' => $wpdb->term_relationships,
            'count' => $orphan_relationships,
            'safe' => true,
            'note' => 'Relations rattachées à des contenus qui n’existent plus.',
        ];
    }

    return $items;
}

/**
 * Retourne toutes les tables utilisant le préfixe WordPress courant.
 *
 * @return string[] Liste des tables du projet.
 */
function comkani_database_project_tables(): array
{
    global $wpdb;

    $like = $wpdb->esc_like($wpdb->prefix) . '%';
    $tables = $wpdb->get_col(
        $wpdb->prepare('SHOW TABLES LIKE %s', $like)
    );

    return array_values(array_filter(array_map('strval', (array) $tables)));
}

/**
 * Génère une représentation SQL sûre d'une valeur MySQL.
 *
 * @param mixed $value Valeur provenant d'une ligne de base.
 * @return string Valeur prête à être incluse dans une requête INSERT.
 */
function comkani_database_sql_value($value): string
{
    global $wpdb;

    if ($value === null) {
        return 'NULL';
    }

    return "'" . esc_sql((string) $value) . "'";
}

/**
 * Autorise les imports SQL en Local et en preproduction ; en production,
 * exige une constante explicite dans wp-config.php.
 *
 * @return bool
 */
function comkani_database_import_is_allowed(): bool
{
    return wp_get_environment_type() !== 'production'
        || (defined('COMKANI_ALLOW_DATABASE_IMPORT') && COMKANI_ALLOW_DATABASE_IMPORT === true);
}

/**
 * Télécharge immédiatement une sauvegarde SQL de la base du projet.
 *
 * L'export contient uniquement les tables utilisant le préfixe WordPress
 * courant. Il inclut leur structure ainsi que toutes les données.
 *
 * @return never
 */
function comkani_database_export(): never
{
    if (!comkani_database_is_agency_user()) {
        wp_die('Cette sauvegarde est réservée aux administrateurs de l’Agence ComKani.');
    }

    check_admin_referer('comkani_database_export');

    global $wpdb;

    $tables = comkani_database_project_tables();

    update_option(
        'comkani_database_last_export_at',
        time(),
        false
    );

    $filename = sprintf(
        'comkani-database-%s-%s.sql',
        sanitize_file_name((string) parse_url(home_url('/'), PHP_URL_HOST)),
        wp_date('Ymd-His')
    );

    nocache_headers();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "-- Sauvegarde BDD ComKani\n";
    echo '-- Site : ' . home_url('/') . "\n";
    echo '-- Date : ' . wp_date('c') . "\n";
    echo '-- Préfixe : ' . $wpdb->prefix . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $create = $wpdb->get_row("SHOW CREATE TABLE `" . esc_sql($table) . "`", ARRAY_N);

        if (!is_array($create) || !isset($create[1])) {
            continue;
        }

        echo 'DROP TABLE IF EXISTS `' . $table . "`;\n";
        echo (string) $create[1] . ";\n\n";

        $offset = 0;
        $limit = 250;

        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `" . esc_sql($table) . "` LIMIT %d OFFSET %d",
                    $limit,
                    $offset
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $columns = array_map(
                    static fn($column): string => '`' . str_replace('`', '``', (string) $column) . '`',
                    array_keys($row)
                );

                $values = array_map(
                    'comkani_database_sql_value',
                    array_values($row)
                );

                echo 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
            }

            $offset += $limit;
        } while (count((array) $rows) === $limit);

        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}
add_action('admin_post_comkani_database_export', 'comkani_database_export');

/**
 * Nettoie les éléments sûrs de la base après confirmation agence.
 *
 * Une ancienne table n'est supprimée que si elle contient exactement zéro
 * ligne. Les données historiques non vides restent en place et sont signalées
 * pour une décision manuelle.
 *
 * @return never
 */
function comkani_database_cleanup(): never
{
    if (!comkani_database_is_agency_user()) {
        wp_die('Ce nettoyage est réservé aux administrateurs de l’Agence ComKani.');
    }

    check_admin_referer('comkani_database_cleanup');

    global $wpdb;

    // Ne jamais supprimer globalement les transients : ils peuvent contenir
    // les compteurs anti-abus des formulaires et des etats de traitement.
    // WordPress sait supprimer uniquement les transients expires.
    delete_expired_transients(true);

    $post_ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' OR post_status = 'auto-draft'"
    );

    foreach ((array) $post_ids as $post_id) {
        wp_delete_post((int) $post_id, true);
    }

    $wpdb->query(
        "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
    );

    $wpdb->query(
        "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
    );

    $wpdb->query(
        "DELETE tm FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL"
    );

    $wpdb->query(
        "DELETE um FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE u.ID IS NULL"
    );

    $wpdb->query(
        "DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL"
    );

    foreach (array_keys(comkani_database_legacy_tables()) as $suffix) {
        $table = $wpdb->prefix . $suffix;
        $count = comkani_database_table_count($table);

        if ($count === 0) {
            $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($table) . '`');
        }
    }

    foreach (comkani_database_project_tables() as $table) {
        $wpdb->query('OPTIMIZE TABLE `' . esc_sql($table) . '`');
    }

    update_option(
        'comkani_database_last_cleanup_at',
        time(),
        false
    );

    wp_safe_redirect(
        add_query_arg(
            [
                'page' => 'comkani-database',
                'comkani_database_cleaned' => 1,
            ],
            admin_url('admin.php')
        )
    );
    exit;
}
add_action('admin_post_comkani_database_cleanup', 'comkani_database_cleanup');



// =============================================================================
// IMPORT / RESTAURATION COMKANI
// =============================================================================

/**
 * Lit les métadonnées écrites en tête d'une sauvegarde SQL ComKani.
 *
 * Le module d'import n'accepte volontairement que les sauvegardes générées par
 * ComKani. Cela permet de connaître l'URL source et le préfixe source avant de
 * lancer une opération destructive.
 *
 * @param string $path Chemin absolu du fichier SQL temporaire.
 * @return array{site:string,prefix:string}|WP_Error Métadonnées ou erreur.
 */
function comkani_database_read_dump_metadata(string $path)
{
    $handle = @fopen($path, 'rb');

    if (!is_resource($handle)) {
        return new WP_Error(
            'comkani_database_open_failed',
            'La sauvegarde SQL ne peut pas être ouverte.'
        );
    }

    $site = '';
    $prefix = '';
    $line_count = 0;

    while (($line = fgets($handle)) !== false && $line_count < 30) {
        $line_count += 1;
        $line = trim($line);

        if (str_starts_with($line, '-- Site :')) {
            $site = trim(substr($line, strlen('-- Site :')));
        }

        if (str_starts_with($line, '-- Préfixe :')) {
            $prefix = trim(substr($line, strlen('-- Préfixe :')));
        }

        if ($site !== '' && $prefix !== '') {
            break;
        }
    }

    fclose($handle);

    if ($site === '' || $prefix === '') {
        return new WP_Error(
            'comkani_database_invalid_dump',
            'Le fichier ne semble pas être une sauvegarde SQL ComKani valide.'
        );
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        return new WP_Error(
            'comkani_database_invalid_prefix',
            'Le préfixe source de la sauvegarde est invalide.'
        );
    }

    return [
        'site' => esc_url_raw($site),
        'prefix' => $prefix,
    ];
}

/**
 * Écrit la base actuelle dans un fichier SQL local temporaire.
 *
 * Cette sauvegarde est utilisée uniquement comme filet de sécurité avant un
 * import. Elle est créée dans le répertoire temporaire PHP, donc hors du thème
 * et hors des uploads publics dans une configuration serveur classique.
 *
 * @param string $path Chemin absolu du fichier de sortie.
 * @return true|WP_Error true en cas de succès.
 */
function comkani_database_export_to_file(string $path)
{
    global $wpdb;

    $handle = @fopen($path, 'wb');

    if (!is_resource($handle)) {
        return new WP_Error(
            'comkani_database_backup_failed',
            'La sauvegarde de sécurité n’a pas pu être créée.'
        );
    }

    fwrite($handle, "-- Sauvegarde BDD ComKani\n");
    fwrite($handle, '-- Site : ' . home_url('/') . "\n");
    fwrite($handle, '-- Date : ' . wp_date('c') . "\n");
    fwrite($handle, '-- Préfixe : ' . $wpdb->prefix . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    foreach (comkani_database_project_tables() as $table) {
        $safe_table = esc_sql($table);
        $create = $wpdb->get_row(
            "SHOW CREATE TABLE `{$safe_table}`",
            ARRAY_N
        );

        if (!is_array($create) || !isset($create[1])) {
            continue;
        }

        fwrite($handle, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
        fwrite($handle, (string) $create[1] . ";\n\n");

        $offset = 0;
        $limit = 250;

        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$safe_table}` LIMIT %d OFFSET %d",
                    $limit,
                    $offset
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $columns = array_map(
                    static fn($column): string => '`' . str_replace('`', '``', (string) $column) . '`',
                    array_keys($row)
                );

                $values = array_map(
                    'comkani_database_sql_value',
                    array_values($row)
                );

                fwrite(
                    $handle,
                    'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n"
                );
            }

            $offset += $limit;
        } while (count((array) $rows) === $limit);

        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);

    return true;
}

/**
 * Parcourt un dump SQL instruction par instruction sans charger tout le fichier
 * en mémoire.
 *
 * Le parseur couvre le format volontairement simple produit par l'exporteur
 * ComKani : commentaires `--`, chaînes simples/doubles, backticks et requêtes
 * terminées par un point-virgule.
 *
 * @param string $path Chemin absolu du dump.
 * @return Generator<int, string> Instructions SQL successives.
 */
/**
 * Exécute la fonction database sql statements.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkani_database_sql_statements(string $path): Generator
{
    $handle = fopen($path, 'rb');

    if (!is_resource($handle)) {
        return;
    }

    $statement = '';
    $single = false;
    $double = false;
    $backtick = false;
    $escaped = false;

    while (($line = fgets($handle)) !== false) {
        if ($statement === '' && str_starts_with(ltrim($line), '--')) {
            continue;
        }

        $length = strlen($line);

        for ($index = 0; $index < $length; $index += 1) {
            $char = $line[$index];
            $statement .= $char;

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\' && ($single || $double)) {
                $escaped = true;
                continue;
            }

            if ($char === "'" && !$double && !$backtick) {
                $single = !$single;
                continue;
            }

            if ($char === '"' && !$single && !$backtick) {
                $double = !$double;
                continue;
            }

            if ($char === '`' && !$single && !$double) {
                $backtick = !$backtick;
                continue;
            }

            if ($char === ';' && !$single && !$double && !$backtick) {
                $sql = trim($statement);
                $statement = '';

                if ($sql !== '') {
                    yield $sql;
                }
            }
        }
    }

    fclose($handle);

    $statement = trim($statement);

    if ($statement !== '') {
        yield $statement;
    }
}

/**
 * Remplace récursivement une valeur, y compris lorsqu'elle est sérialisée par
 * WordPress.
 *
 * @param mixed $value Valeur d'origine.
 * @param string $search Valeur recherchée.
 * @param string $replace Valeur de remplacement.
 * @return mixed Valeur transformée.
 */
function comkani_database_replace_serialized($value, string $search, string $replace)
{
    if ($search === '') {
        return $value;
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = comkani_database_replace_serialized(
                $item,
                $search,
                $replace
            );
        }

        return $value;
    }

    if (is_object($value)) {
        foreach (get_object_vars($value) as $key => $item) {
            $value->{$key} = comkani_database_replace_serialized(
                $item,
                $search,
                $replace
            );
        }

        return $value;
    }

    if (!is_string($value) || $value === '') {
        return $value;
    }

    if (is_serialized($value)) {
        $unserialized = maybe_unserialize($value);
        $replaced = comkani_database_replace_serialized(
            $unserialized,
            $search,
            $replace
        );

        return maybe_serialize($replaced);
    }

    return str_replace($search, $replace, $value);
}

/**
 * Remplace une ancienne URL dans les colonnes textuelles des tables du projet.
 *
 * La transformation respecte les valeurs sérialisées de WordPress. Les tables
 * sans clé primaire exploitable sont ignorées afin d'éviter toute mise à jour
 * ambiguë.
 *
 * @param string $search URL source.
 * @param string $replace URL cible.
 * @return int Nombre de cellules modifiées.
 */
function comkani_database_replace_url(string $search, string $replace): int
{
    global $wpdb;

    $search = untrailingslashit($search);
    $replace = untrailingslashit($replace);

    if ($search === '' || $replace === '' || $search === $replace) {
        return 0;
    }

    $changed = 0;

    foreach (comkani_database_project_tables() as $table) {
        $safe_table = esc_sql($table);
        $columns = (array) $wpdb->get_results(
            "SHOW COLUMNS FROM `{$safe_table}`",
            ARRAY_A
        );

        $text_columns = [];
        $primary_columns = [];

        foreach ($columns as $column) {
            $field = (string) ($column['Field'] ?? '');
            $type = strtolower((string) ($column['Type'] ?? ''));
            $key = (string) ($column['Key'] ?? '');

            if ($field === '') {
                continue;
            }

            if (preg_match('/(char|text|blob|json)/', $type)) {
                $text_columns[] = $field;
            }

            if ($key === 'PRI') {
                $primary_columns[] = $field;
            }
        }

        if ($text_columns === [] || $primary_columns === []) {
            continue;
        }

        $offset = 0;
        $limit = 200;

        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$safe_table}` LIMIT %d OFFSET %d",
                    $limit,
                    $offset
                ),
                ARRAY_A
            );

            foreach ((array) $rows as $row) {
                $updates = [];

                foreach ($text_columns as $column) {
                    if (!array_key_exists($column, $row) || !is_string($row[$column])) {
                        continue;
                    }

                    $new_value = comkani_database_replace_serialized(
                        $row[$column],
                        $search,
                        $replace
                    );

                    if ($new_value !== $row[$column]) {
                        $updates[$column] = $new_value;
                    }
                }

                if ($updates === []) {
                    continue;
                }

                $where = [];

                foreach ($primary_columns as $column) {
                    if (!array_key_exists($column, $row)) {
                        $where = [];
                        break;
                    }

                    $where[$column] = $row[$column];
                }

                if ($where === []) {
                    continue;
                }

                $result = $wpdb->update(
                    $table,
                    $updates,
                    $where
                );

                if ($result !== false) {
                    $changed += count($updates);
                }
            }

            $offset += $limit;
        } while (count((array) $rows) === $limit);
    }

    return $changed;
}

/**
 * Répare les clés WordPress dépendantes du préfixe après une migration.
 *
 * @param string $source_prefix Préfixe de la sauvegarde source.
 * @param string $target_prefix Préfixe WordPress de l'installation cible.
 * @return void
 */
function comkani_database_repair_prefix_keys(
    string $source_prefix,
    string $target_prefix
): void {
    global $wpdb;

    if ($source_prefix === $target_prefix) {
        return;
    }

    $source_roles = $source_prefix . 'user_roles';
    $target_roles = $target_prefix . 'user_roles';

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_name = %s WHERE option_name = %s",
            $target_roles,
            $source_roles
        )
    );

    $like = $wpdb->esc_like($source_prefix) . '%';
    $rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            $like
        )
    );

    foreach ((array) $rows as $meta_key) {
        $meta_key = (string) $meta_key;
        $new_key = $target_prefix . substr($meta_key, strlen($source_prefix));

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s",
                $new_key,
                $meta_key
            )
        );
    }
}

/**
 * Importe une sauvegarde SQL ComKani dans l'installation courante.
 *
 * Sécurités appliquées :
 * - accès agence uniquement
 * - nonce WordPress
 * - fichier .sql uniquement
 * - dump ComKani obligatoire
 * - sauvegarde temporaire automatique avant import
 * - adaptation du préfixe source vers le préfixe courant
 * - remplacement sérialisé de l'URL source vers l'URL cible
 * - restauration automatique de la sauvegarde temporaire si une requête échoue
 *
 * @return never
 */
/**
 * Exécute la fonction database import.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkani_database_import(): never
{
    if (!comkani_database_is_agency_user()) {
        wp_die('Cet import est réservé aux administrateurs de l’Agence ComKani.');
    }

    // L'import SQL remplace des tables entières : en production, une décision
    // explicite de l'agence est indispensable avant d'autoriser l'opération.
    if (!comkani_database_import_is_allowed()) {
        wp_die('Import SQL désactivé en production. Autorisation explicite requise.');
    }

    check_admin_referer('comkani_database_import');

    if (
        !isset($_FILES['database_file'])
        || !is_array($_FILES['database_file'])
        || (int) ($_FILES['database_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
    ) {
        wp_die('Le fichier SQL n’a pas été reçu correctement.');
    }

    $uploaded = $_FILES['database_file'];
    $name = sanitize_file_name((string) ($uploaded['name'] ?? ''));
    $tmp_name = (string) ($uploaded['tmp_name'] ?? '');
    $size = (int) ($uploaded['size'] ?? 0);

    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') {
        wp_die('Seules les sauvegardes SQL ComKani sont autorisées.');
    }

    $max_size = 100 * MB_IN_BYTES;

    if ($size <= 0 || $size > $max_size || !is_uploaded_file($tmp_name)) {
        wp_die('Le fichier SQL est invalide ou dépasse 100 Mo.');
    }

    $metadata = comkani_database_read_dump_metadata($tmp_name);

    if (is_wp_error($metadata)) {
        wp_die(esc_html($metadata->get_error_message()));
    }

    global $wpdb;

    $source_prefix = (string) $metadata['prefix'];
    $source_url = untrailingslashit((string) $metadata['site']);
    $target_prefix = $wpdb->prefix;
    $target_url = untrailingslashit(home_url('/'));

    $backup_path = trailingslashit(get_temp_dir())
        . 'comkani-before-import-'
        . wp_generate_uuid4()
        . '.sql';

    $backup_result = comkani_database_export_to_file($backup_path);

    if (is_wp_error($backup_result)) {
        wp_die(esc_html($backup_result->get_error_message()));
    }

    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $failed = null;

    foreach (comkani_database_sql_statements($tmp_name) as $statement) {
        $statement = str_replace(
            '`' . $source_prefix,
            '`' . $target_prefix,
            $statement
        );

        $result = $wpdb->query($statement);

        if ($result === false) {
            $failed = $wpdb->last_error !== ''
                ? $wpdb->last_error
                : 'Erreur SQL inconnue.';
            break;
        }
    }

    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');

    if ($failed !== null) {
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');

        foreach (comkani_database_sql_statements($backup_path) as $statement) {
            $wpdb->query($statement);
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        @unlink($backup_path);
        wp_cache_flush();

        wp_die(
            'L’import a échoué et la base précédente a été restaurée. Détail : '
            . esc_html($failed)
        );
    }

    comkani_database_repair_prefix_keys(
        $source_prefix,
        $target_prefix
    );

    $changed_cells = comkani_database_replace_url(
        $source_url,
        $target_url
    );

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name IN ('home', 'siteurl')",
            $target_url
        )
    );

    wp_cache_flush();
    flush_rewrite_rules(false);
    @unlink($backup_path);

    update_option(
        'comkani_database_last_import_at',
        time(),
        false
    );

    wp_safe_redirect(
        add_query_arg(
            [
                'page' => 'comkani-database',
                'comkani_database_imported' => 1,
                'comkani_database_replaced' => $changed_cells,
            ],
            admin_url('admin.php')
        )
    );

    exit;
}
add_action('admin_post_comkani_database_import', 'comkani_database_import');

/**
 * Affiche l'écran agence de sauvegarde et nettoyage de la base.
 *
 * @return void
 */
function comkani_database_render_admin_page(): void
{
    if (!comkani_database_is_agency_user()) {
        wp_die('Vous n’avez pas l’autorisation d’accéder à cette page.');
    }

    $inventory = comkani_database_inventory();
    $last_cleanup = (int) get_option('comkani_database_last_cleanup_at', 0);
    $last_export = (int) get_option('comkani_database_last_export_at', 0);

    echo '<div class="wrap comkani-admin-page">';
    echo '<h1>Base de données</h1>';
    echo '<p>Outils agence pour sauvegarder et nettoyer la base avant préproduction ou livraison.</p>';

    if (!empty($_GET['comkani_database_cleaned'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Le nettoyage de la base est terminé.</p></div>';
    }

    if (!empty($_GET['comkani_database_imported'])) {
        $replaced = absint($_GET['comkani_database_replaced'] ?? 0);
        echo '<div class="notice notice-success is-dismissible"><p>La base a été importée. URLs adaptées : ' . esc_html((string) $replaced) . ' cellule(s) modifiée(s).</p></div>';
    }

    echo '<h2>Sauvegarde</h2>';
    echo '<p>Avant tout nettoyage, télécharge une sauvegarde SQL de la base actuelle.</p>';
    if ($last_export > 0) {
        echo '<p>Dernier export ComKani : <strong>' . esc_html(wp_date('d/m/Y à H:i', $last_export)) . '</strong></p>';
    }

    echo '<p><a class="button button-primary" href="' . esc_url(
        wp_nonce_url(
            admin_url('admin-post.php?action=comkani_database_export'),
            'comkani_database_export'
        )
    ) . '">Exporter la base</a></p>';

    echo '<h2>Restauration / migration</h2>';
    if (!comkani_database_import_is_allowed()) {
        echo '<p><strong>Import SQL désactivé en production.</strong> Pour une migration exceptionnelle, l’agence doit l’autoriser temporairement dans la configuration WordPress, effectuer une sauvegarde complète, puis retirer l’autorisation.</p>';
    } else {
    echo '<p>Importe uniquement une sauvegarde SQL générée par ComKani. Le préfixe des tables et les URLs sont adaptés à l’installation cible. Une sauvegarde temporaire automatique est créée avant l’opération.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" onsubmit="return confirm(\'Cette opération remplace la base actuelle. Continuer ?\');">';
    echo '<input type="hidden" name="action" value="comkani_database_import">';
    wp_nonce_field('comkani_database_import');
    echo '<input type="file" name="database_file" accept=".sql,application/sql,text/plain" required>';
    echo ' <button type="submit" class="button button-secondary">Importer et remplacer la base</button>';
    echo '</form>';
    echo '<p><strong>Important :</strong> cet import restaure la base uniquement. Les fichiers médias et le thème doivent déjà être présents sur la cible.</p>';
    }

    echo '<h2>Éléments détectés</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Élément</th><th>Cible</th><th>Volume</th><th>Traitement</th><th>Remarque</th></tr></thead><tbody>';

    if ($inventory === []) {
        echo '<tr><td colspan="5">Aucun reliquat connu détecté.</td></tr>';
    }

    foreach ($inventory as $item) {
        echo '<tr>';
        echo '<td>' . esc_html((string) $item['label']) . '</td>';
        echo '<td><code>' . esc_html((string) $item['target']) . '</code></td>';
        echo '<td>' . esc_html((string) $item['count']) . '</td>';
        echo '<td>' . ($item['safe']
            ? '<span class="comkani-status-value is-success">Nettoyable</span>'
            : '<span class="comkani-status-value is-warning">À vérifier manuellement</span>') . '</td>';
        echo '<td>' . esc_html((string) ($item['note'] ?? '')) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    if ($last_cleanup > 0) {
        echo '<p>Dernier nettoyage : <strong>' . esc_html(wp_date('d/m/Y à H:i', $last_cleanup)) . '</strong></p>';
    }

    echo '<h2>Nettoyage</h2>';
    echo '<p>Le nettoyage supprime uniquement les transients expirés et les reliquats explicitement considérés comme sûrs. Les compteurs anti-abus actifs ne sont pas supprimés. Une ancienne table contenant encore des données n’est jamais supprimée automatiquement. Les médias ne sont jamais supprimés par cette action.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Confirmer le nettoyage de la base ? Une sauvegarde SQL est recommandée avant de continuer.\');">';
    echo '<input type="hidden" name="action" value="comkani_database_cleanup">';
    wp_nonce_field('comkani_database_cleanup');
    echo '<button type="submit" class="button button-secondary">Nettoyer la base</button>';
    echo '</form>';
    echo '</div>';
}
