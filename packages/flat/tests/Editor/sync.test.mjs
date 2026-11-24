import assert from 'node:assert/strict';
import {execFile} from 'node:child_process';
import {once} from 'node:events';
import {copyFile, mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import {createServer} from 'node:http';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {test} from 'node:test';
import {promisify} from 'node:util';

test('editor sync keeps local edits when collaboration blocks a save', async (t) => {
  const project = await mkdtemp(join(tmpdir(), 'pw-editor-sync-'));
  t.after(() => rm(project, {recursive: true, force: true}));

  const content = '---\nrevision: local-revision\n---\nLocal edit\n';
  const page = join(project, 'content/example.test/page.md');
  const token = join(project, '.token');
  const script = join(project, 'sync.cjs');
  await mkdir(join(project, 'content/example.test'), {recursive: true});
  await writeFile(page, content);
  await writeFile(token, 'test-token');
  await copyFile(new URL('../../src/Resources/editor/sync.js', import.meta.url), script);

  const requests = [];
  const server = createServer(async (request, response) => {
    request.setEncoding('utf8');
    let body = '';
    for await (const chunk of request) body += chunk;
    requests.push({
      method: request.method,
      url: request.url,
      revision: request.headers['if-match'],
      respectCollaboration: request.headers['x-pushword-respect-collaboration'],
      body,
    });
    response.writeHead(423, {'Content-Type': 'application/json'});
    response.end('{"error":"collaboration_active","id":42}');
  });
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  t.after(() => new Promise((resolve) => server.close(resolve)));

  await assert.rejects(promisify(execFile)(process.execPath, [script, page], {
    env: {
      ...process.env,
      CLAUDE_PROJECT_DIR: project,
      PUSHWORD_API_BASE: `http://127.0.0.1:${server.address().port}`,
      PUSHWORD_TOKEN_FILE: token,
    },
    timeout: 5000,
  }), {code: 2, stderr: /Local edits were kept\. Use the shared draft API/});

  assert.deepEqual(requests, [{
    method: 'PUT',
    url: '/api/content/page/example.test/page',
    revision: 'local-revision',
    respectCollaboration: '1',
    body: content,
  }]);
  assert.equal(await readFile(page, 'utf8'), content);
});
