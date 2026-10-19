// =============================================================================
// Fichier : config/src/features/security.ts
// Rôle : définit les réglages de sécurité du projet
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
    // Personnaliser ces valeurs lors de l'initialisation du projet.
    // Activer uniquement apres verification de la nouvelle URL de connexion.
    // Ce changement n'est PAS un remplacement du pare-feu ni de la double authentification.
    login: {
        enabled: false,
        slug: '',
    },
    security: {
        xmlrpc: false,
        applicationPasswords: true,
        publicUserRestEndpoints: false,
        authorEnumeration: false,
        allowSvgForAgency: true,
        allowZipForAgency: true
    },
    roles: {
        clientManagerCanManageMenus: false,
        editorCanManageFaq: true,
        editorCanManageRequests: false
    },
    admin: {
        menuOrder: [
            'dashboard',
            'comkani',
            'pages',
            'media',
            'requests',
            'faq',
            'users'
        ]
    }
});

export default config;
