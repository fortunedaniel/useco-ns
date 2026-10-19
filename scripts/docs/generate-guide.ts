/**
 * @file scripts/docs/generate-guide.ts
 * @description Génère un manuel HTML unique depuis les sources Markdown docs/*.md.
 * @remarks docs/index.html est un résultat généré, jamais une source éditoriale.
 * Le guide est organisé par parcours de travail ; les documents non classés sont conservés en archives.
 * Aucun document Markdown n'est supprimé ou ignoré silencieusement.
 */
import {mkdir, readdir, readFile, writeFile} from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const docsDir = path.join(root, 'docs');
const output = path.join(docsDir, 'index.html');

/** Échappe les caractères spéciaux pour sécuriser le HTML généré. */
function escapeHtml(value: string): string {
    return value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
}

/** Normalise les tirets longs dans l'affichage sans modifier les sources Markdown. */
function displayText(value: string): string {
    return value.replaceAll(' — ', ' : ').replaceAll('—', ':');
}

/** Rend les emphases et le code en ligne après échappement HTML. */
function inline(value: string): string {
    return escapeHtml(displayText(value))
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
}

/** Convertit le Markdown interne en HTML sans dépendance externe. */
function markdownToHtml(text: string): string {
    const rows = text.split(/\r?\n/);
    const result: string[] = [];
    let list: 'ul' | 'ol' | null = null;
    let inCode = false;
    let inTable = false;
    let firstTableRow = false;
    const closeList = (): void => { if (list) { result.push(`</${list}>`); list = null; } };
    const closeTable = (): void => { if (inTable) { result.push('</tbody></table>'); inTable = false; } };
    for (const raw of rows) {
        const line = raw.trim();
        if (line.startsWith('```')) {
            closeList(); closeTable();
            inCode = !inCode;
            result.push(inCode ? '<pre><code>' : '</code></pre>');
            continue;
        }
        if (inCode) {result.push(escapeHtml(raw) + '\n'); continue;}
        if (!line) {closeList(); closeTable(); continue;}
        const image = /^!\[([^\]]*)\]\((media\/[\w./-]+\.(?:svg|png|jpg|jpeg|webp))\)$/.exec(line);
        if (image && !image[2].includes('..')) {
            closeList(); closeTable();
            result.push(`<figure><img src="${escapeHtml(image[2])}" alt="${escapeHtml(image[1])}" loading="lazy"><figcaption>${escapeHtml(image[1])}</figcaption></figure>`);
            continue;
        }
        if (line.startsWith('|') && line.endsWith('|')) {
            closeList();
            if (/^\|[\s|:-]+\|$/.test(line)) continue;
            if (!inTable) { result.push('<table><tbody>'); inTable = true; firstTableRow = true; }
            const tag = firstTableRow ? 'th' : 'td';
            result.push('<tr>' + line.slice(1, -1).split('|').map(cell => `<${tag}>${inline(cell.trim())}</${tag}>`).join('') + '</tr>');
            firstTableRow = false;
            continue;
        }
        closeTable();
        const heading = /^(#{1,6})\s+(.+)$/.exec(line);
        if (heading) {closeList(); const level = Math.min(heading[1].length + 1, 6); result.push(`<h${level}>${inline(heading[2])}</h${level}>`); continue;}
        const unordered = /^[-*]\s+(.+)$/.exec(line);
        const ordered = /^\d+\.\s+(.+)$/.exec(line);
        if (unordered || ordered) {
            const kind = unordered ? 'ul' : 'ol';
            if (list !== kind) {closeList(); list = kind; result.push(`<${kind}>`);}
            result.push(`<li>${inline((unordered || ordered)![1])}</li>`);
            continue;
        }
        closeList();
        if (line.startsWith('> ')) {result.push(`<blockquote>${inline(line.slice(2))}</blockquote>`); continue;}
        if (/^---+$/.test(line)) {result.push('<hr>'); continue;}
        result.push(`<p>${inline(line)}</p>`);
    }
    closeList(); closeTable();
    if (inCode) result.push('</code></pre>');
    return result.join('\n');
}

/** Lit tous les chapitres et reconstruit le sommaire sans répétition. */
async function generate(): Promise<void> {
    const names = (await readdir(docsDir)).filter(name => /^\d\d-.*\.md$/.test(name)).sort((a,b) => a.localeCompare(b, 'fr'));
    if (!names.includes('00-preambule.md')) throw new Error('Préambule manquant : docs/00-preambule.md');
    const chapters: {file: string; title: string; id: string; content: string; number: number}[] = [];
    for (const file of names) {
        const source = await readFile(path.join(docsDir, file), 'utf8');
        const title = (source.match(/^#\s+(.+)$/m)?.[1]?.trim() ?? file.slice(0,-3)).replace(/^\d{2}\.\s*/, '');
        const number = Number(file.slice(0,2));
        chapters.push({file, title: displayText(title), id: `doc-${file.slice(0,-3)}`, content: markdownToHtml(source), number});
    }
    const link = (item: typeof chapters[number]): string => `<a href="#${escapeHtml(item.id)}">${escapeHtml(item.title)}</a>`;
    const groups: {title: string; filenames: string[]}[] = [
        {title: 'Commencer', filenames: ['00-preambule.md', '01-comprendre-le-starter.md', '02-architecture.md', '03-prise-en-main.md', '22-demarrage-multipage.md']},
        {title: 'Configurer', filenames: ['04-configuration.md', '05-commandes.md', '06-initialisation.md']},
        {title: 'Construire', filenames: ['07-design-system.md', '08-typescript-et-javascript.md', '09-pages-cpt-templates.md', '10-composants.md']},
        {title: 'Administrer', filenames: ['11-comkani-core.md', '12-administration-core.md']},
        {title: 'Contrôler', filenames: ['13-qualite-tests-depannage.md', '14-qualite-documentation.md']},
        {title: 'Livrer', filenames: ['15-preproduction-production.md', '16-parcours-livraison.md']},
        {title: 'Faire évoluer', filenames: ['17-git-et-maintenance.md', '18-contribuer-au-guide.md']},
    ];
    const visibleNames = new Set(groups.flatMap(group => group.filenames));
    const navGroups = groups.map(group => {
        const items = group.filenames.map(file => chapters.find(chapter => chapter.file === file)).filter((item): item is typeof chapters[number] => Boolean(item));
        return `<div class="group">${escapeHtml(group.title)}</div>${items.map(link).join('')}`;
    }).join('');
    const reference = chapters.filter(item => !visibleNames.has(item.file));
    const nav = `${navGroups}<details><summary>Archives et références (${reference.length})</summary>${reference.map(link).join('')}</details>`;
    const orderedChapters = [...groups.flatMap(group => group.filenames.map(file => chapters.find(item => item.file === file)).filter((item): item is typeof chapters[number] => Boolean(item))), ...reference];
    const sections = orderedChapters.map(item => `<section class="markdown-chapter" id="${escapeHtml(item.id)}">${item.content}</section>`).join('\n');
    const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Guide d'utilisation du Starter WordPress</title><style>:root{--navy:#000a3d;--blue:#0022b8;--periwinkle:#5c7aff;--lavender:#ebeeff;--white:#f5f5f5;--orange:#ff7d00;--ink:#171a2d;--muted:#656a80;--line:#dfe2ef;--green:#16845b;font-family:Inter,Arial,sans-serif;color:var(--ink);background:#f7f8fc}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0}.shell{display:grid;grid-template-columns:310px minmax(0,1fr);min-height:100vh}.side{position:sticky;top:0;height:100vh;overflow:auto;padding:26px 20px;background:var(--navy);color:white}.brand{display:flex;align-items:center;gap:12px;margin-bottom:24px}.mark{display:grid;width:42px;height:42px;border-radius:50%;place-items:center;background:var(--periwinkle);font-weight:900}.brand small{display:block;color:#cbd2ff}.side a{display:block;padding:8px 11px;border-radius:4px;color:#eef0ff;text-decoration:none;font-size:14px}.side a:hover{background:#17296f}.side a.is-active{background:#273c90;color:#fff;;font-weight:700}.side .group{margin:18px 10px 7px;color:#8999e8;font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}main{width:min(1220px,100%);padding:52px clamp(28px,5vw,76px) 120px}.hero{padding:42px;border-radius:20px;background:linear-gradient(135deg,var(--navy),var(--blue));color:white}.eyebrow{color:#cbd2ff;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}h1{max-width:930px;margin:12px 0;font-size:clamp(40px,5vw,70px);line-height:.95}h2{margin:0 0 22px;color:var(--navy);font-size:34px}h3{margin:32px 0 12px;color:var(--blue)}p,li,dd{line-height:1.65}section{margin-top:78px;scroll-margin-top:28px}.lead{max-width:880px;font-size:19px}.note,.warning,.success{margin:20px 0;padding:18px 20px;border-radius:10px}.note{border-left:4px solid var(--periwinkle);background:var(--lavender)}.warning{border-left:4px solid var(--orange);background:#fff5e9}.success{border-left:4px solid var(--green);background:#eaf8f2}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(245px,1fr));gap:14px}.card,.procedure{padding:20px;border:1px solid var(--line);border-radius:12px;background:white}.card strong{color:var(--navy)}code{padding:2px 6px;border-radius:5px;background:#e9ebf5;color:var(--navy)}pre{overflow:auto;padding:18px;border-radius:10px;background:var(--navy);color:white}pre code{padding:0;background:none;color:inherit}table{width:100%;border-collapse:collapse;background:white}th,td{padding:11px 13px;border:1px solid var(--line);text-align:left;vertical-align:top}th{background:var(--lavender);color:var(--navy)}.procedure{margin:18px 0;padding:24px}.procedure h3{margin-top:0}.procedure dl{display:grid;grid-template-columns:220px 1fr;gap:0;border:1px solid var(--line);border-radius:9px;overflow:hidden}.procedure dt,.procedure dd{margin:0;padding:11px 13px;border-bottom:1px solid var(--line)}.procedure dt{background:#f2f3f9;font-weight:800;color:var(--navy)}.run{margin-top:16px}.run span{font-weight:800}.steps li{margin:9px 0}.flow{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.flow span{padding:9px 12px;border:1px solid var(--line);border-radius:8px;background:white}.flow b{color:var(--periwinkle)}.footer{margin-top:90px;padding-top:24px;border-top:1px solid var(--line);color:var(--muted)}@media(max-width:900px){.shell{grid-template-columns:1fr}.side{position:static;height:auto}.side nav{columns:2}main{padding-top:30px}.procedure dl{grid-template-columns:1fr}.procedure dt{border-bottom:0}}
.side details{margin-top:12px}.side summary{cursor:pointer;margin:14px 10px 8px;color:#8999e8;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.side details a{padding-left:18px}.markdown-chapter{margin-top:64px}.markdown-chapter h2{font-size:30px}.markdown-chapter h3{font-size:22px}.markdown-chapter p{max-width:90ch}.side a{overflow-wrap:anywhere}.side .group{font-size:11px}.side{scrollbar-width:thin}nav{padding-bottom:25px}</style></head><body><div class="shell"><aside class="side"><div class="brand"><div><strong>Agence ComKani</strong><small>Guide d'utilsation</small></div></div><nav>${nav}</nav></aside><main>${sections}<div class="footer">Sources éditoriales : docs/*.md. Fichier HTML généré automatiquement, ne pas modifier directement.</div></main></div><script>(()=>{const links=[...document.querySelectorAll('.side a[href^="#doc-"]')];const sections=links.map(a=>({a,el:document.getElementById(a.getAttribute('href').slice(1))})).filter(x=>x.el);function update(){let current=sections[0];for(const item of sections){if(item.el.getBoundingClientRect().top<=window.innerHeight*0.28)current=item;}for(const item of sections){const active=item===current;item.a.classList.toggle('is-active',active);if(active)item.a.setAttribute('aria-current','location');else item.a.removeAttribute('aria-current');}}window.addEventListener('scroll',update,{passive:true});window.addEventListener('hashchange',update);update();})();</script></body></html>`;
    await mkdir(docsDir,{recursive:true});
    await writeFile(output, html, 'utf8');
    console.log(`Manuel ComKani généré : ${chapters.length} chapitres Markdown, un seul sommaire.`);
}

await generate();
