// =============================================================================
// Fichier : config/src/wordpress.ts
// Rôle : définir la structure WordPress de départ
//
// Ce qu’il fait :
// - déclare les pages de base et les réglages éditoriaux neutres
//
// Ce qu’il ne fait pas :
// - ne crée pas directement de contenu dans la base WordPress
// =============================================================================

import {defineWordPressConfig} from '../framework/wordpress.ts';

const config = defineWordPressConfig({
    pages: [
    {title: 'Accueil', slug: 'accueil', home: true},
    {title: 'Mentions légales', slug: 'mentions-legales'},
    {title: 'Politque de confidentialité', slug: 'politque-de-confidentialite'},
    {title: 'Plan du site', slug: 'plan-du-site'},
],
    wordpress: {
        posts: {
            enabled: true,
            indexPage: 'actualites',
            permalinkStructure: '/%postname%/',
            labels: {singular: 'Article', plural: 'Articles', gender: 'masculine'},
        },
    },
    legalPages: {
        mentions: false, privacyCookies: false, cgv: false, cgu: false,
        shippingReturns: false, refund: false, bookingTerms: false,
    },
    menus: {primary: [], footer: {}, legal: []},
    postTypes: [],
});

export default config;
