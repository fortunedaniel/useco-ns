// =============================================================================
// Fichier : scripts/clean-system.ts
// Rôle : nettoyer les métadonnées système et les copies numérotées accidentelles.
//
// Périmètre : sources du thème de développement ; aucun accès à WordPress ou SQL.
// - Supprime les métadonnées macOS/Windows et les copies identiques à l'original.
// - Met en quarantaine les copies différentes dans un dossier temporaire externe.
// - Ignore Git, node_modules, release et les liens symboliques.
// - Ne supprime jamais un fichier suffixé dont l'original n'existe pas.
//
// Retour arrière : les copies différentes sont récupérables au chemin indiqué.
// Attention : les copies identiques et métadonnées supprimées ne sont pas
// récupérables autrement que par sauvegarde, mais leur original est conservé.
// =============================================================================

import {mkdtemp, readdir, readFile, rename, rm, mkdir} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {basename, join, relative} from 'node:path';

const root = process.cwd();
const ignoredDirectories = new Set(['.git', 'node_modules', 'release']);
const removableDirectories = new Set(['__MACOSX', '.AppleDouble']);
const duplicates = /^(.*?)(?: \(([2-9]\d*)\)| ([2-9]\d*))(\.[^.]+)$/;
let quarantine: string | undefined;
let metadataRemoved = 0;
let identicalRemoved = 0;
let copiesQuarantined = 0;

/** Identifie exclusivement les métadonnées de système d'exploitation. */
function isSystemMetadata(name: string): boolean {
    return name === '.DS_Store' || name === 'Thumbs.db' || name.startsWith('._');
}

/** Retourne le nom d'origine uniquement pour une copie numérotée reconnue. */
function originalName(name: string): string | null {
    const match = duplicates.exec(name);
    return match ? `${match[1]}${match[4]}` : null;
}

/** Parcourt le code source sans suivre les liens symboliques. */
async function cleanDirectory(directory: string): Promise<void> {
    const entries = await readdir(directory, {withFileTypes: true});
    const files = new Set(entries.filter(entry => entry.isFile()).map(entry => entry.name));

    for (const entry of entries) {
        const absolute = join(directory, entry.name);
        if (entry.isSymbolicLink()) continue;

        if (entry.isDirectory()) {
            if (ignoredDirectories.has(entry.name)) continue;
            if (removableDirectories.has(entry.name)) {
                await rm(absolute, {recursive: true, force: true});
                metadataRemoved++;
                continue;
            }
            await cleanDirectory(absolute);
            continue;
        }
        if (!entry.isFile()) continue;

        if (isSystemMetadata(entry.name)) {
            await rm(absolute);
            metadataRemoved++;
            continue;
        }

        const original = originalName(entry.name);
        if (!original || !files.has(original)) continue;

        const originalPath = join(directory, original);
        const [copyData, originalData] = await Promise.all([
            readFile(absolute), readFile(originalPath)
        ]);
        if (copyData.equals(originalData)) {
            await rm(absolute);
            identicalRemoved++;
            continue;
        }

        // Une copie différente pourrait contenir des modifications récentes.
        // La déplacer hors du thème évite de l'embarquer dans les livrables.
        quarantine ??= await mkdtemp(join(tmpdir(), 'comkani-copies-a-verifier-'));
        const destination = join(quarantine, relative(root, absolute));
        await mkdir(join(destination, '..'), {recursive: true});
        await rename(absolute, destination);
        copiesQuarantined++;
    }
}

await cleanDirectory(root);
console.log(`Nettoyage système ComKani : ${metadataRemoved} métadonnée(s), ${identicalRemoved} copie(s) identique(s) supprimée(s), ${copiesQuarantined} copie(s) différente(s) isolée(s).`);
if (quarantine) {
    console.log(`Copies différentes à vérifier : ${quarantine}`);
    console.log('Ce dossier temporaire doit être archivé si tu souhaites conserver ces copies.');
}
