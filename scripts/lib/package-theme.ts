// =============================================================================
// Fichier : scripts/lib/package-theme.ts
// Rôle : construire les archives de préproduction et production ComKani
//
// Ce qu'il fait :
// - copie uniquement les fichiers nécessaires au fonctionnement WordPress
// - exclut les sources Node, TypeScript, SCSS, docs et outils de développement
// - conserve uniquement config/generated dans le package livré
// - ajoute les informations de version et de commit dans comkani-build.json
// - crée une archive ZIP reproductible dans release/
//
// Ce qu'il ne fait pas :
// - ne déploie pas automatiquement le ZIP
// - ne modifie pas WordPress ni la base de données
// - ne contient pas node_modules, config/src ou resources
// - ne remplace pas la recette fonctionnelle WordPress avant packaging
// =============================================================================

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {execFileSync} from 'node:child_process';

export type PackageEnvironment = 'preproduction' | 'production';

const excludedDirectories = new Set([
    '.git',
    '.github',
    '.comkani-backups',
    '.idea',
    '.vscode',
    'node_modules',
    'release',
    'resources',
    'scripts',
    'tests',
    'docs',
    'tokens',
]);

const excludedFiles = new Set([
    '.DS_Store',
    '.gitignore',
    '.editorconfig',
    '.nvmrc',
    'development.json',
    'development.example.json',
    'postcss.config.js',
    'package.json',
    'package-lock.json',
    'npm-debug.log',
    'yarn.lock',
    'pnpm-lock.yaml',
    'README.md',
    'stylelint.config.js',
]);

/**
 * Retourne le commit Git court du projet lorsqu'il est disponible.
 *
 * @param root Racine du thème
 * @returns Hash court du commit ou chaîne vide hors dépôt Git
 */
function gitCommit(root: string): string {
    try {
        return execFileSync('git', ['rev-parse', '--short', 'HEAD'], {
            cwd: root,
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'ignore'],
        }).trim();
    } catch {
        return '';
    }
}

/**
 * Indique si un chemin du thème doit être copié dans l'archive livrable.
 *
 * @param root Racine du thème
 * @param source Chemin absolu évalué par fs.cpSync
 * @returns Vrai si le chemin appartient au package de production
 */
function shouldCopy(root: string, source: string): boolean {
    const relative = path.relative(root, source);

    if (relative === '') {
        return true;
    }

    const parts = relative.split(path.sep);
    const name = path.basename(source);

    if (parts.some((part) => excludedDirectories.has(part))) {
        return false;
    }

    if (parts[0] === 'config') {
        return parts.length === 1 || parts[1] === 'generated';
    }

    if (excludedFiles.has(name)) {
        return false;
    }

    if (/^(?:apply|fix)-.*\.sh$/i.test(name)) {
        return false;
    }

    if (/^(?:vite|eslint|stylelint)\.config\./i.test(name)) {
        return false;
    }

    if (/^tsconfig.*\.json$/i.test(name)) {
        return false;
    }

    if (parts[0] === 'components' && ['.js', '.ts'].includes(path.extname(name))) {
        return false;
    }

    return true;
}

/**
 * Construit un package WordPress installable pour l'environnement demandé.
 *
 * @param environment Environnement cible de l'archive
 * @returns Chemin absolu du ZIP généré
 */
export function packageTheme(environment: PackageEnvironment): string {
    const root = process.cwd();
    const themeName = path.basename(root);
    const releaseDirectory = path.join(root, 'release');
    const zipPath = path.join(releaseDirectory, `${themeName}-${environment}.zip`);
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), `comkani-${environment}-`));
    const stageTheme = path.join(tempRoot, themeName);
    const packageJson = JSON.parse(
        fs.readFileSync(path.join(root, 'package.json'), 'utf8'),
    ) as {version?: string};

    try {
        fs.mkdirSync(releaseDirectory, {recursive: true});
        fs.cpSync(root, stageTheme, {
            recursive: true,
            filter: (source) => shouldCopy(root, source),
        });

        const buildInfo = {
            environment,
            theme: themeName,
            version: packageJson.version ?? '0.0.0',
            commit: gitCommit(root),
            builtAt: new Date().toISOString(),
        };

        fs.writeFileSync(
            path.join(stageTheme, 'comkani-build.json'),
            `${JSON.stringify(buildInfo, null, 2)}\n`,
            'utf8',
        );

        fs.rmSync(zipPath, {force: true});
        execFileSync('zip', ['-qr', zipPath, themeName], {
            cwd: tempRoot,
            stdio: 'inherit',
        });

        const sizeMb = (fs.statSync(zipPath).size / 1024 / 1024).toFixed(2);
        console.log(`Package ComKani ${environment} : conforme.`);
        console.log(`Version officielle : ${buildInfo.version}`);
        console.log(`Build Git : ${buildInfo.commit || 'non disponible'}`);
        console.log(`ZIP : ${zipPath}`);
        console.log(`Taille : ${sizeMb} Mo`);

        return zipPath;
    } finally {
        fs.rmSync(tempRoot, {recursive: true, force: true});
    }
}
