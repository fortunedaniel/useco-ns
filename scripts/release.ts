// =============================================================================
// Fichier : scripts/release.ts
// Rôle : crée une version livrable du projet ComKani
//
// Objectif :
// - automatiser une opération de développement de façon reproductible
// - conserver cette opération hors du serveur WordPress de production
//
// Ce qu'il fait :
// - valide le numéro de version demandé
// - met à jour la version du thème et du package npm
// - lance la génération du package de production
// - archive la version dans le dossier release
// Ce qu'il ne fait pas :
// - ne rend pas Node.js obligatoire sur le serveur de production
// - ne modifie pas le contenu éditorial WordPress sauf lorsqu’un script de setup le prévoit explicitement
//
// Fichiers liés :
// - voir les imports, appels et chemins référencés directement dans ce fichier
//
// =============================================================================
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

const root = process.cwd();
const themeName = path.basename(root);
const version = process.argv[2] ?? '';

if (!/^\d+\.\d+\.\d+$/.test(version)) {
    console.error('Usage : npm run release -- 1.0.1');
    process.exit(1);
}

const packagePath = path.join(root, 'package.json');
const stylePath = path.join(root, 'style.css');
const releaseDir = path.join(root, 'release');
const versionsDir = path.join(releaseDir, 'versions');
const productionZip = path.join(
    releaseDir,
    `${themeName}-production.zip`
);
const versionZip = path.join(
    versionsDir,
    `${themeName}-v${version}.zip`
);

const packageJson = JSON.parse(
    fs.readFileSync(packagePath, 'utf8')
);

packageJson.version = version;

fs.writeFileSync(
    packagePath,
    `${JSON.stringify(packageJson, null, 2)}\n`,
    'utf8'
);

if (fs.existsSync(stylePath)) {
    let style = fs.readFileSync(stylePath, 'utf8');

    if (/^Version:\s*.*$/m.test(style)) {
        style = style.replace(
            /^Version:\s*.*$/m,
            `Version: ${version}`
        );
    } else if (/^Theme Name:\s*.*$/m.test(style)) {
        style = style.replace(
            /^(Theme Name:\s*.*)$/m,
            `$1\nVersion: ${version}`
        );
    }

    fs.writeFileSync(
        stylePath,
        style,
        'utf8'
    );
}

const result = spawnSync(
    'npm',
    ['run', 'prod'],
    {
        cwd: root,
        stdio: 'inherit',
        shell: false
    }
);

if (result.status !== 0) {
    process.exit(result.status ?? 1);
}

fs.mkdirSync(
    versionsDir,
    {
        recursive: true
    }
);

fs.copyFileSync(
    productionZip,
    versionZip
);

console.log('');
console.log(`Release ComKani créée : v${version}`);
console.log(`Archive : ${versionZip}`);
console.log('Aucun commit Git n’a été créé automatiquement.');
