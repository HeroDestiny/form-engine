# Testes e qualidade

## Backend

Instale as dependências conforme o [README](../README.md). A partir de `backend`, execute a suíte SQLite em memória e a verificação de estilo:

```powershell
php vendor/bin/phpunit --colors=never
php vendor/bin/pint --test
```

Também é possível executar `docker compose exec -T backend php artisan test --compact` a partir da raiz, com o ambiente local iniciado.

Para PostgreSQL, construa a imagem de desenvolvimento e use o projeto descartável, a partir da raiz:

```powershell
docker compose build backend
docker compose -p form-engine-tests -f docker-compose.test.yml up --abort-on-container-exit --exit-code-from backend-tests
docker compose -p form-engine-tests -f docker-compose.test.yml down
```

O código/dependências de `backend` são montados no runner. O banco de teste usa `tmpfs`, sem porta publicada nem compartilhamento de dados com o ambiente local. Os testes recriam tabelas: não use o banco de desenvolvimento nem execute duas suítes simultâneas no mesmo banco descartável.

`Tests\TestCase` exige ambiente `testing` e SQLite `:memory:` sem URL de conexão, ou o perfil explícito `isolated-postgres`: banco/usuário `form_engine_testing`, host `postgres-test` ou `127.0.0.1`, sem URL de conexão. O CI define `TEST_PG_HOST=127.0.0.1`; o Compose usa `postgres-test`.

Para configuração PostgreSQL, use PHPUnit diretamente com `--configuration=phpunit.postgres.xml`. Não passe esse parâmetro pelo comando Artisan, que acrescenta configuração duplicada nesta versão.

## Cobertura funcional

A suíte verifica autenticação, expiração e rate limit; permissões das 30 operações protegidas com quatro papéis; isolamento entre tenants e proprietários; formulários/campos/versionamento; submissões e rascunhos; validação de filtros, calendário e limites; representação dos detalhes; CSV; auditoria, rollback e imutabilidade; e saúde operacional.

`ComprehensiveAcceptanceRegressionTest` reúne cenários de entradas, contratos e atomicidade. `ConcurrencyRegressionTest` executa sete disputas entre dois processos reais no PostgreSQL: criação duplicada de tenant, usuário, formulário e campo; criação de versão; publicação; finalização de rascunho. Esses sete casos e um teste de bloqueio são ignorados no SQLite.

Ao alterar uma regra, inclua uma regressão que verifique o comportamento relevante: resposta, persistência, autorização/isolamento e atomicidade quando aplicáveis. Atualize a matriz de permissões ao adicionar rotas.

## Frontend e operação

O CI valida o build do frontend; ainda não há suíte automatizada do frontend. Em `frontend`:

```powershell
npm ci
npm run build
```

O ensaio `./scripts/validate-production.ps1`, na raiz e com PowerShell 7, reconstrói as imagens e verifica migrations, preflight, HTTP/SPA, proxy/rate limit, backup/restauração, auditoria e indisponibilidade/recuperação do Redis. Ele cria e remove apenas seus recursos descartáveis. Pré-requisitos e parâmetros estão em [operação](operacao-producao.md).

## Limites da validação

Testes aprovados não comprovam ausência de defeitos nem substituem testes de carga, pentest ou aceite da infraestrutura definitiva. Não há percentual de cobertura de linhas/branches estabelecido. A análise integral de CVEs das imagens com Docker Scout depende de autenticação; auditoria de dependências da aplicação não substitui essa análise.
