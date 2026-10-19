// =============================================================================
// Fichier : scripts/lib/project-config.ts
// Rôle : charger la configuration TypeScript d'un projet ComKani
//
// Ce qu'il fait :
// - charge la source de vérité située dans config/src
// - fournit une configuration unique aux scripts de développement
// - évite les doublons entre plusieurs fichiers JSON fonctionnels
//
// Ce qu'il ne fait pas :
// - ne modifie aucun fichier
// - ne lit pas la base de données WordPress
// - n'est pas requis sur le serveur de production
// =============================================================================

import config from '../../config/src/index.ts';

/**
 * Retourne la configuration complète du projet.
 *
 * Le paramètre root est conservé pour maintenir une API stable entre les
 * scripts du Starter, même si la configuration TypeScript est importée.
 *
 * @param root Racine du thème WordPress
 * @returns Configuration complète du projet WordPress
 */
export function loadProjectConfig(root: string = process.cwd()) {
    void root;
    return structuredClone(config);
}
