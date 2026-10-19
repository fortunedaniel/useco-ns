// =============================================================================
// Fichier : config/src/project.ts
// Rôle : définit l’identité et les réglages généraux du projet
//
// Ce qu'il fait :
// - définit l’identité neutre du Starter ComKani
// - centralise les informations juridiques utilisées par les pages légales
// - centralise les coordonnées, réseaux sociaux et informations d’hébergement
// - référence le Design System utilisé par les générateurs ComKani
// - fournit une configuration TypeScript unique aux générateurs du projet
//
// Ce qu'il ne fait pas :
// - ne modifie pas directement WordPress
// - ne crée pas les pages légales dans la base de données
// - n'est pas chargé directement par le serveur de production
//
// Fichiers liés :
// - config/src/wordpress.ts
// - config/src/index.ts
// - templates/page-legal.php
// - templates/legal/mentions.php
// - templates/legal/privacy-cookies.php
// =============================================================================

import {defineProjectConfig} from '../framework/config.ts';

const config = defineProjectConfig({
    project: {
        name: 'ComKani Starter',
        slug: 'comkani-starter',
        type: 'multipage',
        language: 'fr_FR',
        timezone: 'Europe/Paris',
        tagline: '',
    },

    organization: {
        name: '', legalName: '', description: '',
        email: '', privacyEmail: '', phone: '',
        address: '', city: '', postalCode: '', country: 'France',
        areaServed: [], sameAs: [],
        legalForm: '', siren: '', siret: '', vatNumber: '', capital: '',
        registration: '', publicationDirector: '',
        professionalRegistration: {organization: '', region: '', number: ''},
        hosting: {name: '', legalName: '', address: '', postalCode: '', city: '', country: '', phone: '', url: ''},
    },

    designSystem: {
        theme: {
            defaultMode: 'light',
            supportsDarkMode: true,
        },

        tokens: {
            light: 'config/tokens/colors/light.tokens.json',
            dark: 'config/tokens/colors/dark.tokens.json',
            typography: 'config/tokens/typography',
            spacings: 'config/tokens/spacings',
            borders: 'config/tokens/borders.tokens.json',
            radius: 'config/tokens/radius.tokens.json',
            shadows: 'config/tokens/shadows.tokens.json',
            motions: 'config/tokens/motions.tokens.json',
            grid: 'config/tokens/grid.tokens.json',
            effects: 'config/tokens/effects.tokens.json',
        },

        foundations: {
            spacings: 'responsive',
            radius: 'figma',
            borders: 'figma',
            shadows: 'figma',
            motions: 'figma',
            grid: 'responsive',
            effects: [
                'glass',
            ],
        },

        typography: {
            primary: {
                family: 'Inter',
            },
            secondary: {
                family: 'Manrope',
            },
        },
    },

    footer: {socials: [], credit: {label: 'Agence ComKani', url: 'https://www.comkani.fr/'}},
});

export default config;
