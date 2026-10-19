param([Parameter(Mandatory = $true)][string]$NodePath)
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
try {
  $browserProcess = Start-Process -FilePath $NodePath `
    -ArgumentList (Join-Path $PSScriptRoot 'windows-server.mjs') `
    -WorkingDirectory $PSScriptRoot -NoNewWindow -PassThru -Wait `
    -RedirectStandardOutput (Join-Path $PSScriptRoot 'stdout.log') `
    -RedirectStandardError (Join-Path $PSScriptRoot 'stderr.log')
  if ($browserProcess.ExitCode -ne 0) {
    throw ('Browser server exited ' + $browserProcess.ExitCode + ': ' + (Get-Content -Raw (Join-Path $PSScriptRoot 'stderr.log')))
  }
} catch {
  Set-Content -Path (Join-Path $PSScriptRoot 'error.txt') -Value $_.ToString()
  exit 1
}
