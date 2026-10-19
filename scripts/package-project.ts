// =============================================================================
// Fichier : scripts/package-project.ts
// Rôle : préparer une archive WordPress pour l'environnement demandé
//
// Ce qu'il fait :
// - valide la destination (préproduction ou production)
// - délègue la création du ZIP au moteur mutualisé package-theme.ts
//
// Ce qu'il ne fait pas :
// - ne déploie aucun fichier sur un serveur
// - ne touche pas à la base WordPress
// - n'effectue pas lui-même les contrôles préalables du package.json
// =============================================================================

import {packageTheme, type PackageEnvironment} from './lib/package-theme.ts';

/**
 * Lit et valide l'environnement demandé en argument.
 *
 * @param argumentsList Arguments de la commande Node
 * @returns Environnement de packaging autorisé
 */
function readEnvironment(argumentsList: string[]): PackageEnvironment {
    if (argumentsList.length !== 1 || !['preproduction', 'production'].includes(argumentsList[0])) {
        throw new Error('Usage interne : tsx scripts/package-project.ts preproduction|production');
    }
    return argumentsList[0] as PackageEnvironment;
}

packageTheme(readEnvironment(process.argv.slice(2)));
