// =============================================================================
// Fichier : config/src/features/duplication.ts
// Rôle : configure les types de contenus duplicables par ComKani Core
// Ce qu'il fait : active la duplication des pages et des articles en brouillon.
// Ce qu'il ne fait pas : ne crée aucune copie avant une action autorisée de l'utilisateur.
// =============================================================================
import {defineConfig} from '../../framework/config.ts';

const config = defineConfig({
    duplication: {
        enabled: true,
        postTypes: {page: true, post: true},
    },
});

export default config;
