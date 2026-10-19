// =============================================================================
// Fichier : packages/comkani-core/assets/consent.js
// Rôle : gérer le consentement visiteurs et charger les services tiers autorisés
//
// Objectif :
// - ne charger aucun service non essentiel avant consentement
// - mémoriser le choix du visiteur dans un cookie first-party
// - permettre de modifier ou retirer le consentement à tout moment
// - charger les services configurés sans dépendance JavaScript externe
//
// Ce qu’il fait :
// - affiche la bannière lorsqu’aucun choix n’a encore été enregistré
// - gère Tout refuser, Tout accepter et Paramètres
// - applique automatiquement les catégories obligatoires
// - charge GA4, GTM, Meta Pixel, LinkedIn Insight, Hotjar et scripts externes
// - met à jour Google Consent Mode selon les choix du visiteur
// - recharge la page après modification du consentement
// - expose window.ComKaniConsent pour les autres composants du Starter
//
// Ce qu’il ne fait pas :
// - ne charge aucun service non autorisé
// - ne transmet pas les préférences au serveur WordPress
// - ne contient aucun identifiant client en dur
// - ne dépend pas des classes CSS pour identifier les actions
//
// Hooks techniques :
// - tous les événements reposent sur les attributs data-comkani-consent-*
// - les classes CSS peuvent évoluer sans casser la logique JavaScript
//
// Fichiers liés :
// - config/consent.json
// - packages/comkani-core/includes/consent.php
// - resources/scss/components/_consent.scss
// =============================================================================

(() => {
    'use strict';

    const config =
        window.ComKaniConsentConfig;

    if (
        !config
        || !config.cookieName
    ) {
        return;
    }

    const banner =
        document.querySelector(
            '[data-comkani-consent-banner]'
        );

    const modal =
        document.querySelector(
            '[data-comkani-consent-modal]'
        );

    const categoryInputs =
        Array.from(
            document.querySelectorAll(
                '[data-comkani-consent-category]'
            )
        );

    const loadedServices =
        new Set();

    //
    // CHOIX PAR DÉFAUT
    //

    /**
     * Retourne l’état initial des catégories.
     *
     * Les catégories obligatoires restent systématiquement actives.
     *
     * @returns {Record<string, boolean>} État initial.
     */
    function defaultChoices() {
        const choices = {};

        Object.entries(
            config.categories || {}
        ).forEach(
            ([key, category]) => {
                choices[key] =
                    Boolean(
                        category?.required
                    );
            }
        );

        return choices;
    }

    //
    // COOKIE DE CONSENTEMENT
    //

    /**
     * Lit un cookie first-party.
     *
     * @param {string} name Nom du cookie.
     * @returns {string|null} Valeur décodée ou null.
     */
    function readCookie(name) {
        const prefix =
            `${encodeURIComponent(name)}=`;

        const cookie =
            document.cookie
                .split('; ')
                .find(
                    (entry) =>
                        entry.startsWith(prefix)
                );

        return cookie
            ? decodeURIComponent(
                cookie.slice(
                    prefix.length
                )
            )
            : null;
    }

    /**
     * Enregistre les préférences dans un cookie first-party.
     *
     * @param {Record<string, boolean>} choices Préférences à enregistrer.
     * @returns {void}
     */
    function writeChoices(choices) {
        const days =
            Math.max(
                1,
                Number(
                    config.cookieLifetimeDays
                    || 180
                )
            );

        const maxAge =
            Math.round(
                days
                * 24
                * 60
                * 60
            );

        const secure =
            window.location.protocol
            === 'https:'
                ? '; Secure'
                : '';

        const payload =
            encodeURIComponent(
                JSON.stringify({
                    version: 1,
                    choices,
                    updatedAt:
                        new Date()
                            .toISOString(),
                })
            );

        document.cookie =
            `${encodeURIComponent(config.cookieName)}=${payload}; Path=/; Max-Age=${maxAge}; SameSite=Lax${secure}`;
    }

    /**
     * Retourne les préférences enregistrées.
     *
     * Les catégories obligatoires sont systématiquement réactivées.
     *
     * @returns {Record<string, boolean>|null} Préférences ou null.
     */
    function readChoices() {
        const raw =
            readCookie(
                config.cookieName
            );

        if (!raw) {
            return null;
        }

        try {
            const parsed =
                JSON.parse(raw);

            const choices = {
                ...defaultChoices(),
                ...(parsed?.choices || {}),
            };

            Object.entries(
                config.categories || {}
            ).forEach(
                ([key, category]) => {
                    if (
                        category?.required
                    ) {
                        choices[key] = true;
                    }
                }
            );

            return choices;
        } catch {
            return null;
        }
    }

    //
    // FORMULAIRE DES PRÉFÉRENCES
    //

    /**
     * Synchronise les switches avec les préférences actuelles.
     *
     * @param {Record<string, boolean>} choices Préférences à afficher.
     * @returns {void}
     */
    function syncInputs(choices) {
        categoryInputs.forEach(
            (input) => {
                const key =
                    input.dataset
                        .comkaniConsentCategory;

                if (
                    !key
                    || input.disabled
                ) {
                    return;
                }

                input.checked =
                    Boolean(
                        choices[key]
                    );
            }
        );
    }

    /**
     * Retourne les choix sélectionnés dans le modal.
     *
     * @returns {Record<string, boolean>} Préférences sélectionnées.
     */
    function choicesFromInputs() {
        const choices =
            defaultChoices();

        categoryInputs.forEach(
            (input) => {
                const key =
                    input.dataset
                        .comkaniConsentCategory;

                if (!key) {
                    return;
                }

                choices[key] =
                    input.disabled
                        ? true
                        : input.checked;
            }
        );

        return choices;
    }

    //
    // MODAL
    //

    /**
     * Ouvre la fenêtre de personnalisation.
     *
     * Lorsque le visiteur n’a encore fait aucun choix, la bannière est
     * temporairement masquée pendant l’affichage du panneau.
     *
     * @returns {void}
     */
    function openModal() {
        const choices =
            readChoices()
            || defaultChoices();

        syncInputs(
            choices
        );

        banner?.setAttribute(
            'hidden',
            ''
        );

        modal?.removeAttribute(
            'hidden'
        );

        document.documentElement
            .classList
            .add(
                'consent-open'
            );
    }

    /**
     * Ferme la fenêtre de personnalisation.
     *
     * Si aucun choix n’est encore enregistré, la bannière réapparaît.
     *
     * @returns {void}
     */
    function closeModal() {
        modal?.setAttribute(
            'hidden',
            ''
        );

        document.documentElement
            .classList
            .remove(
                'consent-open'
            );

        const choices =
            readChoices();

        if (!choices) {
            banner?.removeAttribute(
                'hidden'
            );

            return;
        }
    }

    //
    // CHARGEMENT DES SCRIPTS
    //

    /**
     * Charge un script externe une seule fois.
     *
     * @param {string} key Identifiant interne.
     * @param {string} src URL du script.
     * @param {Record<string, string|boolean>} [attributes={}] Attributs HTML.
     * @returns {HTMLScriptElement|null} Script créé ou null.
     */
    function loadScript(
        key,
        src,
        attributes = {}
    ) {
        if (
            !src
            || loadedServices.has(key)
        ) {
            return null;
        }

        const script =
            document.createElement(
                'script'
            );

        script.src = src;

        Object.entries(
            attributes
        ).forEach(
            ([name, value]) => {
                if (
                    typeof value
                    === 'boolean'
                ) {
                    script[name] =
                        value;

                    return;
                }

                script.setAttribute(
                    name,
                    String(value)
                );
            }
        );

        document.head
            .appendChild(
                script
            );

        loadedServices
            .add(key);

        return script;
    }

    //
    // GOOGLE CONSENT MODE
    //

    /**
     * Met à jour Google Consent Mode.
     *
     * @param {Record<string, boolean>} choices Préférences actives.
     * @returns {void}
     */
    function updateGoogleConsent(
        choices
    ) {
        if (
            typeof window.gtag
            !== 'function'
        ) {
            return;
        }

        window.gtag(
            'consent',
            'update',
            {
                analytics_storage:
                    choices.analytics
                        ? 'granted'
                        : 'denied',

                ad_storage:
                    choices.marketing
                        ? 'granted'
                        : 'denied',

                ad_user_data:
                    choices.marketing
                        ? 'granted'
                        : 'denied',

                ad_personalization:
                    choices.marketing
                        ? 'granted'
                        : 'denied',
            }
        );
    }

    //
    // GOOGLE ANALYTICS 4
    //

    /**
     * Charge Google Analytics 4.
     *
     * @param {object} service Configuration GA4.
     * @returns {void}
     */
    function loadGa4(service) {
        const measurementId =
            String(
                service?.measurementId
                || ''
            ).trim();

        if (!measurementId) {
            return;
        }

        loadScript(
            'ga4',
            `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`,
            {
                async: true,
            }
        );

        window.dataLayer =
            window.dataLayer
            || [];

        window.gtag =
            window.gtag
            /**
             * Gère gtag dans le module ComKani.
             *
             * Les paramètres, effets secondaires et droits doivent être vérifiés
             * avec les appels du module lors de sa maintenance.
             */
            || function gtag() {
                window.dataLayer
                    .push(arguments);
            };

        window.gtag(
            'js',
            new Date()
        );

        window.gtag(
            'config',
            measurementId,
            {
                anonymize_ip: true,
            }
        );
    }

    //
    // GOOGLE TAG MANAGER
    //

    /**
     * Charge Google Tag Manager.
     *
     * @param {object} service Configuration GTM.
     * @returns {void}
     */
    function loadGtm(service) {
        const containerId =
            String(
                service?.containerId
                || ''
            ).trim();

        if (
            !containerId
            || loadedServices.has(
                'gtm'
            )
        ) {
            return;
        }

        window.dataLayer =
            window.dataLayer
            || [];

        window.dataLayer.push({
            'gtm.start':
                Date.now(),

            event:
                'gtm.js',
        });

        loadScript(
            'gtm',
            `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(containerId)}`,
            {
                async: true,
            }
        );
    }

    //
    // META PIXEL
    //

    /**
     * Charge Meta Pixel.
     *
     * @param {object} service Configuration Meta Pixel.
     * @returns {void}
     */
    function loadMetaPixel(service) {
        const pixelId =
            String(
                service?.pixelId
                || ''
            ).trim();

        if (
            !pixelId
            || loadedServices.has(
                'metaPixel'
            )
        ) {
            return;
        }

        const fbq =
            window.fbq =
            window.fbq
            || function () {
                fbq.callMethod
                    ? fbq.callMethod.apply(
                        fbq,
                        arguments
                    )
                    : fbq.queue.push(
                        arguments
                    );
            };

        if (!window._fbq) {
            window._fbq =
                fbq;
        }

        fbq.push =
            fbq;

        fbq.loaded =
            true;

        fbq.version =
            '2.0';

        fbq.queue =
            fbq.queue
            || [];

        loadScript(
            'metaPixel',
            'https://connect.facebook.net/en_US/fbevents.js',
            {
                async: true,
            }
        );

        fbq(
            'init',
            pixelId
        );

        fbq(
            'track',
            'PageView'
        );
    }

    //
    // LINKEDIN INSIGHT
    //

    /**
     * Charge LinkedIn Insight Tag.
     *
     * @param {object} service Configuration LinkedIn.
     * @returns {void}
     */
    function loadLinkedIn(service) {
        const partnerId =
            String(
                service?.partnerId
                || ''
            ).trim();

        if (
            !partnerId
            || loadedServices.has(
                'linkedinInsight'
            )
        ) {
            return;
        }

        window._linkedin_partner_id =
            partnerId;

        window._linkedin_data_partner_ids =
            window._linkedin_data_partner_ids
            || [];

        window
            ._linkedin_data_partner_ids
            .push(
                partnerId
            );

        loadScript(
            'linkedinInsight',
            'https://snap.licdn.com/li.lms-analytics/insight.min.js',
            {
                async: true,
            }
        );
    }

    //
    // HOTJAR
    //

    /**
     * Charge Hotjar.
     *
     * @param {object} service Configuration Hotjar.
     * @returns {void}
     */
    function loadHotjar(service) {
        const siteId =
            Number(
                service?.siteId
                || 0
            );

        if (
            !siteId
            || loadedServices.has(
                'hotjar'
            )
        ) {
            return;
        }

        window.hj =
            window.hj
            || function () {
                (
                    window.hj.q =
                    window.hj.q
                    || []
                ).push(
                    arguments
                );
            };

        window._hjSettings = {
            hjid:
                siteId,

            hjsv:
                6,
        };

        loadScript(
            'hotjar',
            `https://static.hotjar.com/c/hotjar-${siteId}.js?sv=6`,
            {
                async: true,
            }
        );
    }

    //
    // SCRIPTS EXTERNES
    //

    /**
     * Charge les scripts externes génériques configurés par l’agence.
     *
     * @param {Array<object>} services Scripts externes.
     * @param {Record<string, boolean>} choices Préférences actives.
     * @returns {void}
     */
    function loadExternalScripts(
        services,
        choices
    ) {
        if (
            !Array.isArray(
                services
            )
        ) {
            return;
        }

        services.forEach(
            (
                service,
                index
            ) => {
                if (
                    !service?.enabled
                    || !service?.src
                ) {
                    return;
                }

                const category =
                    String(
                        service.category
                        || 'external'
                    );

                if (
                    !choices[
                        category
                    ]
                ) {
                    return;
                }

                loadScript(
                    `external-${String(service.key || index)}`,
                    String(
                        service.src
                    ),
                    {
                        async:
                            Boolean(
                                service.async
                            ),

                        defer:
                            Boolean(
                                service.defer
                            ),
                    }
                );
            }
        );
    }

    //
    // APPLICATION DES SERVICES
    //

    /**
     * Charge les services autorisés par les préférences courantes.
     *
     * @param {Record<string, boolean>} choices Préférences actives.
     * @returns {void}
     */
    function applyServices(
        choices
    ) {
        const services =
            config.services
            || {};

        updateGoogleConsent(
            choices
        );

        if (
            services.ga4?.enabled
            && choices[
                services.ga4.category
                || 'analytics'
            ]
        ) {
            loadGa4(
                services.ga4
            );
        }

        if (
            services.gtm?.enabled
            && choices[
                services.gtm.category
                || 'analytics'
            ]
        ) {
            loadGtm(
                services.gtm
            );
        }

        if (
            services.metaPixel?.enabled
            && choices[
                services.metaPixel.category
                || 'marketing'
            ]
        ) {
            loadMetaPixel(
                services.metaPixel
            );
        }

        if (
            services.linkedinInsight?.enabled
            && choices[
                services.linkedinInsight.category
                || 'marketing'
            ]
        ) {
            loadLinkedIn(
                services.linkedinInsight
            );
        }

        if (
            services.hotjar?.enabled
            && choices[
                services.hotjar.category
                || 'analytics'
            ]
        ) {
            loadHotjar(
                services.hotjar
            );
        }

        loadExternalScripts(
            services.externalScripts,
            choices
        );
    }

    //
    // ENREGISTREMENT DU CONSENTEMENT
    //

    /**
     * Enregistre les préférences puis recharge la page.
     *
     * Le rechargement permet notamment de retirer proprement les scripts
     * précédemment autorisés lorsqu’un consentement est modifié.
     *
     * @param {Record<string, boolean>} choices Préférences à enregistrer.
     * @returns {void}
     */
    function saveAndApply(
        choices
    ) {
        writeChoices(
            choices
        );

        window.location.reload();
    }

    /**
     * Retourne un choix global accepter/refuser.
     *
     * @param {boolean} accepted true pour accepter, false pour refuser.
     * @returns {Record<string, boolean>} Préférences complètes.
     */
    /**
     * Gère allChoices dans le module ComKani.
     *
     * Les paramètres, effets secondaires et droits doivent être vérifiés
     * avec les appels du module lors de sa maintenance.
     */
    function allChoices(
        accepted
    ) {
        const choices =
            defaultChoices();

        Object.entries(
            config.categories
            || {}
        ).forEach(
            (
                [
                    key,
                    category,
                ]
            ) => {
                choices[key] =
                    category?.required
                        ? true
                        : accepted;
            }
        );

        return choices;
    }

    //
    // ÉVÉNEMENTS
    //

    /**
     * Gère les actions du composant.
     *
     * @param {MouseEvent} event Événement de clic.
     * @returns {void}
     */
    function handleClick(event) {
        const target =
            event.target.closest(
                '[data-comkani-consent-accept], [data-comkani-consent-refuse], [data-comkani-consent-customize], [data-comkani-consent-save], [data-comkani-consent-close], [data-comkani-consent-manage]'
            );

        if (!target) {
            return;
        }

        if (
            target.matches(
                '[data-comkani-consent-accept]'
            )
        ) {
            saveAndApply(
                allChoices(
                    true
                )
            );

            return;
        }

        if (
            target.matches(
                '[data-comkani-consent-refuse]'
            )
        ) {
            saveAndApply(
                allChoices(
                    false
                )
            );

            return;
        }

        if (
            target.matches(
                '[data-comkani-consent-customize]'
            )
            || target.matches(
                '[data-comkani-consent-manage]'
            )
        ) {
            openModal();

            return;
        }

        if (
            target.matches(
                '[data-comkani-consent-save]'
            )
        ) {
            saveAndApply(
                choicesFromInputs()
            );

            return;
        }

        if (
            target.matches(
                '[data-comkani-consent-close]'
            )
        ) {
            closeModal();
        }
    }

    /**
     * Ferme le modal avec la touche Échap.
     *
     * @param {KeyboardEvent} event Événement clavier.
     * @returns {void}
     */
    function handleKeydown(
        event
    ) {
        if (
            event.key
            === 'Escape'
            && modal
            && !modal.hidden
        ) {
            closeModal();
        }
    }

    document.addEventListener(
        'click',
        handleClick
    );

    document.addEventListener(
        'keydown',
        handleKeydown
    );

    //
    // INITIALISATION
    //

    const storedChoices =
        readChoices();

    if (storedChoices) {
        applyServices(
            storedChoices
        );
    } else {
        banner?.removeAttribute(
            'hidden'
        );
    }

    //
    // API PUBLIQUE
    //

    window.ComKaniConsent = {
        getChoices: () =>
            readChoices()
            || defaultChoices(),

        openPreferences:
            openModal,

        acceptAll: () =>
            saveAndApply(
                allChoices(
                    true
                )
            ),

        refuseAll: () =>
            saveAndApply(
                allChoices(
                    false
                )
            ),
    };
})();
