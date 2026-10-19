<?php

// =============================================================================
// Fichier : packages/comkani-core/includes/consent.php
// Rôle : gérer le consentement cookies et le chargement conditionnel des
//        services tiers dans les projets issus du Starter ComKani
//
// Objectif :
// - fournir un socle RGPD réutilisable sans extension tierce
// - empêcher le chargement des services non essentiels avant consentement
// - centraliser les services de mesure, marketing et contenus externes
// - proposer une interface de consentement commune à tous les projets ComKani
//
// Ce qu’il fait :
// - lit la configuration générée depuis config/consent.json
// - charge le JavaScript du consentement lorsque le module est actif
// - applique Google Consent Mode avec un refus par défaut
// - affiche la bannière de consentement
// - affiche la fenêtre de personnalisation des préférences
// - expose un bouton permettant de rouvrir les préférences depuis le footer
// - transmet au JavaScript les catégories et services configurés
//
// Ce qu’il ne fait pas :
// - ne configure pas les comptes GA4, GTM ou régies publicitaires
// - ne remplace pas la page Cookies & confidentialité
// - ne garantit pas à lui seul la conformité juridique du projet
// - ne charge aucun service désactivé ou dépourvu d’identifiant
// - ne stocke aucune donnée personnelle dans WordPress
//
// Conventions :
// - .consent est la classe racine du composant
// - .banner et .modal définissent les deux variantes principales
// - les classes internes restent volontairement simples et contextualisées
// - les attributs data-comkani-consent-* sont réservés au JavaScript
//
// Fichiers liés :
// - config/consent.json
// - config/generated/project-config.php
// - packages/comkani-core/assets/consent.js
// - resources/scss/components/_consent.scss
// - footer.php
// =============================================================================

defined('ABSPATH') || exit;

//
// ACTIVATION DU MODULE
//

/**
 * Indique si le module de consentement est activé.
 *
 * @return bool true lorsque la gestion du consentement est active.
 */
function comkani_consent_enabled(): bool
{
    return (bool) comkani_core_get(
        'consent.enabled',
        false
    );
}

//
// CONFIGURATION FRONT
//

/**
 * Retourne la configuration publique transmise au JavaScript.
 *
 * Les identifiants tels qu’un Measurement ID GA4 ou un Container ID GTM
 * sont destinés au navigateur et ne constituent pas des secrets privés.
 *
 * @return array<string, mixed> Configuration publique du consentement.
 */
function comkani_consent_public_config(): array
{
    $default_language = sanitize_key(
        (string) comkani_core_get(
            'consent.defaultLanguage',
            'fr'
        )
    );

    $locale = function_exists('determine_locale')
        ? determine_locale()
        : get_locale();

    $language = sanitize_key(
        strtolower(
            substr((string) $locale, 0, 2)
        )
    );

    $translations = (array) comkani_core_get(
        'consent.translations',
        []
    );

    if (!isset($translations[$language])) {
        $language = $default_language;
    }

    $translation = isset($translations[$language])
        && is_array($translations[$language])
            ? $translations[$language]
            : [];

    $labels = is_array($translation['labels'] ?? null)
        ? $translation['labels']
        : [];

    $translated_categories = is_array($translation['categories'] ?? null)
        ? $translation['categories']
        : [];

    $categories = [];

    foreach ((array) comkani_core_get('consent.categories', []) as $key => $category) {
        $key = sanitize_key((string) $key);
        $category = is_array($category) ? $category : [];
        $translated = isset($translated_categories[$key])
            && is_array($translated_categories[$key])
                ? $translated_categories[$key]
                : [];

        $categories[$key] = [
            'label' => sanitize_text_field(
                (string) ($translated['label'] ?? $category['label'] ?? $key)
            ),
            'description' => sanitize_text_field(
                (string) ($translated['description'] ?? $category['description'] ?? '')
            ),
            'required' => !empty($category['required']),
        ];
    }

    $fallback_labels = [
        'title' => 'Vos préférences de confidentialité',
        'intro' => 'Nous utilisons des cookies nécessaires au fonctionnement du site.',
        'acceptAll' => 'Tout accepter',
        'refuseAll' => 'Tout refuser',
        'customize' => 'Paramètres',
        'save' => 'Enregistrer mes choix',
        'close' => 'Fermer',
        'learnMore' => 'En savoir plus',
        'manage' => 'Gérer mes cookies',
        'settingsTitle' => 'Préférences de confidentialité',
        'settingsIntro' => 'Choisissez les catégories que vous souhaitez autoriser.',
        'alwaysActive' => 'Toujours actif',
        'allow' => 'Autoriser',
    ];

    return [
        'cookieName' => sanitize_key(
            (string) comkani_core_get(
                'consent.cookieName',
                'comkani_consent'
            )
        ),
        'cookieLifetimeDays' => max(
            1,
            (int) comkani_core_get(
                'consent.cookieLifetimeDays',
                180
            )
        ),
        'language' => $language,
        'categories' => $categories,
        'services' => (array) comkani_core_get(
            'consent.services',
            []
        ),
        'labels' => array_merge(
            $fallback_labels,
            array_map(
                static fn($value): string => sanitize_text_field((string) $value),
                $labels
            )
        ),
    ];
}

//
// CHARGEMENT DES ASSETS
//

/**
 * Charge le JavaScript du consentement sur le front-office.
 *
 * Le style est compilé avec le SCSS principal du thème depuis :
 * resources/scss/components/_consent.scss
 *
 * @return void
 */
/**
 * Exécute la fonction consent enqueue assets.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function comkani_consent_enqueue_assets(): void
{
    if (
        is_admin()
        || !comkani_consent_enabled()
    ) {
        return;
    }

    wp_enqueue_script(
        'comkani-consent',
        comkani_core_url(
            'assets/consent.js'
        ),
        [],
        comkani_core_asset_version(
            'assets/consent.js'
        ),
        [
            'strategy' => 'defer',
            'in_footer' => true,
        ]
    );

    wp_localize_script(
        'comkani-consent',
        'ComKaniConsentConfig',
        comkani_consent_public_config()
    );
}
add_action(
    'wp_enqueue_scripts',
    'comkani_consent_enqueue_assets',
    30
);

//
// GOOGLE CONSENT MODE
//

/**
 * Applique l’état Google Consent Mode par défaut.
 *
 * Les autorisations analytiques et publicitaires restent refusées jusqu’à
 * ce qu’un consentement explicite soit enregistré par le visiteur.
 *
 * @return void
 */
function comkani_consent_render_google_default(): void
{
    if (
        is_admin()
        || !comkani_consent_enabled()
    ) {
        return;
    }

    echo "<script>\n";

    echo "window.dataLayer=window.dataLayer||[];\n";

    echo "window.gtag=window.gtag||function(){dataLayer.push(arguments);};\n";

    echo "gtag('consent','default',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',wait_for_update:500});\n";

    echo "</script>\n";
}
add_action(
    'wp_head',
    'comkani_consent_render_google_default',
    1
);

//
// PAGE COOKIES & CONFIDENTIALITÉ
//

/**
 * Retourne l’URL de la page Cookies & confidentialité.
 *
 * @return string URL de la politique ou chaîne vide si elle est indisponible.
 */
function comkani_consent_policy_url(): string
{
    if (
        !function_exists(
            'comkani_legal_page_definitions'
        )
    ) {
        return '';
    }

    $definitions =
        comkani_legal_page_definitions();

    $key = trim(
        (string) comkani_core_get(
            'consent.policyPage',
            'privacyCookies'
        )
    );

    if (
        $key === ''
        || !isset($definitions[$key])
    ) {
        return '';
    }

    $slug = trim(
        (string) (
            $definitions[$key]['slug']
            ?? ''
        )
    );

    if ($slug === '') {
        return '';
    }

    $page = get_page_by_path(
        $slug,
        OBJECT,
        'page'
    );

    if (
        !$page instanceof WP_Post
        || $page->post_status !== 'publish'
    ) {
        return '';
    }

    return (string) get_permalink(
        $page
    );
}

//
// BOUTON FOOTER
//

/**
 * Affiche le bouton permettant de rouvrir les préférences.
 *
 * Ce bouton peut être appelé depuis le footer du thème après qu’un choix
 * initial a déjà été enregistré.
 *
 * @param string $label Libellé visible.
 * @return void
 */
function comkani_consent_render_manage_button(
    string $label = ''
): void {
    if (!comkani_consent_enabled()) {
        return;
    }

    if ($label === '') {
        $config = comkani_consent_public_config();
        $label = (string) ($config['labels']['manage'] ?? 'Gérer mes cookies');
    }

    echo '<button type="button" class="consent-manage" data-comkani-consent-manage>';

    echo esc_html($label);

    echo '</button>';
}

//
// INTERFACE DU CONSENTEMENT
//

/**
 * Affiche la bannière et le panneau de personnalisation.
 *
 * Ordre définitif des actions principales :
 * Refuser → Accepter → Paramètres
 *
 * @return void
 */
function comkani_consent_render_ui(): void
{
    if (
        is_admin()
        || !comkani_consent_enabled()
    ) {
        return;
    }

    $config =
        comkani_consent_public_config();

    $categories = (array) (
        $config['categories']
        ?? []
    );

    $policy_url =
        comkani_consent_policy_url();

    ?>

    <section
        class="consent banner"
        data-comkani-consent-banner
        aria-labelledby="consent-title"
        hidden
    >
        <div class="content">

            <div class="text">
                <h2 id="consent-title">
                    <?php
                    echo esc_html(
                        $config['labels']['title']
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo esc_html(
                        $config['labels']['intro']
                    );
                    ?>

                    <?php if ($policy_url !== '') : ?>
                        <a
                            href="<?php echo esc_url($policy_url); ?>"
                        >
                            <?php echo esc_html($config['labels']['learnMore']); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>

            <div class="actions">

                <button
                    type="button"
                    class="button"
                    data-comkani-consent-refuse
                >
                    <?php
                    echo esc_html(
                        $config['labels']['refuseAll']
                    );
                    ?>
                </button>

                <button
                    type="button"
                    class="button"
                    data-comkani-consent-accept
                >
                    <?php
                    echo esc_html(
                        $config['labels']['acceptAll']
                    );
                    ?>
                </button>

                <button
                    type="button"
                    class="button settings"
                    data-comkani-consent-customize
                >
                    <?php
                    echo esc_html(
                        $config['labels']['customize']
                    );
                    ?>
                </button>

            </div>

        </div>
    </section>

    <div
        class="consent modal"
        data-comkani-consent-modal
        hidden
    >

        <button
            type="button"
            class="backdrop"
            data-comkani-consent-close
            aria-label="<?php echo esc_attr($config['labels']['close']); ?>"
        ></button>

        <section
            class="content"
            role="dialog"
            aria-modal="true"
            aria-labelledby="consent-settings-title"
        >

            <div class="header">

                <div>
                    <h2 id="consent-settings-title">
                        <?php echo esc_html($config['labels']['settingsTitle']); ?>
                    </h2>

                    <p>
                        <?php echo esc_html($config['labels']['settingsIntro']); ?>
                    </p>
                </div>

                <button
                    type="button"
                    class="close"
                    data-comkani-consent-close
                    aria-label="<?php echo esc_attr($config['labels']['close']); ?>"
                >
                    ×
                </button>

            </div>

            <div class="categories">

                <?php foreach ($categories as $key => $category) : ?>

                    <?php
                    $key = sanitize_key(
                        (string) $key
                    );

                    $category = (array) $category;

                    $required = !empty(
                        $category['required']
                    );

                    $label = (string) (
                        $category['label']
                        ?? $key
                    );

                    $description = (string) (
                        $category['description']
                        ?? ''
                    );
                    ?>

                    <div class="category">

                        <div class="text">

                            <h3>
                                <?php
                                echo esc_html(
                                    $label
                                );
                                ?>
                            </h3>

                            <p>
                                <?php
                                echo esc_html(
                                    $description
                                );
                                ?>
                            </p>

                        </div>

                        <label class="switch">

                            <input
                                type="checkbox"
                                data-comkani-consent-category="<?php echo esc_attr($key); ?>"
                                aria-label="<?php echo esc_attr(
                                    $required
                                        ? $label . ' : ' . $config['labels']['alwaysActive']
                                        : $config['labels']['allow'] . ' ' . $label
                                ); ?>"
                                <?php checked($required); ?>
                                <?php disabled($required); ?>
                            >

                        </label>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="actions">

                <button
                    type="button"
                    class="button"
                    data-comkani-consent-refuse
                >
                    <?php
                    echo esc_html(
                        $config['labels']['refuseAll']
                    );
                    ?>
                </button>

                <button
                    type="button"
                    class="button"
                    data-comkani-consent-save
                >
                    <?php
                    echo esc_html(
                        $config['labels']['save']
                    );
                    ?>
                </button>

            </div>

        </section>

    </div>

    <?php
}
add_action(
    'wp_footer',
    'comkani_consent_render_ui',
    5
);
