// =============================================================================
// Fichier : config/src/features/editor.ts
// Rôle : définit les règles générales de l’éditeur ComKani
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
    editor: {
        clientContentOnlyPostTypes: [
            'page'
        ],
        clientCodeEditor: false
    },
    pageEditor: {
        enabled: true,
        pages: {}
    }
});

export default config;
