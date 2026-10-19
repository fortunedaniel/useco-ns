// =============================================================================
// Fichier : scripts/init-project.ts
// Rôle : préparer l'identité d'un projet créé depuis le Starter ComKani
//
// Ce qu'il fait :
// - affiche un aperçu de l'identité demandée par défaut
// - remplace uniquement config/src/project.ts après confirmation explicite
// - conserve une sauvegarde avant toute écriture
//
// Ce qu'il ne fait pas :
// - ne nettoie pas les autres références historiques du thème
// - ne modifie pas WordPress, sa base, wp-config.php ou le SMTP
// - ne déploie ni ne compile le thème
// =============================================================================

import fs from 'node:fs';
import path from 'node:path';
import {loadProjectConfig} from './lib/project-config.ts';

const root = process.cwd();
const file = path.join(root, 'config/src/project.ts');
const args = process.argv.slice(2);
/**
 * Lit la valeur d'un argument nommé transmis au script.
 *
 * @param flag Nom de l'option, par exemple --slug
 * @returns Valeur suivante ou undefined si absente
 */
function value(flag: string): string | undefined {
    const index = args.indexOf(flag);
    return index >= 0 ? args[index + 1] : undefined;
}
/**
 * Interrompt l'initialisation avec un message explicite.
 *
 * @param message Motif de l'arrêt
 * @returns Ne retourne jamais
 */
function abort(message: string): never {
    throw new Error(`Initialisation ComKani : ${message}`);
}
/**
 * Sérialise les données du projet en syntaxe TypeScript conforme aux conventions ComKani.
 *
 * @param value Valeur de configuration à sérialiser
 * @param depth Niveau d'indentation courant
 * @returns Représentation TypeScript avec clés non citées et apostrophes simples
 */
function toTypeScript(value: unknown, depth = 0): string {
    const indent = '    '.repeat(depth);
    const nextIndent = '    '.repeat(depth + 1);
    if (typeof value === 'string') {
        return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '\\r')}'`;
    }
    if (value === null || typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }
    if (Array.isArray(value)) {
        if (value.length === 0) return '[]';
        return `[\n${value.map((item) => `${nextIndent}${toTypeScript(item, depth + 1)}`).join(',\n')},\n${indent}]`;
    }
    if (typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);
        if (entries.length === 0) return '{}';
        return `{\n${entries.map(([key, item]) => {
            const safeKey = /^[A-Za-z_$][A-Za-z0-9_$]*$/.test(key) ? key : toTypeScript(key);
            return `${nextIndent}${safeKey}: ${toTypeScript(item, depth + 1)}`;
        }).join(',\n')},\n${indent}}`;
    }
    throw new Error('Type de configuration non pris en charge');
}

if (!fs.existsSync(file) || !fs.existsSync(path.join(root, 'style.css'))) {
    abort('place-toi a la racine du theme WordPress avant de lancer cette commande.');
}
const name = (value('--name') ?? '').trim();
const slug = (value('--slug') ?? '').trim();
const apply = args.includes('--apply');
if (!name || !slug) {
    console.log('Usage : npm run init -- --name "Nom du projet" --slug "nom-du-projet" [--apply]');
    console.log('Sans --apply : apercu uniquement, aucune modification.');
    process.exit(0);
}
if (!/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/.test(slug)) {
    abort('slug invalide : lettres minuscules, chiffres et tirets uniquement.');
}
if (/starter/i.test(`${name} ${slug}`)) {
    abort('choisis une nouvelle identite de projet, sans nom historique ni "starter".');
}
const current = loadProjectConfig();
const currentName = String(current.project?.name ?? '');
const currentSlug = String(current.project?.slug ?? '');
if (apply && !args.includes('--confirm-reinitialization')) {
    abort('pour ecrire, ajoute --confirm-reinitialization apres --apply. Cette confirmation evite les modifications accidentelles.');
}
const project = {...current.project, name, slug, tagline: ''};
const organization = {
    ...current.organization,
    name,
    legalName: '', description: '', email: '', privacyEmail: '', phone: '',
    address: '', city: '', postalCode: '', country: 'France',
    areaServed: [], sameAs: [], legalForm: '', siren: '', siret: '',
    vatNumber: '', capital: '', registration: '', publicationDirector: '',
    professionalRegistration: {organization: '', region: '', number: ''},
    hosting: {name: '', legalName: '', address: '', postalCode: '', city: '', country: '', phone: '', url: ''},
};
const footer = {...current.footer, socials: []};
const config = {project, organization, designSystem: current.designSystem, footer};
const output = [
    '// =============================================================================',
    '// Fichier : config/src/project.ts',
    '// Rôle : configurer l’identité et les informations générales du projet WordPress',
    '//',
    '// Ce qu’il fait :',
    '// - définit l’identité, les coordonnées et les informations légales du projet',
    '// - référence les tokens et les options du Design System',
    '//',
    '// Ce qu’il ne fait pas :',
    '// - ne modifie pas directement la base WordPress',
    '// - ne contient aucun secret ni mot de passe',
    '// =============================================================================',
    '',
    "import {defineProjectConfig} from '../framework/config.ts';",
    '',
    `const config = defineProjectConfig(${toTypeScript(config)});`,
    '',
    'export default config;',
    '',
].join('\n');
console.log(`Projet actuel : ${currentName} (${currentSlug})`);
console.log(`Nouvelle identite : ${name} (${slug})`);
console.log('Fichier concerne : config/src/project.ts');
console.log('Les coordonnees et mentions juridiques seront videes dans CE fichier.');
console.log('Les autres configurations, textes, composants, contenus WordPress et wp-config.php ne changent pas.');
if (!apply) {
    console.log('APERCU : aucune modification. Pour appliquer, ajouter --apply --confirm-reinitialization.');
    process.exit(0);
}
const backupDir = path.join(root, '.comkani-backups', 'initialization');
fs.mkdirSync(backupDir, {recursive: true});
const backup = path.join(backupDir, `project-${new Date().toISOString().replace(/[:.]/g, '-')}.ts`);
fs.copyFileSync(file, backup, fs.constants.COPYFILE_EXCL);
try {
    fs.writeFileSync(file, output, 'utf8');
} catch (error) {
    fs.copyFileSync(backup, file);
    throw error;
}
console.log(`Configuration actualisee. Sauvegarde : ${path.relative(root, backup)}`);
console.log('IMPORTANT : initialisation partielle. Le theme, les pages, les contenus et les autres configurations peuvent encore contenir des references historiques.');
console.log('Ne pas lancer de packaging client avant nettoyage et validation complets.');
