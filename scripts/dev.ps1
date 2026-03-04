# Form Engine - Script de Desenvolvimento
# Uso: .\dev.ps1 [comando]

param([string]$Command = "help")

function Show-Help {
    Write-Host ""
    Write-Host "Form Engine - Comandos Essenciais" -ForegroundColor Cyan
    Write-Host "==================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Containers:" -ForegroundColor Yellow
    Write-Host "  up        Iniciar containers"
    Write-Host "  down      Parar containers"
    Write-Host "  restart   Reiniciar containers"
    Write-Host "  logs      Ver logs (Ctrl+C para sair)"
    Write-Host "  status    Status dos containers e URLs"
    Write-Host "  shell     Acessar shell do container"
    Write-Host ""
    Write-Host "Database:" -ForegroundColor Yellow
    Write-Host "  migrate   Executar migrations"
    Write-Host "  fresh     Resetar database (APAGA TUDO)"
    Write-Host ""
    Write-Host "Development:" -ForegroundColor Yellow
    Write-Host "  cache     Limpar todos os caches"
    Write-Host "  composer  Executar comando Composer"
    Write-Host "            Exemplo: .\dev.ps1 composer require pacote"
    Write-Host "  artisan   Executar comando Artisan"
    Write-Host "            Exemplo: .\dev.ps1 artisan make:model Post"
    Write-Host ""
}

switch ($Command) {
    "up" {
        Write-Host "Iniciando containers..." -ForegroundColor Green
        docker-compose up -d
        Write-Host ""
        Write-Host "Containers iniciados!" -ForegroundColor Green
        Write-Host "Backend: http://localhost:8000" -ForegroundColor Cyan
    }
    
    "down" {
        Write-Host "Parando containers..." -ForegroundColor Yellow
        docker-compose down
        Write-Host "Containers parados!" -ForegroundColor Green
    }
    
    "restart" {
        Write-Host "Reiniciando containers..." -ForegroundColor Yellow
        docker-compose restart
        Write-Host "Containers reiniciados!" -ForegroundColor Green
    }
    
    "logs" {
        Write-Host "Exibindo logs... (Ctrl+C para sair)" -ForegroundColor Yellow
        docker-compose logs -f backend
    }
    
    "status" {
        Write-Host ""
        Write-Host "Status dos Containers:" -ForegroundColor Cyan
        docker-compose ps
        Write-Host ""
        Write-Host "URLs Disponiveis:" -ForegroundColor Cyan
        Write-Host "  Backend:    http://localhost:8000" -ForegroundColor White
        Write-Host "  PostgreSQL: localhost:5432" -ForegroundColor White
        Write-Host "  Redis:      localhost:6379" -ForegroundColor White
        Write-Host ""
    }
    
    "shell" {
        Write-Host "Acessando shell do container..." -ForegroundColor Yellow
        docker-compose exec backend sh
    }
    
    "migrate" {
        Write-Host "Executando migrations..." -ForegroundColor Yellow
        docker-compose exec backend php artisan migrate
    }
    
    "fresh" {
        Write-Host ""
        Write-Host "ATENCAO: Isso vai APAGAR TODOS OS DADOS do banco!" -ForegroundColor Red
        $confirm = Read-Host "Tem certeza? Digite 'sim' para confirmar"
        if ($confirm -eq "sim") {
            Write-Host "Resetando database..." -ForegroundColor Yellow
            docker-compose exec backend php artisan migrate:fresh --seed
            Write-Host "Database resetado!" -ForegroundColor Green
        } else {
            Write-Host "Operacao cancelada." -ForegroundColor Gray
        }
    }
    
    "cache" {
        Write-Host "Limpando caches..." -ForegroundColor Yellow
        docker-compose exec backend php artisan cache:clear
        docker-compose exec backend php artisan config:clear
        docker-compose exec backend php artisan route:clear
        docker-compose exec backend php artisan view:clear
        Write-Host "Caches limpos!" -ForegroundColor Green
    }
    
    "composer" {
        $composerArgs = $args -join " "
        if ($composerArgs) {
            docker-compose exec backend composer $composerArgs
        } else {
            Write-Host "Uso: .\dev.ps1 composer [comando]" -ForegroundColor Yellow
            Write-Host "Exemplo: .\dev.ps1 composer require laravel/sanctum" -ForegroundColor Gray
        }
    }
    
    "artisan" {
        $artisanArgs = $args -join " "
        if ($artisanArgs) {
            docker-compose exec backend php artisan $artisanArgs
        } else {
            Write-Host "Uso: .\dev.ps1 artisan [comando]" -ForegroundColor Yellow
            Write-Host "Exemplo: .\dev.ps1 artisan migrate" -ForegroundColor Gray
        }
    }
    
    "help" {
        Show-Help
    }
    
    default {
        Write-Host "Comando desconhecido: $Command" -ForegroundColor Red
        Show-Help
    }
}
