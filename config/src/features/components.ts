// =============================================================================
// Fichier : config/src/features/components.ts
// Rôle : déclarer les composants disponibles et leur activation
//
// Ce qu’il fait :
// - définit les composants activés et les paramètres neutres
//
// Ce qu’il ne fait pas :
// - ne compile pas lui-même les ressources front-end
// =============================================================================

import {defineConfig} from '../../framework/config.ts';

const config = defineConfig({
    components: {contact: false, booking: false, faq: false, whatsapp: false, testimonials: false},
    header: {actions: []},
    faq: {groups: []},
});

export default config;
