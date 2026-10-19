/* Run by the Windows interactive task, with the same Playwright version as the host. */
import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'
import http from 'node:http'
import { execFile } from 'node:child_process'
import { fileURLToPath } from 'node:url'

const directory = path.dirname(fileURLToPath(import.meta.url))

;(async () => {
  const server = await chromium.launchServer({
    channel: 'msedge',
    headless: false,
    host: '127.0.0.1',
    port: 19880,
    args: ['--lang=fr-FR'],
  })
  const native = http.createServer((request, response) => {
    if (request.method !== 'POST' || request.url !== '/french-keyboard') {
      response.writeHead(404).end()
      return
    }
    execFile(
      'powershell.exe',
      [
        '-NoProfile',
        '-ExecutionPolicy',
        'Bypass',
        '-File',
        path.join(directory, 'windows-keyboard.ps1'),
        '-BrowserPid',
        String(server.process().pid),
      ],
      { timeout: 30_000 },
      (error, stdout, stderr) => {
        response
          .writeHead(error ? 500 : 200, { 'Content-Type': 'text/plain' })
          .end(error ? stderr || String(error) : stdout || 'ok')
      },
    )
  })
  await new Promise((resolve, reject) => {
    native.once('error', reject)
    native.listen(19881, '127.0.0.1', resolve)
  })
  fs.writeFileSync(path.join(directory, 'endpoint.txt'), server.wsEndpoint())
  const timer = setInterval(async () => {
    if (!fs.existsSync(path.join(directory, 'stop'))) return
    clearInterval(timer)
    native.close()
    await server.close()
  }, 500)
})().catch((error) => {
  fs.writeFileSync(path.join(directory, 'error.txt'), String(error.stack ?? error))
  process.exitCode = 1
})
