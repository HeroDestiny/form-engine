# Scripts de Desenvolvimento

Este diretório contém scripts utilitários para facilitar o desenvolvimento.

Para operação em produção (PowerShell 7), use `ops.ps1 -Action preflight|health|backup|restore-check`. O ensaio `validate-production.ps1` constrói e valida uma stack descartável, testa backup/restauração e indisponibilidade/recuperação do Redis e remove apenas os recursos que criou. Consulte [operação em produção](../docs/operacao-producao.md) para configuração e comandos completos.

## dev.ps1

Script principal para gerenciar o ambiente de desenvolvimento.

### Uso

```powershell
# A partir da raiz do projeto
.\scripts\dev.ps1 [comando]
```

### Comandos Disponíveis

**Containers:**
- `up` - Iniciar containers
- `down` - Parar containers
- `restart` - Reiniciar containers
- `logs` - Ver logs em tempo real
- `status` - Status dos containers e URLs

**Database:**
- `migrate` - Executar migrations
- `fresh` - Resetar database (APAGA TUDO)

**Development:**
- `cache` - Limpar todos os caches
- `composer [args]` - Executar comando Composer
- `artisan [args]` - Executar comando Artisan
- `shell` - Acessar shell do container

### Exemplos

```powershell
# Iniciar ambiente
.\scripts\dev.ps1 up

# Instalar dependência
.\scripts\dev.ps1 composer require laravel/sanctum

# Criar model
.\scripts\dev.ps1 artisan make:model Post -m

# Ver logs
.\scripts\dev.ps1 logs
```
