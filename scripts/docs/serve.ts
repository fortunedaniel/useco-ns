// =============================================================================
// Fichier : scripts/docs/serve.ts
// Rôle : prévisualisation locale du manuel avec actualisation automatique
//
// Ce qu'il fait :
// - sert docs/index.html sur http://127.0.0.1:4174
// - surveille les fichiers Markdown de docs/
// - régénère le guide lorsqu'un Markdown est enregistré
// - recharge automatiquement les navigateurs connectés après génération réussie
//
// Ce qu'il ne fait pas :
// - ne modifie ni WordPress, ni les bases de données, ni les sites clients
// - ne publie rien sur Internet : écoute exclusivement sur 127.0.0.1
// =============================================================================

import {createServer, type ServerResponse} from 'node:http';
import {watch} from 'node:fs';
import {readFile} from 'node:fs/promises';
import {spawn} from 'node:child_process';
import path from 'node:path';

const root = path.resolve(process.cwd(), 'docs');
const port = 1310;
const clients = new Set<ServerResponse>();
let debounce: ReturnType<typeof setTimeout> | undefined;
let generating = false;
let pending = false;

const liveReloadScript = `<script>
(() => {
  const source = new EventSource('/__comkani_live');
  source.addEventListener('reload', () => window.location.reload());
})();
</script>`;

/** Détermine le type MIME d'un fichier servi par le manuel. */
function contentType(filePath: string): string {
    if (filePath.endsWith('.html')) return 'text/html; charset=utf-8';
    if (filePath.endsWith('.css')) return 'text/css; charset=utf-8';
    if (filePath.endsWith('.js')) return 'text/javascript; charset=utf-8';
    if (filePath.endsWith('.svg')) return 'image/svg+xml';
    if (filePath.endsWith('.png')) return 'image/png';
    if (filePath.endsWith('.jpg') || filePath.endsWith('.jpeg')) return 'image/jpeg';
    if (filePath.endsWith('.webp')) return 'image/webp';
    return 'application/octet-stream';
}

/** Reconstruit le manuel sans lancer deux générations simultanément. */
function regenerate(): void {
    if (generating) {
        pending = true;
        return;
    }

    generating = true;
    console.log('[docs] Modification Markdown : régénération du manuel...');

    const child = spawn('npm', ['run', 'internal:docs-generate'], {
        cwd: process.cwd(),
        stdio: 'inherit',
        shell: process.platform === 'win32',
    });

    child.on('error', (error) => {
        console.error('[docs] Impossible de démarrer la génération :', error);
        finish(false);
    });
    child.on('exit', (code) => finish(code === 0));

    let finished = false;
    function finish(ok: boolean): void {
        if (finished) return;
        finished = true;
        generating = false;
        if (ok) {
            console.log('[docs] Manuel régénéré : navigateur actualisé.');
            for (const client of clients) client.write('event: reload\ndata: updated\n\n');
        } else {
            console.error('[docs] Échec de génération : consulter les erreurs ci-dessus.');
        }
        if (pending) {
            pending = false;
            regenerate();
        }
    }
}

/** Regroupe les événements de sauvegarde rapprochés en une génération. */
function scheduleRegeneration(): void {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(regenerate, 250);
}

const server = createServer(async (request, response) => {
    const pathname = new URL(request.url ?? '/', `http://127.0.0.1:${port}`).pathname;

    if (pathname === '/__comkani_live') {
        response.writeHead(200, {
            'Content-Type': 'text/event-stream; charset=utf-8',
            'Cache-Control': 'no-cache, no-transform',
            Connection: 'keep-alive',
        });
        response.write(': connected\n\n');
        clients.add(response);
        request.on('close', () => clients.delete(response));
        return;
    }

    const relativePath = pathname === '/' ? 'index.html' : decodeURIComponent(pathname.slice(1));
    const filePath = path.resolve(root, relativePath);

    // Refuser toute lecture en dehors du répertoire docs/.
    if (filePath !== root && !filePath.startsWith(`${root}${path.sep}`)) {
        response.writeHead(403);
        response.end('Accès refusé');
        return;
    }

    try {
        let content = await readFile(filePath);
        if (filePath.endsWith('.html')) {
            const html = content.toString('utf8');
            content = Buffer.from(html.includes('</body>')
                ? html.replace('</body>', `${liveReloadScript}</body>`)
                : html + liveReloadScript);
        }
        response.writeHead(200, {'Content-Type': contentType(filePath), 'Cache-Control': 'no-store'});
        response.end(content);
    } catch {
        response.writeHead(404);
        response.end('Document introuvable');
    }
});

const watcher = watch(root, {recursive: true}, (_event, filename) => {
    if (filename && filename.toLowerCase().endsWith('.md')) scheduleRegeneration();
});
watcher.on('error', (error) => console.error('[docs] Erreur de surveillance :', error));

server.listen(port, '127.0.0.1', () => {
    console.log(`Guide ComKani : http://localhost:${port}`);
    console.log('Mode automatique actif : enregistrer un fichier .md pour actualiser le contenu.');
});
