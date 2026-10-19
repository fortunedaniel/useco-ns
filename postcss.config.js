// =============================================================================
// Fichier : postcss.config.js
// Rôle : configure le post-traitement du CSS compilé
//
// Ce qu'il fait :
// - active Autoprefixer après la compilation SCSS
// - ajoute les préfixes navigateurs nécessaires au CSS final
// =============================================================================
export default {
    plugins: {
        autoprefixer: {}
    }
};
