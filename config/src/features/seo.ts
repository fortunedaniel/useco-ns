// =============================================================================
// Fichier : config/src/features/seo.ts
// Rôle : définit les réglages SEO du projet
//
// Ce qu'il fait :
// - définit la configuration source du starter générique
// - fournit une configuration typée aux générateurs ComKani
//
// Ce qu'il ne fait pas :
// - ne modifie pas directement WordPress
// - n'est pas chargé par le serveur de production
// =============================================================================

import {defineConfig} from '../../framework/config.ts';

const config = defineConfig({
    seo: {
        enabled: true,
        technical: true,
        content: true,
        local: true,
        entity: true,
        aeo: true,
        geo: true,
        sxo: true,
        breadcrumbs: false,
        organizationType: 'ProfessionalService',
        schema: {
            enabled: true,
            website: true,
            webPage: true,
            organization: true,
            localBusiness: false,
            professionalService: true,
            service: true,
            breadcrumbList: false,
            faqPage: true,
            article: false,
            person: false
        },
        yoast: {
            enabled: true,
            avoidDuplicateSchema: true
        },
        indexing: {
            development: false,
            staging: false,
            production: true
        }
    }
});

export default config;
