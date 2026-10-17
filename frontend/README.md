# Frontend — Form Engine

SPA em Vue 3 com Vue Router e Vite. Requer Node.js 22.12 ou superior.

```powershell
npm ci
npm run dev -- --host 127.0.0.1
```

Abra http://127.0.0.1:5173. O proxy de desenvolvimento encaminha `/api` para
`http://127.0.0.1:8000`; os serviços do backend devem estar disponíveis nessa porta.

## Estrutura

- `src/components/AppLayout.vue`: cabeçalho, navegação, identidade, saída e retorno.
- `src/RootApp.vue`: estrutura compartilhada e montagem das páginas; oculta o
  conteúdo privado enquanto a identidade é verificada.
- `src/auth/session.js`: estado reativo compartilhado, restauração e permissões.
- `src/auth/token.js`: persistência do token e sincronização entre abas.
- `src/auth/permissions.js`: grupos de perfis e validação do endereço de retorno.
- `src/router/guards.js`: autenticação e autorização antes de montar cada página.
- `src/api.js`: chamadas autenticadas, erros da API e expiração em respostas 401,
  inclusive na exportação CSV.
- `src/style.css`: tema claro/escuro conforme o sistema, layout responsivo e
  controles compartilhados.
- `src/composables/useListing.js`: filtros em edição/aplicados, paginação,
  cancelamento de requisições e proteção contra respostas atrasadas.
- `src/components/ListContent.vue` e `ListPagination.vue`: estados de listagem,
  tentativas de recuperação e controles de página compartilhados.

## Sessão e acesso

O token continua armazenado em `localStorage` na chave `fe_token`. A identidade é
verificada em `/auth/me` ao abrir/recarregar a aplicação ou trocar o token; as
páginas compartilham o resultado. Requisições concorrentes usam a mesma
restauração. Respostas atrasadas de um token antigo não invalidam uma nova sessão.

As rotas são privadas por padrão. O login e a página de falha na verificação da
sessão são públicos. Após autenticar, o usuário retorna ao endereço interno
solicitado. Respostas 401 limpam a sessão e solicitam novo login; 403 de uma ação
mantêm a sessão. Falhas na verificação da identidade exibem uma página com opção
de tentar novamente ou voltar ao login.

| Perfil | Formulários e submissões | Gerir formulários / exportar | Usuários / auditoria | Tenants |
| --- | --- | --- | --- | --- |
| user | Sim, conforme o acesso da API | Não | Não | Não |
| manager | Sim | Sim | Não | Não |
| admin | Sim | Sim | Sim | Não |
| admin-sistema | Sim | Sim | Sim | Sim |

O contexto de usuários, formulários, submissões e auditoria continua limitado ao
tenant da sessão. A API é responsável pela autorização definitiva de cada ação.

## Verificação

```powershell
npm test
npm run build
```

Os testes usam o executor nativo do Node e simulam as respostas HTTP, sem alterar
o banco. Cobrem acesso por perfil, retorno após login, expiração, exportação,
indisponibilidade, restauração concorrente, logout, respostas atrasadas e troca de
sessão entre abas. O CI executa testes e build.

## Listagens

- Formulários: busca por nome, ordenação por nome ou criação, direção crescente
  ou decrescente e contagem dos resultados. Ativação do formulário e publicação
  da versão aparecem separadamente. A API retorna a coleção completa, sem
  paginação nesse endpoint.
- Submissões: filtros por formulário, versão, usuário (gestão), status e período; paginação e tamanho da
  página. O CSV usa o formulário e período **aplicados** e exporta todos os envios
  correspondentes, incluindo outras páginas. A API não exporta rascunhos. A interface permite escolher múltiplas versões e o separador; filtro por usuário bloqueia o CSV porque não é suportado por esse endpoint.
- Auditoria: filtros exatos de ação, usuário e entidade, período em dias UTC,
  paginação e tamanho da página.
- Usuários e tenants: paginação e tamanho da página, conforme os recursos da API.

Os formulários de busca aplicam alterações ao clicar no botão ou pressionar
Enter. Trocar de página mantém os filtros aplicados; aplicar ou limpar filtros,
assim como alterar o tamanho da página, retorna à primeira página. Requisições
anteriores são canceladas quando uma consulta substitui outra ou a tela fecha.
Falhas de carregamento exibem erro com nova tentativa, sem apresentar uma lista
vazia como se a consulta tivesse funcionado.

Além dos testes de autenticação, a suíte cobre parâmetros de consultas,
paginação, filtros, respostas concorrentes, cancelamento e seleção da exportação.

## Fluxo funcional de formulários

O detalhe do formulário permite consultar todas as versões e seus campos.
Versões publicadas são somente leitura; uma nova versão copia os campos da
última publicada e abre o editor. Uma versão sem campos não pode ser publicada.
O editor valida opções vazias/duplicadas e calcula a próxima ordem a partir da
maior ordem existente, mantendo a edição manual disponível.

O preenchimento usa os oito tipos de campo da API. O envio apresenta os erros
por campo retornados pelo servidor; salvar rascunho permite respostas parciais.
Atualizações substituem as respostas do mesmo rascunho, inclusive para limpar
todas. A retomada pelo detalhe da submissão só aparece ao proprietário e mantém
a versão original, mesmo quando existe uma publicação mais recente. Trocar de
contexto ou sair com alterações pendentes exige confirmação.

Envio, salvamento e troca de rascunho não podem correr simultaneamente. Depois da
confirmação do envio, os controles ficam bloqueados até iniciar explicitamente
outro preenchimento. Uma falha ao atualizar a lista de rascunhos não desfaz a
confirmação de um envio concluído. Falhas ao carregar outro rascunho bloqueiam o
envio de valores do contexto anterior.

### Teste integrado local

Com o backend local e uma conta administrativa do tenant `demo` existentes:

```powershell
$env:FORM_ENGINE_SMOKE_PASSWORD = '<senha local existente>'
node scripts/smoke-form-flow.mjs
```

O script usa `admin@formengine.local` por padrão; `FORM_ENGINE_SMOKE_EMAIL` pode
substituí-lo. Só aceita host local. Cria um formulário identificado como
validação funcional, oito tipos de campo, duas versões e duas submissões. Testa
rascunho parcial, limpeza, retomada na versão original, validação, finalização,
novo envio, CSV e ativação/desativação. Os registros ficam disponíveis para
revisão; a sessão do teste é encerrada. Se falhar, tenta desativar o formulário
criado. Esse teste modifica apenas os registros criados por sua execução e não
faz parte da suíte padrão de testes simulados.

Consultas aplicadas e paginação são persistidas na URL por `useQueryListing`. Links de detalhes usam `returnTo` validado para restaurar o contexto, inclusive após recarregar a página. A matriz dos 21 casos de uso e evidências está em [fechamento funcional](../docs/fechamento-funcional-v1.md).
