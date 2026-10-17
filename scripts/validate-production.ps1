#requires -Version 7.0
[CmdletBinding()]
param([switch]$SkipBuild, [int]$HttpPort = 18080)
$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskId = [guid]::NewGuid().ToString('N')
$taskProject = 'form-engine-validation-' + $taskId
$taskEnvFile = Join-Path $taskRoot ('.env.production.validation-' + $taskId)
$taskBackup = $null
function Invoke-ValidationCompose {
    param([string[]]$Arguments)
    & docker compose --env-file $taskEnvFile -p $taskProject -f docker-compose.production.yml @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose falhou (exit $LASTEXITCODE)." }
}
Push-Location $taskRoot
try {
    $taskKey = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    $taskSecrets = 1..3 | ForEach-Object { [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24)) }
    $taskEnv = @('APP_URL=https://validation.example.test', "APP_KEY=base64:$taskKey", 'DB_DATABASE=form_engine_validation',
        'DB_USERNAME=form_engine_app', "DB_PASSWORD=$($taskSecrets[0])", "POSTGRES_ADMIN_PASSWORD=$($taskSecrets[1])",
        "REDIS_PASSWORD=$($taskSecrets[2])", 'RELEASE_TAG=validation', "HTTP_PORT=$HttpPort") -join "`n"
    [IO.File]::WriteAllText($taskEnvFile, $taskEnv + "`n", [Text.UTF8Encoding]::new($false))
    Invoke-ValidationCompose @('config', '--quiet')
    if (-not $SkipBuild) { Invoke-ValidationCompose @('build') }
    Invoke-ValidationCompose @('up', '-d', '--wait', '--wait-timeout', '90', 'postgres', 'redis')
    Invoke-ValidationCompose @('run', '--rm', '--no-deps', 'backend', 'php', 'artisan', 'app:production-check')
    Invoke-ValidationCompose @('run', '--rm', '--no-deps', 'backend', 'php', 'artisan', 'migrate', '--force')
    Invoke-ValidationCompose @('up', '-d', '--wait', '--wait-timeout', '90', 'backend', 'nginx')
    $taskUrl = "http://127.0.0.1:$HttpPort"
    foreach ($path in @('/', '/login', '/up', '/ready')) {
        $response = Invoke-WebRequest "$taskUrl$path" -TimeoutSec 10
        if ($response.StatusCode -ne 200) { throw "Falha HTTP em $path" }
    }
    $response = Invoke-WebRequest "$taskUrl/api/forms" -SkipHttpErrorCheck -TimeoutSec 10
    if ($response.StatusCode -ne 401 -or ($response.Content | ConvertFrom-Json).success -ne $false) { throw 'Rota protegida não recusou acesso anônimo.' }
    # Simulate the host TLS proxy: distinct forwarded clients must not share the Nginx IP budget.
    foreach ($client in 1..6) {
        $taskLoginBody = @{ email = "proxy-validation-$client@example.test"; password = 'invalid' } | ConvertTo-Json
        $response = Invoke-WebRequest "$taskUrl/api/auth/login" -Method Post -ContentType 'application/json' -Body $taskLoginBody -Headers @{
            'X-Forwarded-For' = "198.51.100.$client"; 'X-Forwarded-Proto' = 'https'
        } -SkipHttpErrorCheck -TimeoutSec 10
        if ($response.StatusCode -ne 401) { throw "Clientes distintos compartilharam o limite do proxy: HTTP $($response.StatusCode)" }
    }
    $taskSql = "INSERT INTO tenants (name, slug, is_active, created_at, updated_at) VALUES ('Restore verification', 'restore-verification', true, NOW(), NOW()); INSERT INTO audit_logs (tenant_id, action, entity_type, entity_id, created_at, updated_at) SELECT id, 'RESTORE_PROBE', 'tenant', id, NOW(), NOW() FROM tenants WHERE slug='restore-verification';"
    Invoke-ValidationCompose @('exec', '-T', 'postgres', 'psql', '-U', 'form_engine_app', '-d', 'form_engine_validation', '-v', 'ON_ERROR_STOP=1', '-c', $taskSql)
    $taskBackupOutput = Invoke-ValidationCompose @('--profile', 'ops', 'run', '--rm', '--no-deps', 'backup') | Out-String
    if ($taskBackupOutput -notmatch '(form-engine-[a-zA-Z0-9_-]+\.dump)') { throw 'Nome do backup ausente na resposta.' }
    $taskBackup = $Matches[1]
    & "$PSScriptRoot/ops.ps1" -Action restore-check -BackupFile $taskBackup
    # Verify readiness actually detects a dependency outage, then recover.
    Invoke-ValidationCompose @('pause', 'redis')
    $response = Invoke-WebRequest "$taskUrl/ready" -SkipHttpErrorCheck -TimeoutSec 20
    if ($response.StatusCode -ne 503 -or ($response.Content | ConvertFrom-Json).ready -ne $false) {
        throw "Readiness não detectou a falha do Redis: HTTP $($response.StatusCode)"
    }
    Invoke-ValidationCompose @('unpause', 'redis')
    $taskRecoveryDeadline = [DateTime]::UtcNow.AddSeconds(30)
    do {
        $response = Invoke-WebRequest "$taskUrl/ready" -SkipHttpErrorCheck -TimeoutSec 10
        if ($response.StatusCode -eq 200) { break }
        Start-Sleep -Milliseconds 500
    } while ([DateTime]::UtcNow -lt $taskRecoveryDeadline)
    if ($response.StatusCode -ne 200) { throw 'Readiness não recuperou após retorno do Redis.' }
    Write-Output 'Validação de produção, backup, restauração e recuperação de dependência concluída.'
} finally {
    # This script owns only this generated project, env file and synthetic backup.
    if ($taskProject -notmatch '^form-engine-validation-[a-f0-9]{32}$') { throw 'Projeto de limpeza inválido.' }
    & docker compose --env-file $taskEnvFile -p $taskProject -f docker-compose.production.yml down --volumes
    if (Test-Path -LiteralPath $taskEnvFile) { Remove-Item -LiteralPath $taskEnvFile }
    if ($taskBackup -and $taskBackup -match '^form-engine-[a-zA-Z0-9_-]+\.dump$') {
        foreach ($suffix in @('', '.sha256')) {
            $taskBackupPath = [IO.Path]::GetFullPath((Join-Path $taskRoot "backups/$taskBackup$suffix"))
            $taskExpectedParent = [IO.Path]::GetFullPath((Join-Path $taskRoot 'backups'))
            if ([IO.Path]::GetDirectoryName($taskBackupPath) -ne $taskExpectedParent) { throw 'Caminho de limpeza inválido.' }
            if (Test-Path -LiteralPath $taskBackupPath) { Remove-Item -LiteralPath $taskBackupPath }
        }
    }
    Pop-Location
}
