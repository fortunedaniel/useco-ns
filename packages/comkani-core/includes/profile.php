<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/profile.php
// Rôle : fournir un écran Profil simplifié et uniforme dans le back-office
//        WordPress des projets issus du Starter ComKani
//
// Objectif :
// - remplacer l’écran Profil natif de WordPress par une interface volontairement
//   limitée aux réglages utiles au quotidien
// - proposer le même affichage aux administrateurs de l’agence, aux
//   Gestionnaires du site et aux Collaborateurs
// - éviter d’exposer les réglages WordPress, Yoast ou extensions qui ne sont
//   pas nécessaires dans le profil personnel
//
// Ce qu’il fait :
// - redirige /wp-admin/profile.php vers le profil ComKani
// - ajoute une entrée Profil aux utilisateurs qui n’ont pas accès au menu Comptes
// - permet d’afficher ou masquer la barre d’outils WordPress sur le front-office
// - permet de modifier l’adresse e-mail du compte connecté
// - permet de modifier le mot de passe du compte connecté
// - contrôle les nonces et les permissions avant chaque modification
// - affiche des messages de succès ou d’erreur après traitement
// - journalise les modifications utiles si le module Activité ComKani est chargé
//
// Ce qu’il ne fait pas :
// - ne permet pas de modifier le nom, prénom, pseudo ou nom public
// - ne permet pas de modifier le site web, la biographie ou les réseaux sociaux
// - ne permet pas de modifier les couleurs d’administration ou la langue
// - ne permet pas de gérer les sessions ou les mots de passe d’application
// - ne permet pas de modifier les réglages Yoast ou ceux d’autres extensions
// - ne permet pas de gérer les autres utilisateurs
//
// Fichiers liés :
// - packages/comkani-core/comkani-core.php
// - packages/comkani-core/includes/admin.php
// - packages/comkani-core/includes/activity-log.php
// - packages/comkani-core/assets/admin.css
//
// Sécurité :
// - l’utilisateur doit être connecté
// - chaque formulaire possède son propre nonce WordPress
// - un utilisateur ne peut modifier que son propre profil depuis cet écran
// - aucun mot de passe n’est enregistré dans le journal d’activité
// =============================================================================

defined('ABSPATH') || exit;

//
// CONSTANTES DU MODULE
//

if (!defined('COMKANI_PROFILE_PAGE_SLUG')) {
    define('COMKANI_PROFILE_PAGE_SLUG', 'comkani-profile');
}

//
// OUTILS DU PROFIL
//

/**
 * Retourne l’URL de l’écran Profil ComKani.
 *
 * @param array<string, scalar> $args Paramètres optionnels à ajouter à l’URL.
 * @return string URL complète du profil.
 */
function comkani_profile_url(array $args = []): string
{
    $url = admin_url(
        'admin.php?page=' . COMKANI_PROFILE_PAGE_SLUG
    );

    if ($args !== []) {
        $url = add_query_arg(
            $args,
            $url
        );
    }

    return $url;
}

/**
 * Vérifie si l’utilisateur courant peut accéder à son profil ComKani.
 *
 * La capacité `read` est utilisée car tout compte pouvant accéder au
 * back-office WordPress doit pouvoir gérer ses propres informations.
 *
 * @return bool true si le profil est accessible.
 */
function comkani_profile_can_access(): bool
{
    return is_user_logged_in()
        && current_user_can('read');
}

/**
 * Redirige vers le profil ComKani après une action.
 *
 * Cette fonction centralise les redirections afin d’éviter la duplication
 * des URLs et garantit une sortie immédiate après `wp_safe_redirect()`.
 *
 * @param array<string, scalar> $args Paramètres affichant le résultat de l’action.
 * @return never
 */
function comkani_profile_redirect(array $args = []): never
{
    wp_safe_redirect(
        comkani_profile_url($args)
    );

    exit;
}

//
// REDIRECTION DU PROFIL WORDPRESS NATIF
//

/**
 * Redirige l’écran WordPress natif `profile.php` vers le profil ComKani.
 *
 * Cette règle s’applique à tous les utilisateurs connectés, y compris les
 * administrateurs de l’Agence ComKani. Les droits administratifs ne sont pas
 * supprimés : seule l’interface du profil personnel est simplifiée.
 *
 * @return void
 */
function comkani_profile_redirect_native_page(): void
{
    global $pagenow;

    if (
        !is_admin()
        || !comkani_profile_can_access()
        || $pagenow !== 'profile.php'
    ) {
        return;
    }

    comkani_profile_redirect();
}
add_action(
    'admin_init',
    'comkani_profile_redirect_native_page',
    20
);

//
// ENREGISTREMENT DE LA PAGE PROFIL
//

/**
 * Enregistre l’écran Profil ComKani dans l’administration WordPress.
 *
 * Pour un utilisateur qui possède `list_users`, le profil reste accessible
 * depuis le sous-menu Comptes > Profil. Pour les rôles ne disposant pas du
 * menu Comptes, une entrée Profil autonome est ajoutée à la sidebar.
 *
 * @return void
 */
function comkani_profile_register_admin_page(): void
{
    if (!comkani_profile_can_access()) {
        return;
    }

    if (current_user_can('list_users')) {
        add_users_page(
            'Profil',
            'Profil',
            'read',
            COMKANI_PROFILE_PAGE_SLUG,
            'comkani_profile_render_page'
        );

        return;
    }

    add_menu_page(
        'Profil',
        'Profil',
        'read',
        COMKANI_PROFILE_PAGE_SLUG,
        'comkani_profile_render_page',
        'dashicons-admin-users',
        90
    );
}
add_action(
    'admin_menu',
    'comkani_profile_register_admin_page',
    99
);

//
// TRAITEMENT DE LA BARRE D’OUTILS
//

/**
 * Met à jour l’affichage de la barre d’outils WordPress sur le front-office.
 *
 * La préférence est stockée dans l’option utilisateur native `show_admin_bar_front`.
 * Elle permet uniquement de choisir si la barre d’administration doit être
 * visible lorsque l’utilisateur connecté consulte le site.
 *
 * @return void
 */
function comkani_profile_handle_toolbar_update(): void
{
    if (
        !isset($_POST['comkani_profile_action'])
        || $_POST['comkani_profile_action'] !== 'toolbar'
    ) {
        return;
    }

    if (!comkani_profile_can_access()) {
        wp_die(
            'Vous n’avez pas l’autorisation de modifier ce profil.'
        );
    }

    check_admin_referer(
        'comkani_profile_update_toolbar',
        'comkani_profile_toolbar_nonce'
    );

    $user_id = get_current_user_id();
    $enabled = isset($_POST['show_admin_bar_front'])
        ? 'true'
        : 'false';

    update_user_option(
        $user_id,
        'show_admin_bar_front',
        $enabled,
        true
    );

    if (function_exists('comkani_activity_log')) {
        comkani_activity_log(
            'user_updated',
            'user',
            $user_id,
            wp_get_current_user()->user_login,
            [
                'changed_fields' => [
                    'Barre d’outils',
                ],
            ],
            $user_id
        );
    }

    comkani_profile_redirect([
        'comkani_profile_updated' => 'toolbar',
    ]);
}
add_action(
    'admin_init',
    'comkani_profile_handle_toolbar_update',
    30
);

//
// TRAITEMENT DE L’ADRESSE E-MAIL
//

/**
 * Met à jour l’adresse e-mail du compte actuellement connecté.
 *
 * L’adresse est normalisée avec `sanitize_email()` puis validée avec
 * `is_email()`. WordPress vérifie ensuite lui-même l’unicité de l’adresse via
 * `wp_update_user()`.
 *
 * @return void
 */
function comkani_profile_handle_email_update(): void
{
    if (
        !isset($_POST['comkani_profile_action'])
        || $_POST['comkani_profile_action'] !== 'email'
    ) {
        return;
    }

    if (!comkani_profile_can_access()) {
        wp_die(
            'Vous n’avez pas l’autorisation de modifier ce profil.'
        );
    }

    check_admin_referer(
        'comkani_profile_update_email',
        'comkani_profile_email_nonce'
    );

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user instanceof WP_User) {
        comkani_profile_redirect([
            'comkani_profile_error' => 'user',
        ]);
    }

    $email = isset($_POST['email'])
        ? sanitize_email(
            wp_unslash(
                (string) $_POST['email']
            )
        )
        : '';

    if (
        $email === ''
        || !is_email($email)
    ) {
        comkani_profile_redirect([
            'comkani_profile_error' => 'email',
        ]);
    }

    if (
        strtolower($email)
        === strtolower((string) $user->user_email)
    ) {
        comkani_profile_redirect([
            'comkani_profile_updated' => 'email',
        ]);
    }

    $result = wp_update_user([
        'ID' => $user_id,
        'user_email' => $email,
    ]);

    if (is_wp_error($result)) {
        $error_code = $result->get_error_code();

        comkani_profile_redirect([
            'comkani_profile_error' => (
                $error_code === 'existing_user_email'
                ? 'email_exists'
                : 'email_update'
            ),
        ]);
    }

    comkani_profile_redirect([
        'comkani_profile_updated' => 'email',
    ]);
}
add_action(
    'admin_init',
    'comkani_profile_handle_email_update',
    30
);

//
// TRAITEMENT DU MOT DE PASSE
//

/**
 * Met à jour le mot de passe du compte actuellement connecté.
 *
 * Le nouveau mot de passe n’est jamais journalisé ni ajouté à une URL.
 * Après modification, une nouvelle authentification est créée pour maintenir
 * l’utilisateur connecté à l’administration WordPress.
 *
 * @return void
 */
function comkani_profile_handle_password_update(): void
{
    if (
        !isset($_POST['comkani_profile_action'])
        || $_POST['comkani_profile_action'] !== 'password'
    ) {
        return;
    }

    if (!comkani_profile_can_access()) {
        wp_die(
            'Vous n’avez pas l’autorisation de modifier ce profil.'
        );
    }

    check_admin_referer(
        'comkani_profile_update_password',
        'comkani_profile_password_nonce'
    );

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user instanceof WP_User) {
        comkani_profile_redirect([
            'comkani_profile_error' => 'user',
        ]);
    }

    $password = isset($_POST['new_password'])
        ? (string) wp_unslash(
            $_POST['new_password']
        )
        : '';

    $confirmation = isset($_POST['confirm_password'])
        ? (string) wp_unslash(
            $_POST['confirm_password']
        )
        : '';

    if ($password === '') {
        comkani_profile_redirect([
            'comkani_profile_error' => 'password_empty',
        ]);
    }

    if ($password !== $confirmation) {
        comkani_profile_redirect([
            'comkani_profile_error' => 'password_match',
        ]);
    }

    /*
     * WordPress n’impose pas de longueur minimale à `wp_set_password()`.
     * Le Starter impose ici huit caractères pour éviter les mots de passe
     * manifestement trop faibles sans ajouter une dépendance externe.
     */
    if (strlen($password) < 8) {
        comkani_profile_redirect([
            'comkani_profile_error' => 'password_length',
        ]);
    }

    wp_set_password(
        $password,
        $user_id
    );

    /*
     * `wp_set_password()` invalide les sessions existantes. Le compte ayant
     * lui-même demandé cette modification, une nouvelle session sécurisée est
     * immédiatement créée pour éviter une déconnexion brutale du back-office.
     */
    wp_set_current_user(
        $user_id,
        $user->user_login
    );

    wp_set_auth_cookie(
        $user_id,
        true,
        is_ssl()
    );

    do_action(
        'wp_login',
        $user->user_login,
        $user
    );

    if (function_exists('comkani_activity_log')) {
        comkani_activity_log(
            'user_updated',
            'user',
            $user_id,
            (string) $user->user_login,
            [
                'changed_fields' => [
                    'Mot de passe',
                ],
            ],
            $user_id
        );
    }

    comkani_profile_redirect([
        'comkani_profile_updated' => 'password',
    ]);
}
add_action(
    'admin_init',
    'comkani_profile_handle_password_update',
    30
);

//
// MESSAGES D’ÉTAT
//

/**
 * Retourne le message de succès correspondant à la dernière action.
 *
 * @param string $code Code transmis après la redirection.
 * @return string Message utilisateur ou chaîne vide.
 */
function comkani_profile_success_message(string $code): string
{
    $messages = [
        'toolbar' => 'L’affichage de la barre d’outils a été mis à jour.',
        'email' => 'L’adresse e-mail a été mise à jour.',
        'password' => 'Le mot de passe a été mis à jour.',
    ];

    return $messages[$code] ?? '';
}

/**
 * Retourne le message d’erreur correspondant à la dernière action.
 *
 * @param string $code Code transmis après la redirection.
 * @return string Message utilisateur ou chaîne vide.
 */
function comkani_profile_error_message(string $code): string
{
    $messages = [
        'user' => 'Le compte utilisateur n’a pas pu être chargé.',
        'email' => 'L’adresse e-mail renseignée n’est pas valide.',
        'email_exists' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
        'email_update' => 'WordPress n’a pas pu mettre à jour l’adresse e-mail.',
        'password_empty' => 'Renseignez un nouveau mot de passe.',
        'password_match' => 'Les deux mots de passe ne correspondent pas.',
        'password_length' => 'Le mot de passe doit contenir au moins 8 caractères.',
    ];

    return $messages[$code] ?? '';
}

/**
 * Affiche les notifications de succès ou d’erreur du profil.
 *
 * Les messages sont affichés uniquement sur l’écran Profil ComKani.
 *
 * @return void
 */
function comkani_profile_render_notices(): void
{
    $success_code = isset($_GET['comkani_profile_updated'])
        ? sanitize_key(
            wp_unslash(
                (string) $_GET['comkani_profile_updated']
            )
        )
        : '';

    $error_code = isset($_GET['comkani_profile_error'])
        ? sanitize_key(
            wp_unslash(
                (string) $_GET['comkani_profile_error']
            )
        )
        : '';

    $success_message = comkani_profile_success_message(
        $success_code
    );

    $error_message = comkani_profile_error_message(
        $error_code
    );

    if ($success_message !== '') {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . esc_html($success_message) . '</p>';
        echo '</div>';
    }

    if ($error_message !== '') {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p>' . esc_html($error_message) . '</p>';
        echo '</div>';
    }
}

//
// AFFICHAGE DU PROFIL
//

/**
 * Affiche l’écran Profil ComKani simplifié.
 *
 * L’écran contient volontairement uniquement :
 * - l’affichage de la barre d’outils sur le site
 * - l’adresse e-mail
 * - le nouveau mot de passe
 * - la confirmation du mot de passe
 *
 * @return void
 */
function comkani_profile_render_page(): void
{
    if (!comkani_profile_can_access()) {
        wp_die(
            'Vous n’avez pas l’autorisation d’accéder à cette page.'
        );
    }

    $user = wp_get_current_user();

    if (!$user instanceof WP_User) {
        wp_die(
            'Le compte utilisateur n’a pas pu être chargé.'
        );
    }

    $show_admin_bar_front = (
        get_user_option(
            'show_admin_bar_front',
            $user->ID
        ) !== 'false'
    );

    echo '<div class="wrap comkani-admin-page">';
    echo '<h1>Profil</h1>';

    comkani_profile_render_notices();

    ?>
    <section class="comkani-profile-section">
        <h2>Barre d’outils</h2>

        <form method="post" action="">
            <?php
            wp_nonce_field(
                'comkani_profile_update_toolbar',
                'comkani_profile_toolbar_nonce'
            );
            ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            Barre d’outils
                        </th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="show_admin_bar_front"
                                    value="1"
                                    <?php checked($show_admin_bar_front); ?>
                                >
                                Afficher la barre d’outils lorsque vous visitez le site
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button
                    type="submit"
                    name="comkani_profile_action"
                    value="toolbar"
                    class="button button-primary"
                >
                    Mettre à jour la barre d’outils
                </button>
            </p>
        </form>
    </section>

    <section class="comkani-profile-section">
        <h2>Adresse e-mail</h2>

        <form method="post" action="">
            <?php
            wp_nonce_field(
                'comkani_profile_update_email',
                'comkani_profile_email_nonce'
            );
            ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="comkani-profile-email">
                                Adresse e-mail
                            </label>
                        </th>
                        <td>
                            <input
                                type="email"
                                id="comkani-profile-email"
                                name="email"
                                value="<?php echo esc_attr($user->user_email); ?>"
                                class="regular-text"
                                autocomplete="email"
                                required
                            >
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button
                    type="submit"
                    name="comkani_profile_action"
                    value="email"
                    class="button button-primary"
                >
                    Mettre à jour l’adresse e-mail
                </button>
            </p>
        </form>
    </section>

    <section class="comkani-profile-section">
        <h2>Nouveau mot de passe</h2>

        <form method="post" action="">
            <?php
            wp_nonce_field(
                'comkani_profile_update_password',
                'comkani_profile_password_nonce'
            );
            ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="comkani-profile-new-password">
                                Nouveau mot de passe
                            </label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="comkani-profile-new-password"
                                name="new_password"
                                class="regular-text"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="comkani-profile-confirm-password">
                                Confirmer le mot de passe
                            </label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="comkani-profile-confirm-password"
                                name="confirm_password"
                                class="regular-text"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            >
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="submit">
                <button
                    type="submit"
                    name="comkani_profile_action"
                    value="password"
                    class="button button-primary"
                >
                    Mettre à jour le mot de passe
                </button>
            </p>
        </form>
    </section>
    <?php

    echo '</div>';
}
