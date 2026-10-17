#requires -Version 7.0
[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidateSet('preflight', 'health', 'backup', 'restore-check')][string]$Action,
    [string]$EnvFile = '.env.production',
    [string]$ProjectName = 'form-engine-production',
    [string]$BackupFile
)
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
Push-Location $taskRoot
try {
    function Invoke-Compose {
        param([string[]]$Arguments)
        & docker compose --env-file $EnvFile -p $ProjectName -f docker-compose.production.yml @Arguments
        if ($LASTEXITCODE -ne 0) { throw "Docker Compose falhou (exit $LASTEXITCODE)." }
    }
    switch ($Action) {
        preflight {
            Invoke-Compose @('config', '--quiet')
            Invoke-Compose @('run', '--rm', '--no-deps', 'backend', 'php', 'artisan', 'app:production-check')
        }
        health { Invoke-Compose @('exec', '-T', 'backend', 'php', 'artisan', 'app:health') }
        backup { Invoke-Compose @('--profile', 'ops', 'run', '--rm', '--no-deps', 'backup') }
        restore-check {
            if (-not $BackupFile -or $BackupFile -notmatch '^[a-zA-Z0-9._-]+\.dump$') {
                throw 'Informe somente o nome de um .dump dentro de backups, sem caminho.'
            }
            if (-not (Test-Path -LiteralPath (Join-Path $taskRoot "backups/$BackupFile"))) { throw 'Backup não encontrado.' }
            $taskRestoreProject = 'form-engine-restore-' + [guid]::NewGuid().ToString('N')
            $taskPreviousBackup = $env:BACKUP_FILE
            try {
                $env:BACKUP_FILE = $BackupFile
                & docker compose -p $taskRestoreProject -f docker-compose.restore-test.yml up --abort-on-container-exit --exit-code-from verify
                if ($LASTEXITCODE -ne 0) { throw 'A restauração de verificação falhou.' }
            } finally {
                & docker compose -p $taskRestoreProject -f docker-compose.restore-test.yml down
                $env:BACKUP_FILE = $taskPreviousBackup
            }
        }
    }
} finally { Pop-Location }
