#!/usr/bin/env node
/**
 * Sync `content/{host}/*.md` edits to a Pushword instance.
 *
 * Served by the instance itself at GET /api/editor/sync.js, so the client
 * always matches the server it talks to. It holds no YAML: the raw file bytes
 * are PUT to /api/content/page/{host}/{slug} (text/markdown), the server
 * parses and re-serializes them, and the response bytes — the canonical file
 * text a fresh export would write — are written straight back to disk.
 *
 * Three invocation modes:
 *
 * 1. Hook mode (no args): reads Claude Code's PostToolUse JSON payload from
 *    stdin and syncs the single file that was just written.
 * 2. Single-file CLI: `sync.js path/to/file.md [more.md …]`.
 * 3. Auto mode: `sync.js --all` — scans `content/**\/*.md` for files modified
 *    since the last snapshot pull (`.claude/.last-pull` marker) and PUTs each.
 *
 * Behaviour per file:
 *   - 200 → local file overwritten with the canonical server text (fresh revision)
 *   - 409 → local file overwritten with the server's current canonical text;
 *           re-apply the edit on the fresh bytes
 *   - 401/403 → bails immediately
 *   - no `revision:` in the front matter → hard failure: the file is not a
 *     real snapshot. In hook mode this exits 2 (blocking) so the message is
 *     surfaced to Claude, with instructions to pull a snapshot and re-apply.
 *
 * Configuration comes from the environment — nothing per-site is baked in:
 *   PUSHWORD_API_BASE    e.g. https://example.com (required)
 *   PUSHWORD_TOKEN_FILE  path to the bearer token (default: <project>/.token)
 *
 * Why Node and not Python or PHP: Claude Code already requires Node, so this
 * runs everywhere Claude Code runs — including Windows marketing PCs where
 * Python and PHP are not installed by default. No external deps.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const CONTENT_RE = /^content\/([^/]+)\/(.+)\.md$/;
const TOOLS = new Set(['Write', 'Edit', 'MultiEdit']);
const MARKER_REL = '.claude/.last-pull';

// Sentinel returned by syncOne() when the file has no `revision:` stamp. It is
// treated as a hard failure everywhere and, in hook mode, mapped to exit code 2
// so the message is fed back to Claude (blocking) rather than silently passing.
const NO_REVISION = 'no-revision';

const projectDir = path.resolve(process.env.CLAUDE_PROJECT_DIR || process.cwd());

async function main() {
    const args = process.argv.slice(2);

    if (args.length === 0) return runHookMode();
    if (args[0] === '--all') return runAutoMode();
    if (args[0] === '-h' || args[0] === '--help') {
        process.stdout.write(extractDocComment());
        return;
    }
    return runCliMode(args);
}

async function runHookMode() {
    let raw;
    try {
        raw = await readStdin();
    } catch {
        process.exit(0);
    }
    let payload;
    try {
        payload = JSON.parse(raw);
    } catch {
        process.exit(0);
    }
    if (!TOOLS.has(payload.tool_name)) process.exit(0);

    const filePathStr = (payload.tool_input || {}).file_path || '';
    if (!filePathStr) process.exit(0);

    const filePath = path.resolve(filePathStr);
    const rel = relativeToProject(filePath);
    if (!rel || !CONTENT_RE.test(rel)) process.exit(0);

    const status = await syncOne(filePath, true);
    // Exit 2 = blocking in PostToolUse: Claude Code feeds stderr back to the
    // model so it acts on it. Surface EVERY failure this way, not just the
    // no-revision case: a 409 conflict refreshes the local file with the
    // server version and DISCARDS the just-made edit — if that exits with a
    // non-2 code the warning is buried and the edit is silently lost. Auth and
    // other errors likewise need to reach the model. Only a clean sync passes.
    if (status === 0 || status === null) process.exit(0);
    process.exit(2);
}

async function runCliMode(paths) {
    let failures = 0;
    for (const p of paths) {
        const fp = path.resolve(p);
        if (!fs.existsSync(fp)) {
            stderr(`✗ ${p}: not found`);
            failures++;
            continue;
        }
        const rel = relativeToProject(fp);
        if (!rel || !CONTENT_RE.test(rel)) {
            stderr(`✗ ${p}: not under content/{host}/*.md`);
            failures++;
            continue;
        }
        const status = await syncOne(fp, true);
        if (status === 401 || status === 403) process.exit(2);
        if (status !== 0 && status !== null) failures++;
    }
    process.exit(failures ? 2 : 0);
}

async function runAutoMode() {
    const marker = path.join(projectDir, MARKER_REL);
    if (!fs.existsSync(marker)) {
        stderr(`✗ no ${MARKER_REL} marker — pull a fresh snapshot first.`);
        process.exit(2);
    }
    const markerMtime = fs.statSync(marker).mtimeMs;

    const contentRoot = path.join(projectDir, 'content');
    if (!fs.existsSync(contentRoot) || !fs.statSync(contentRoot).isDirectory()) {
        stderr(`✗ no ${contentRoot}/ — pull a snapshot first.`);
        process.exit(2);
    }

    const candidates = [];
    for (const md of walkMd(contentRoot)) {
        try {
            if (fs.statSync(md).mtimeMs > markerMtime) candidates.push(md);
        } catch { /* skip */ }
    }

    if (candidates.length === 0) {
        process.stdout.write('Nothing to sync (no .md changed since last snapshot pull).\n');
        process.exit(0);
    }

    stderr(`Syncing ${candidates.length} modified file(s):`);
    let conflicts = 0, failures = 0;
    candidates.sort();
    for (const p of candidates) {
        const status = await syncOne(p, true);
        if (status === 401 || status === 403) process.exit(2);
        if (status === 409) conflicts++;
        else if (status !== 0 && status !== null) failures++;
    }
    stderr('');
    if (failures || conflicts) {
        stderr(`Done: ${candidates.length - failures - conflicts} ok, ${conflicts} conflict(s), ${failures} error(s).`);
        process.exit(2);
    }
    stderr(`Done: ${candidates.length} file(s) synced.`);
}

async function syncOne(filePath, verbose) {
    const rel = relativeToProject(filePath);
    if (!rel) return null;
    const m = rel.match(CONTENT_RE);
    if (!m) return null;
    const [, host, slug] = m;

    let text;
    try {
        text = fs.readFileSync(filePath, 'utf8');
    } catch (e) {
        stderr(`✗ cannot read ${rel}: ${e.message}`);
        return 1;
    }

    const revision = extractRevision(text);
    if (!revision) {
        if (verbose) {
            stderr(
                `✗ ${rel}: no \`revision:\` in front matter — NOT pushed to prod.\n` +
                `  This file is not a real snapshot (a genuine snapshot carries a\n` +
                `  server-managed \`revision:\` stamp used for optimistic locking).\n` +
                `  Fix it: pull a fresh snapshot for ${host}, then re-apply your\n` +
                `  edit so the hook can sync it.\n` +
                `  (Creating a brand-new page? POST the file to\n` +
                `   /api/content/page/${host}/${slug} instead — never invent a revision.)`
            );
        }
        return NO_REVISION;
    }

    const { status, text: responseText } = await apiCall('PUT', `/api/content/page/${host}/${slug}`, revision, text);

    if (status === 200 && responseText) {
        fs.writeFileSync(filePath, responseText, 'utf8');
        if (verbose) stderr(`✓ ${rel} → rev ${String(extractRevision(responseText) || '').slice(0, 8)}…`);
        return 0;
    }

    if (status === 409 && responseText && extractRevision(responseText)) {
        fs.writeFileSync(filePath, responseText, 'utf8');
        if (verbose) {
            stderr(
                `⚠ ${rel}: conflict — file refreshed with server version ` +
                `(rev ${String(extractRevision(responseText) || '').slice(0, 8)}…). ` +
                'Re-apply your changes.'
            );
        }
        return 409;
    }

    if (status === 401 || status === 403) {
        stderr(`✗ auth failed (${status}) — check ${tokenPath()}`);
        return status;
    }

    if (status === 428) {
        stderr(`⚠ ${rel}: missing If-Match — broken sync.`);
        return 428;
    }

    stderr(`✗ ${rel}: unexpected HTTP ${status}: ${truncate(responseText, 300)}`);
    return typeof status === 'number' ? status : 1;
}

// The `revision:` stamp lives in the front matter — the block between the
// file's opening `---` pair. Anchoring the search there keeps a `revision:`
// line inside a body code fence from poisoning If-Match.
function extractRevision(text) {
    const fm = text.match(/^---[ \t]*\r?\n([\s\S]*?)\r?\n---[ \t]*(?:\r?\n|$)/);
    if (!fm) return null;
    const r = fm[1].match(/^revision:[ \t]*([^\s#]+)/m);
    return r ? r[1] : null;
}

// Cloudflare/origin hiccups (502/503/504/520-527) and the origin's own 500s
// come in bursts. With If-Match every PUT is idempotent — a retry that lands
// after the first silently applied just gets a 409, never a double-write — so
// transient failures are safe to retry behind a short backoff.
const MAX_ATTEMPTS = 4;
const RETRY_STATUS = new Set([408, 425, 429, 500, 502, 503, 504, 520, 521, 522, 523, 524, 525, 527]);
const RETRY_BASE_MS = 400;

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function backoffMs(attempt) {
    // 400ms, 800ms, 1600ms … plus up to 250ms jitter to desync concurrent hooks.
    return RETRY_BASE_MS * 2 ** (attempt - 1) + Math.floor(Math.random() * 250);
}

async function apiCall(method, urlPath, revision, bodyText) {
    const base = apiBase();
    const token = readToken();
    const url = `${base}${urlPath}`;

    const headers = {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'text/markdown; charset=UTF-8',
        'Accept': 'text/markdown',
        // Cloudflare bot rule 403s default Node fetch UAs on PUT.
        'User-Agent': 'pushword-editor-sync/2.0',
    };
    // Optimistic locking only applies to an UPDATE. A create (POST) has no
    // prior revision to match, and sending the literal string "undefined" as
    // If-Match is a header the API would have to reject.
    if (revision) headers['If-Match'] = revision;

    for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
        let res;
        try {
            res = await fetch(url, {
                method,
                headers,
                body: bodyText,
                signal: AbortSignal.timeout(15000),
            });
        } catch (e) {
            // Network error / timeout — itself transient: back off and retry.
            if (attempt < MAX_ATTEMPTS) {
                const wait = backoffMs(attempt);
                stderr(`… ${method} ${urlPath}: ${e.message} — retry ${attempt}/${MAX_ATTEMPTS - 1} in ${wait}ms`);
                await sleep(wait);
                continue;
            }
            stderr(`✗ network error after ${MAX_ATTEMPTS} attempts: ${e.message}`);
            process.exit(2);
        }

        const raw = await res.text();

        if (RETRY_STATUS.has(res.status) && attempt < MAX_ATTEMPTS) {
            const wait = backoffMs(attempt);
            stderr(`… ${method} ${urlPath}: HTTP ${res.status} — retry ${attempt}/${MAX_ATTEMPTS - 1} in ${wait}ms`);
            await sleep(wait);
            continue;
        }

        return { status: res.status, text: raw };
    }
}

function apiBase() {
    const base = process.env.PUSHWORD_API_BASE || '';
    if (!base) {
        stderr('✗ PUSHWORD_API_BASE is not set — export the Pushword instance base URL (e.g. https://example.com).');
        process.exit(2);
    }
    return base.replace(/\/$/, '');
}

function tokenPath() {
    if (process.env.PUSHWORD_TOKEN_FILE) return process.env.PUSHWORD_TOKEN_FILE;
    return path.join(projectDir, '.token');
}

function readToken() {
    const p = tokenPath();
    if (!fs.existsSync(p)) {
        stderr(`✗ no token at ${p}. Provision one and store it there, or set PUSHWORD_TOKEN_FILE.`);
        process.exit(2);
    }
    return fs.readFileSync(p, 'utf8').trim();
}

// ----- utils -----

function relativeToProject(absPath) {
    try {
        const rel = path.relative(projectDir, fs.realpathSync(absPath));
        if (rel.startsWith('..')) return null;
        return rel.split(path.sep).join('/');
    } catch {
        return null;
    }
}

function* walkMd(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) yield* walkMd(full);
        else if (entry.isFile() && entry.name.endsWith('.md')) yield full;
    }
}

function readStdin() {
    return new Promise((resolve, reject) => {
        let data = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', chunk => { data += chunk; });
        process.stdin.on('end', () => resolve(data));
        process.stdin.on('error', reject);
        // If nothing is piped (TTY), resolve immediately so we don't hang.
        if (process.stdin.isTTY) resolve('');
    });
}

function stderr(msg) {
    process.stderr.write(msg + '\n');
}

function truncate(s, n) {
    s = s || '';
    return s.length <= n ? s : s.slice(0, n) + '…';
}

function extractDocComment() {
    const src = fs.readFileSync(__filename, 'utf8');
    const m = src.match(/\/\*\*([\s\S]*?)\*\//);
    return m ? m[1].replace(/^\s*\*\s?/gm, '').trim() + '\n' : '';
}

// Only run when invoked directly — other editor scripts may require this
// module to reuse the API client (one token lookup, one retry policy).
if (require.main === module) {
    main().catch(e => { stderr(`✗ ${e.stack || e.message}`); process.exit(2); });
}

module.exports = { apiCall, extractRevision, readToken, stderr };
