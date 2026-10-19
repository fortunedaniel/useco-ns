/**
 * Fichier : scripts/check-scss.ts
 * Rôle : Vérifier que le point d’entrée SCSS du thème se compile correctement.
 * Ce qu’il fait : Compile le SCSS en mémoire et signale les erreurs de syntaxe et de résolution.
 * Ce qu’il ne fait pas : Modifier les sources, générer un CSS ou appliquer les règles stylistiques de Stylelint.
 */
import {compile} from 'sass';
import {resolve} from 'node:path';

/** Vérifie le point d’entrée SCSS principal sans écrire de fichier. */
function checkScss(): void {
    const file = resolve(process.cwd(), 'resources/scss/style.scss');
    const result = compile(file, {quietDeps: true, loadPaths: [resolve(process.cwd(), 'resources/scss')]});
    if (!result.css.trim()) {
        throw new Error('La compilation SCSS a produit un résultat vide.');
    }
    console.log('Contrôle SCSS ComKani : compilation Sass conforme.');
}

try {
    checkScss();
} catch (error: unknown) {
    console.error('Contrôle SCSS ComKani : échec.', error);
    process.exitCode = 1;
}
