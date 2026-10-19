// =============================================================================
// Fichier : config/src/features/consent.ts
// Rôle : définit la gestion du consentement RGPD et des cookies
//
// Ce qu'il fait :
// - active le bandeau de consentement du projet
// - distingue les cookies nécessaires des mesures d'audience optionnelles
// - prépare le chargement conditionnel des outils analytics
// - fournit les libellés français utilisés par l'interface ComKani
//
// Ce qu'il ne fait pas :
// - n'active aucun outil de mesure tant qu'aucun identifiant n'est renseigné
// - ne modifie pas directement WordPress
// - n'est pas chargé par le serveur de production
// =============================================================================

import {defineConfig} from '../../framework/config.ts';

const config = defineConfig({
    consent: {
        enabled: true,
        cookieName: 'comkani_consent',
        cookieLifetimeDays: 180,
        policyPage: 'privacyCookies',
        defaultLanguage: 'fr',
        categories: {
            necessary: {
                required: true,
            },
            analytics: {
                required: false,
            },
        },
        translations: {
            fr: {
                labels: {
                    title: 'Vos préférences de confidentialité',
                    intro: 'Nous utilisons des cookies nécessaires au fonctionnement du site et, avec votre accord, des outils de mesure d’audience.',
                    acceptAll: 'Tout accepter',
                    refuseAll: 'Tout refuser',
                    customize: 'Paramétrer',
                    save: 'Enregistrer mes choix',
                    close: 'Fermer',
                    learnMore: 'En savoir plus',
                    manage: 'Gérer mes cookies',
                    settingsTitle: 'Préférences de confidentialité',
                    settingsIntro: 'Choisissez les catégories que vous souhaitez autoriser.',
                    alwaysActive: 'Toujours actif',
                    allow: 'Autoriser',
                },
                categories: {
                    necessary: {
                        label: 'Nécessaires',
                        description: 'Indispensables au fonctionnement, à la sécurité et aux formulaires du site.',
                    },
                    analytics: {
                        label: 'Mesure d’audience',
                        description: 'Permet de mesurer l’utilisation du site lorsque vous donnez votre accord.',
                    },
                },
            },
        },
        services: {
            ga4: {
                enabled: false,
                category: 'analytics',
                measurementId: '',
            },
            gtm: {
                enabled: false,
                category: 'analytics',
                containerId: '',
            },
            hotjar: {
                enabled: false,
                category: 'analytics',
                siteId: '',
            },
            externalScripts: [],
        },
    },
});

export default config;
