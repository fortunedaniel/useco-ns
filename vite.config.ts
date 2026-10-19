// =============================================================================
// Fichier : vite.config.ts
// Rôle : générer uniquement les ressources communes et les composants activés
//
// Ce qu’il fait :
// - construit un point d’entrée virtuel à partir de la configuration du projet
// - inclut les modules TypeScript des composants activés
// - compile les ressources CSS et JavaScript avec Vite
//
// Ce qu’il ne fait pas :
// - n’ajoute pas les composants désactivés à la compilation
// - ne modifie pas la base WordPress
// =============================================================================
import {defineConfig} from 'vite';
import path from 'node:path';
import fs from 'node:fs';
import config from './config/src/index.ts';

const root = process.cwd();
const entryId = 'virtual:comkani-entry';
const resolvedEntryId = '\0' + entryId;
const enabled = (config.components ?? {}) as Record<string, boolean>;
const optionalModules: Record<string, string> = {
    booking: 'components/booking/booking.ts',
    faq: 'components/faq/faq.ts',
    testimonials: 'components/testimonials/testimonials.ts',
};
export default defineConfig({
    plugins: [{
        name: 'comkani-conditional-components',
        resolveId(id) { return id === entryId ? resolvedEntryId : null; },
        load(id) {
            if (id !== resolvedEntryId) return null;
            const imports = ["import '/resources/ts/main.ts';"];
            for (const [name, filename] of Object.entries(optionalModules)) {
                if (enabled[name] && fs.existsSync(path.join(root, filename))) {
                    imports.push(`import '/${filename}';`);
                }
            }
            return imports.join('\n');
        },
    }],
    publicDir: false,
    base: './',
    build: {
        outDir: 'assets/build',
        emptyOutDir: true,
        sourcemap: false,
        rollupOptions: {
            input: entryId,
            output: {
                entryFileNames: 'main.js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames(assetInfo) {
                    return assetInfo.name?.endsWith('.css') ? 'main.css' : 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
});
