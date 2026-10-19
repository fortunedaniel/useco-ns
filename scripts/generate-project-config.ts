// =============================================================================
// Fichier : scripts/generate-project-config.ts
// Rôle : compile la configuration modulaire du projet pour WordPress
//
// Objectif :
// - automatiser une opération de développement de façon reproductible
// - conserver cette opération hors du serveur WordPress de production
//
// Ce qu'il fait :
// - lit les JSON de config/ et config/pages/
// - vérifie leur structure minimale
// - fusionne la configuration sans doublon de responsabilité
// - génère un tableau PHP dans config/generated/project-config.php
// - permet à la préproduction et à la production de fonctionner sans JSON source
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
import {createHash} from 'node:crypto';
import {loadProjectConfig} from './lib/project-config.ts';
import {validateProjectConfig} from './lib/project-config-validation.ts';

const root = process.cwd();
const outputDirectory = path.join(root, 'config', 'generated');
const output = path.join(outputDirectory, 'project-config.php');
const manifestOutput = path.join(outputDirectory, 'manifest.json');
const config = loadProjectConfig(root);
validateProjectConfig(config as unknown as Record<string, unknown>);

const name = String(config.project?.name ?? '').trim();
const slug = String(config.project?.slug ?? '').trim();
if (!name || !slug) {
    throw new Error('project.name et project.slug sont obligatoires.');
}

/**
 * Convertit une valeur texte en chaîne PHP sûre pour le fichier généré.
 *
 * @param value Valeur à sérialiser
 * @returns Chaîne PHP entourée de quotes simples
 */
function phpString(value: unknown): string {
    return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

/**
 * Sérialise récursivement une valeur JavaScript en syntaxe de tableau PHP.
 *
 * @param value Valeur à convertir
 * @param level Niveau d'indentation courant
 * @returns Représentation PHP de la valeur
 */
function phpExport(value: unknown, level = 0): string {
    const indent = '    '.repeat(level);
    const nextIndent = '    '.repeat(level + 1);

    if (value === null) return 'null';
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    if (typeof value === 'number') return Number.isFinite(value) ? String(value) : '0';
    if (typeof value === 'string') return phpString(value);

    if (Array.isArray(value)) {
        if (value.length === 0) return '[]';
        const items = value.map((item) => `${nextIndent}${phpExport(item, level + 1)},`);
        return `[\n${items.join('\n')}\n${indent}]`;
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);
        if (entries.length === 0) return '[]';
        const items = entries.map(([key, item]) => `${nextIndent}${phpString(key)} => ${phpExport(item, level + 1)},`);
        return `[\n${items.join('\n')}\n${indent}]`;
    }

    return 'null';
}

const php = `<?php\n\n// =============================================================================\n// FICHIER GÉNÉRÉ AUTOMATIQUEMENT - NE PAS MODIFIER DIRECTEMENT\n// Source : config/src/**/*.ts\n// Générateur : scripts/generate-project-config.ts\n// Toute modification manuelle sera remplacée par npm run config/generate/qa.\n// =============================================================================\ndefined('ABSPATH') || exit;\n\nreturn ${phpExport(config)};\n`;

fs.mkdirSync(outputDirectory, {recursive: true});
fs.writeFileSync(output, php, 'utf8');

const serializedConfig = JSON.stringify(config);
const manifest = {
    generated: true,
    doNotEdit: 'Fichier généré automatiquement par scripts/generate-project-config.ts',
    generatedAt: new Date().toISOString(),
    source: 'config/src/index.ts',
    generator: 'scripts/generate-project-config.ts',
    sha256: createHash('sha256').update(serializedConfig).digest('hex'),
};

fs.writeFileSync(manifestOutput, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
console.log('Configuration projet : project-config.php + manifest.json générés.');
