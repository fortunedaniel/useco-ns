// =============================================================================
// Fichier : config/src/features/shared-content.ts
// Rôle : définit les contenus partagés administrables
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
    sharedContent: {
        clients: {
            enabled: false,
            pages: []
        },
        steps: {
            enabled: false,
            pages: [],
            defaults: []
        }
    }
});

export default config;
