import { spawn } from 'node:child_process'
import { createRequire } from 'node:module'
import { homedir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const directory = dirname(fileURLToPath(import.meta.url))
const packageDirectory = resolve(directory, '../..')
const require = createRequire(import.meta.url)
const version = require('@playwright/test/package.json').version
const host = process.env.PW_WINDOWS_SSH_HOST ?? 'Administrator@127.0.0.1'
const port = process.env.PW_WINDOWS_SSH_PORT ?? '2223'
const identity =
  process.env.PW_WINDOWS_SSH_KEY ?? join(homedir(), '.local/share/ga-windows11-vm/cle')
const options = [
  '-o',
  'BatchMode=yes',
  '-o',
  'IdentitiesOnly=yes',
  '-o',
  'StrictHostKeyChecking=accept-new',
  '-o',
  'ConnectTimeout=10',
  '-i',
  identity,
]
const runId = `pushword-editor-${Date.now()}`
const remote = `C:\\pushword-editor-tests\\${runId}`
let tunnel
let remotePrepared = false

function run(command, args, options = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { cwd: packageDirectory, ...options })
    let stdout = ''
    let stderr = ''
    child.stdout?.on('data', (value) => {
      stdout += value
    })
    child.stderr?.on('data', (value) => {
      stderr += value
    })
    child.on('error', reject)
    child.on('exit', (code) =>
      code === 0
        ? resolve(stdout.trim())
        : reject(new Error(`${command} exited ${code}: ${stderr || stdout}`)),
    )
  })
}

function powershell(script) {
  const encoded = Buffer.from(
    `$ErrorActionPreference = 'Stop'; $ProgressPreference = 'SilentlyContinue'; ${script}`,
    'utf16le',
  ).toString('base64')
  return run('ssh', [
    ...options,
    '-p',
    port,
    host,
    `powershell -NoProfile -EncodedCommand ${encoded}`,
  ])
}

try {
  console.log('Checking the Windows VM SSH connection and interactive session…')
  const system = await powershell(
    '[Environment]::OSVersion.VersionString; node --version; quser; if ($LASTEXITCODE -ne 0) { throw "An interactive Windows session is required" }',
  )
  console.log(system)
  remotePrepared = true
  await powershell(`New-Item -ItemType Directory -Force '${remote}' | Out-Null
    Set-Location C:\\pushword-editor-tests
    $installed = $null
    if (Test-Path node_modules/@playwright/test/package.json) {
      $installed = (Get-Content -Raw node_modules/@playwright/test/package.json | ConvertFrom-Json).version
    }
    if ($installed -ne '${version}') {
      & npm.cmd install --no-audit --no-fund --ignore-scripts --save-exact '@playwright/test@${version}'
      if ($LASTEXITCODE -ne 0) { throw 'Installing Playwright failed' }
    }`)
  for (const file of [
    'windows-server.mjs',
    'windows-keyboard.ps1',
    'windows-launch.ps1',
  ]) {
    await run('scp', [
      ...options,
      '-P',
      port,
      join(directory, file),
      `${host}:${remote.replaceAll('\\', '/')}/${file}`,
    ])
  }
  await powershell(`$node = (Get-Command node.exe).Source
    $arguments = '-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "${remote}\\windows-launch.ps1" -NodePath "' + $node + '"'
    $action = New-ScheduledTaskAction -Execute (Get-Command powershell.exe).Source -Argument $arguments -WorkingDirectory '${remote}'
    $principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive
    Register-ScheduledTask -TaskName '${runId}' -Action $action -Principal $principal | Out-Null
    Start-ScheduledTask -TaskName '${runId}'`)
  console.log('Starting Microsoft Edge in the Windows desktop session…')
  const endpoint = await powershell(`$limit = (Get-Date).AddSeconds(120)
    while ((Get-Date) -lt $limit) {
      if (Test-Path '${remote}\\error.txt') { throw (Get-Content -Raw '${remote}\\error.txt') }
      if (Test-Path '${remote}\\endpoint.txt') { Get-Content -Raw '${remote}\\endpoint.txt'; exit 0 }
      $task = Get-ScheduledTask -TaskName '${runId}'
      $info = Get-ScheduledTaskInfo -TaskName '${runId}'
      if ($task.State -ne 'Running' -and $info.LastRunTime.Year -gt 2000 -and $info.LastTaskResult -ne 0) {
        throw ('Browser task failed: ' + $info.LastTaskResult)
      }
      Start-Sleep -Milliseconds 500
    }
    throw ('Edge did not start within 120 seconds; task state: ' + $task.State + '; result: ' + $info.LastTaskResult)`)
  // Both sides use localhost: the application stays on Linux, the browser runs on Windows.
  tunnel = spawn(
    'ssh',
    [
      ...options,
      '-p',
      port,
      '-N',
      '-o',
      'ExitOnForwardFailure=yes',
      '-L',
      '127.0.0.1:19880:127.0.0.1:19880',
      '-L',
      '127.0.0.1:19881:127.0.0.1:19881',
      '-R',
      '127.0.0.1:9079:127.0.0.1:9079',
      host,
    ],
    { stdio: ['ignore', 'ignore', 'pipe'] },
  )
  let tunnelError = ''
  tunnel.stderr.on('data', (data) => {
    tunnelError += data
  })
  await new Promise((resolve, reject) => {
    tunnel.once('error', reject)
    tunnel.once('exit', (code) =>
      reject(new Error(`SSH tunnel exited ${code}: ${tunnelError}`)),
    )
    setTimeout(resolve, 1000)
  })
  await run(
    process.execPath,
    [
      require.resolve('@playwright/test/cli'),
      'test',
      '--project=msedge',
      ...process.argv.slice(2),
    ],
    {
      stdio: 'inherit',
      env: {
        ...process.env,
        PW_EDITOR_EDGE_WS_ENDPOINT: endpoint,
        PW_EDITOR_WINDOWS: '1',
        PW_EDITOR_WINDOWS_VERSION: system.split(/\r?\n/)[0],
      },
    },
  )
} catch (error) {
  console.error(error.message)
  process.exitCode = 1
} finally {
  if (remotePrepared) {
    try {
      await powershell(`if (Test-Path '${remote}') {
          New-Item -ItemType File -Force '${remote}\\stop' | Out-Null
        }
        $cleanupFailure = $null
        if (Get-ScheduledTask -TaskName '${runId}' -ErrorAction SilentlyContinue) {
          $limit = (Get-Date).AddSeconds(45)
          while ((Get-ScheduledTask -TaskName '${runId}').State -eq 'Running' -and (Get-Date) -lt $limit) {
            Start-Sleep -Milliseconds 250
          }
          if ((Get-ScheduledTask -TaskName '${runId}').State -eq 'Running') {
            Stop-ScheduledTask -TaskName '${runId}'
            $cleanupFailure = 'The Windows browser did not shut down within 45 seconds'
          } elseif ((Get-ScheduledTaskInfo -TaskName '${runId}').LastTaskResult -ne 0) {
            $cleanupFailure = 'The Windows browser task exited unsuccessfully'
          }
          if (Test-Path '${remote}\\error.txt') {
            $cleanupFailure = Get-Content -Raw '${remote}\\error.txt'
          }
          Unregister-ScheduledTask -TaskName '${runId}' -Confirm:$false
        }
        if (Test-Path '${remote}') { Remove-Item -Recurse -Force '${remote}' }
        if ($cleanupFailure) { throw $cleanupFailure }`)
    } catch (error) {
      console.error(`Windows browser cleanup failed: ${error.message}`)
      process.exitCode = 1
    }
  }
  tunnel?.kill('SIGTERM')
}
