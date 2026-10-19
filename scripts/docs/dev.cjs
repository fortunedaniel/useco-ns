/**
 * @file scripts/docs/dev.cjs
 * @description Serveur local de documentation avec regeneration Markdown et rechargement automatique.
 * @remarks Developpement uniquement. Ne touche pas a WordPress, ni aux ressources de production.
 */
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');

const root = process.cwd();
const docs = path.join(root, 'docs');
const generator = path.join(root, 'scripts', 'docs', 'generate-guide.ts');
const hostname = '127.0.0.1';
const port = Number(process.env.COMKANI_DOCS_PORT || 1310);
const clients = new Set();
let running = false;
let pending = false;
let debounce;

/** Envoie au navigateur l'ordre de recharger la page du manuel. */
function broadcast() {
    for (const response of clients) response.write('data: reload\n\n');
}

/** Regenere le guide avec le generateur officiel et gere les changements simultanes. */
function rebuild() {
    if (running) { pending = true; return; }
    running = true;
    const runner = process.platform === 'win32' ? 'npm.cmd' : 'npm';
    const proc = spawn(runner, ['run', 'internal:docs-generate'], { cwd: root, stdio: 'inherit' });
    proc.on('error', error => {
        console.error('[docs:dev] Impossible de demarrer le generateur :', error.message);
        running = false;
    });
    proc.on('close', code => {
        running = false;
        if (code === 0) {
            console.log('[docs:dev] Manuel actualise.');
            broadcast();
        } else {
            console.error('[docs:dev] Generation en echec. Corrige le Markdown ou le generateur et enregistre de nouveau.');
        }
        if (pending) { pending = false; rebuild(); }
    });
}

/** Sert uniquement le manuel HTML et ses medias locaux. */
function serve(request, response) {
    const pathname = decodeURIComponent(new URL(request.url || '/', 'http://localhost').pathname);
    if (pathname === '/__comkani_docs_events') {
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
    const isHome = pathname === '/' || pathname === '/index.html';
    const allowedMedia = /^\/media\/[a-zA-Z0-9_./-]+\.(?:svg|png|jpe?g|webp|gif)$/.test(pathname) && !pathname.includes('..');
    if (!isHome && !allowedMedia) { response.writeHead(404); response.end('Introuvable'); return; }
    const file = isHome ? path.join(docs, 'index.html') : path.join(docs, pathname.slice(1));
    fs.readFile(file, (error, contents) => {
        if (error) { response.writeHead(404); response.end('Manuel non disponible'); return; }
        if (isHome) {
            const reload = `<script>/* Recharge automatiquement le manuel sans extension. */\nconst source = new EventSource('/__comkani_docs_events');\nsource.onmessage = event => { if (event.data === 'reload') location.reload(); };\n</script>`;
            const html = contents.toString('utf8').replace('</body>', `${reload}</body>`);
            response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' });
            response.end(html);
        } else {
            const ext = path.extname(file).toLowerCase();
            const type = { '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.gif': 'image/gif' }[ext];
            response.writeHead(200, { 'Content-Type': type || 'application/octet-stream' });
            response.end(contents);
        }
    });
}

if (!fs.existsSync(docs) || !fs.existsSync(generator)) {
    console.error('[docs:dev] Lance cette commande depuis la racine du theme comkani-starter.');
    process.exit(1);
}
const server = http.createServer(serve);
server.on('error', error => { console.error('[docs:dev]', error.message); process.exitCode = 1; });
server.listen(port, hostname, () => {
    console.log(`[docs:dev] Ouvre http://${hostname}:${port}/ dans le navigateur.`);
    console.log('[docs:dev] Enregistre un .md avec Cmd + S : le manuel se met a jour automatiquement.');
    rebuild();
});
const watcher = fs.watch(docs, (_event, filename) => {
    if (!filename || !filename.toString().endsWith('.md')) return;
    clearTimeout(debounce);
    debounce = setTimeout(rebuild, 250);
});
process.on('SIGINT', () => { watcher.close(); server.close(); for (const client of clients) client.end(); process.exit(0); });
