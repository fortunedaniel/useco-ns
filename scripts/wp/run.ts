// =============================================================================
// Fichier : scripts/wp/run.ts
// Rôle : exécuter les opérations WP-CLI ComKani depuis le dossier du thème
//
// Ce qu'il fait :
// - retrouve automatiquement la racine WordPress locale
// - exécute les scripts PHP de maintenance via WP-CLI
// - centralise les modes contrôle et application
// - évite de mémoriser des commandes WP-CLI longues ou des chemins absolus
//
// Ce qu'il ne fait pas :
// - n'installe pas WP-CLI
// - ne contourne pas les protections des scripts PHP
// - n'est pas utilisé en production par WordPress
//
// Fichiers liés :
// - scripts/wp/*.php
// - package.json
// =============================================================================

import {access} from 'node:fs/promises';
import {spawn} from 'node:child_process';
import path from 'node:path';

const root = process.cwd();
const [command = ''] = process.argv.slice(2);

interface CommandDefinition {
    readonly file: string;
    readonly env?: Readonly<Record<string, string>>;
}

const commands: Readonly<Record<string, CommandDefinition>> = {
    setup: {
        file: 'scripts/wp-setup.php',
    },
    'setup:adopt': {
        file: 'scripts/wp-setup.php',
        env: {COMKANI_SYNC_ADOPT: '1'},
    },
    'setup:prune': {
        file: 'scripts/wp-setup.php',
        env: {COMKANI_SYNC_PRUNE: '1'},
    },
    'db:check': {
        file: 'scripts/wp/database-cleanup.php',
    },
    'db:clean': {
        file: 'scripts/wp/database-cleanup.php',
        env: {COMKANI_APPLY: '1'},
    },
    'themes:check': {
        file: 'scripts/wp/theme-cleanup.php',
    },
    'themes:clean': {
        file: 'scripts/wp/theme-cleanup.php',
        env: {COMKANI_APPLY: '1'},
    },
    cache: {
        file: 'scripts/wp/cache-clear.php',
    },
};

/**
 * Vérifie l’existence d’un fichier sans lever d’erreur si le chemin est absent.
 *
 * @param target Chemin absolu à contrôler
 * @returns Vrai si le fichier ou dossier existe
 */
async function exists(target: string): Promise<boolean> {
    try {
        await access(target);
        return true;
    } catch {
        return false;
    }
}

/**
 * Recherche la racine WordPress en remontant depuis le thème courant.
 *
 * @param start Dossier de départ
 * @returns Racine WordPress contenant wp-load.php
 */
async function findWordPressRoot(start: string): Promise<string> {
    let current = path.resolve(start);

    for (let level = 0; level < 8; level += 1) {
        if (await exists(path.join(current, 'wp-load.php'))) {
            return current;
        }

        const parent = path.dirname(current);
        if (parent === current) {
            break;
        }
        current = parent;
    }

    throw new Error('Racine WordPress introuvable. Lance la commande depuis un thème installé dans Local.');
}

/**
 * Exécute un fichier PHP avec WP-CLI dans le bon WordPress local.
 *
 * @param definition Script et variables d’environnement à utiliser
 * @returns Promesse résolue si WP-CLI termine sans erreur
 */
async function runWordPressCommand(definition: CommandDefinition): Promise<void> {
    const wordpressRoot = await findWordPressRoot(root);
    const script = path.join(root, definition.file);

    const child = spawn('wp', ['eval-file', script, `--path=${wordpressRoot}`], {
        stdio: 'inherit',
        env: {
            ...process.env,
            ...definition.env,
        },
    });

    await new Promise<void>((resolve, reject) => {
        child.once('error', () => reject(new Error(
            'WP-CLI est indisponible. Lance la commande depuis le Local Site Shell ou vérifie ton PATH.',
        )));
        child.once('exit', (code) => {
            if (code === 0) {
                resolve();
                return;
            }
            reject(new Error(`WP-CLI a terminé avec le code ${code ?? 'inconnu'}.`));
        });
    });
}

const definition = commands[command];
if (!definition) {
    throw new Error(
        'Commande inconnue. Valeurs : setup, setup:adopt, setup:prune, db:check, db:clean, themes:check, themes:clean, cache.',
    );
}

await runWordPressCommand(definition);
