/**
 * ComKani Starter - compilateur Vite + rechargement navigateur en Local.
 * Exécution : npm run dev ; zéro dépendance de rechargement supplémentaire.
 * N'intervient jamais dans une installation de production.
 * @file scripts/dev-live-reload.cjs
 */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const port = 35731;
const clients = new Set();
const roots = ['assets/build', 'components', 'pages', 'inc', 'packages/comkani-core/assets', 'packages/comkani-core/includes'];
const rootFiles = ['functions.php', 'header.php', 'footer.php', 'front-page.php', 'home.php', 'index.php', 'single.php', 'page.php', 'style.css'];
const extensions = new Set(['.css', '.js', '.php', '.html']);
const ignored = new Set(['node_modules', '.git', 'release', '.comkani-quarantine', 'docs']);
let lastSnapshot = new Map();
let ready = false;
let debounce = null;
let vite = null;
const server = http.createServer((req, res) => {
  if (req.url !== '/events') { res.writeHead(404); res.end(); return; }
  res.writeHead(200, {
    'Content-Type': 'text/event-stream; charset=utf-8',
    'Cache-Control': 'no-cache, no-transform',
    'Access-Control-Allow-Origin': '*',
    Connection: 'keep-alive',
    'X-Accel-Buffering': 'no'
  });
  res.write('retry: 1000\n\n');
  clients.add(res);
  req.on('close', () => clients.delete(res));
});
/**
 * Gère collect dans le module ComKani.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function collect(dir, snapshot) {
  if (!fs.existsSync(dir)) return;
  for (const item of fs.readdirSync(dir, { withFileTypes: true })) {
    if (ignored.has(item.name)) continue;
    const full = path.join(dir, item.name);
    if (item.isDirectory()) { collect(full, snapshot); continue; }
    if (!item.isFile() || !extensions.has(path.extname(item.name))) continue;
    try { const s = fs.statSync(full); snapshot.set(full, `${s.mtimeMs}:${s.size}`); } catch (_) { /* fichier remplacé entre-temps */ }
  }
}
/**
 * Gère scan dans le module ComKani.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function scan() {
  const next = new Map();
  for (const relative of roots) collect(path.join(root, relative), next);
  for (const relative of rootFiles) {
    const full = path.join(root, relative);
    try { const s = fs.statSync(full); next.set(full, `${s.mtimeMs}:${s.size}`); } catch (_) { /* fichier absent */ }
  }
  if (ready) {
    const changed = [...next].some(([key, value]) => lastSnapshot.get(key) !== value) || [...lastSnapshot.keys()].some(key => !next.has(key));
    if (changed) {
      clearTimeout(debounce);
      debounce = setTimeout(() => {
        for (const res of clients) res.write('event: reload\ndata: updated\n\n');
        process.stdout.write('[ComKani] Fichier modifié : rechargement du navigateur.\n');
      }, 650);
    }
  }
  lastSnapshot = next;
  ready = true;
}
/**
 * Gère shutdown dans le module ComKani.
 *
 * Les paramètres, effets secondaires et droits doivent être vérifiés
 * avec les appels du module lors de sa maintenance.
 */
function shutdown() {
  clearInterval(timer); clearTimeout(debounce);
  for (const res of clients) res.end();
  server.close();
  if (vite && !vite.killed) vite.kill('SIGTERM');
}
server.on('error', (error) => {
  console.error(`[ComKani] Port ${port} indisponible : ${error.message}`);
  process.exitCode = 1;
  shutdown();
});
server.listen(port, '127.0.0.1', () => {
  process.stdout.write(`[ComKani] Live Reload actif sur 127.0.0.1:${port} (WordPress local uniquement).\n`);
});
scan();
const timer = setInterval(scan, 450);
const viteCommand = path.join(root, 'node_modules', 'vite', 'bin', 'vite.js');
vite = spawn(process.execPath, [viteCommand, 'build', '--watch', '--mode', 'development'], { cwd: root, stdio: 'inherit' });
vite.on('exit', code => { process.exitCode = code || 0; shutdown(); });
process.on('SIGINT', () => { shutdown(); process.exit(0); });
process.on('SIGTERM', () => { shutdown(); process.exit(0); });
