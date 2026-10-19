# Conferência funcional da v1 — 17/10/2026

Esta matriz confronta os 21 casos de uso com as telas e os contratos HTTP atuais.
Os exemplos conceituais antigos dos documentos cedem ao contrato implementado,
conforme o README dos casos de uso. Itens futuros, como expiração de rascunhos e
acesso de suporte entre tenants, não fazem parte da v1.

“Implementado” significa que a operação tem caminho na interface e proteção na
API. A coluna de evidência identifica testes automatizados executados; não
significa que cada cenário foi reproduzido manualmente em todos os navegadores.

| UC | Funcionalidade | Estado e caminho no frontend | Evidência de API |
| --- | --- | --- | --- |
| 01 | Criar tenant | Implementado em `/admin/tenants`, perfil admin-sistema | TenantUserUseCasesContractTest; TenantSessionContractTest |
| 02 | Ativar/desativar tenant | Implementado com confirmação do impacto e preservação de dados | TenantUserUseCasesContractTest; TenantSessionContractTest |
| 03 | Criar usuário | Implementado em `/admin/users`, dentro do próprio tenant | TenantUserUseCasesContractTest |
| 04 | Ativar/desativar usuário | Implementado com confirmação; ação sobre a própria conta desabilitada | TenantUserUseCasesContractTest; RoutePermissionMatrixTest |
| 05 | Criar formulário | Implementado em `/forms/new`; abre o editor da versão inicial | FormUseCasesContractTest; FormFlowUseCasesContractTest |
| 06 | Adicionar campos | Implementado no editor, com oito tipos, opções e ordem | FormUseCasesContractTest; DynamicFieldValidationContractTest |
| 07 | Editar campos | Implementado somente em rascunhos de versão | FormUseCasesContractTest; VersionHistoryContractTest |
| 08 | Remover campo | Implementado com confirmação, somente em versão em edição | FormUseCasesContractTest; VersionHistoryContractTest |
| 09 | Publicar versão | Implementado no detalhe, com resumo e confirmação; exige campos | FormUseCasesContractTest; VersionHistoryContractTest |
| 10 | Criar nova versão | Implementado com confirmação, cópia de campos e abertura do editor | VersionHistoryContractTest; FormFlowUseCasesContractTest |
| 11 | Ativar/desativar formulário | Implementado com confirmação e aviso de impacto | FormUseCasesContractTest; FormFlowUseCasesContractTest |
| 12 | Listar formulários | Implementado com busca/ordenação e escopo conforme perfil | FormFlowUseCasesContractTest; RoutePermissionMatrixTest |
| 13 | Preencher/enviar | Implementado com validação por campo e bloqueio após confirmação | DynamicFieldValidationContractTest; FormFlowUseCasesContractTest |
| 14 | Rascunhos | Implementado: criar, substituir respostas, limpar, retomar e finalizar na versão original | DraftLifecycleContractTest |
| 15 | Consultar submissões | Implementado: formulário, versão, usuário, status, período, paginação e autor do envio | QueryAuditUseCasesContractTest; RoutePermissionMatrixTest |
| 16 | Detalhar submissão | Implementado com proprietário, data, valores e rótulos formatados; retomada somente pelo proprietário | QueryAuditUseCasesContractTest; ComprehensiveAcceptanceRegressionTest |
| 17 | Exportar CSV | Implementado: formulário, seleção de versões, período, separador e tamanho do arquivo | CsvExportContractTest; ComprehensiveAcceptanceRegressionTest |
| 18 | Consultar auditoria | Implementado com filtros e paginação, restrito a administradores | QueryAuditUseCasesContractTest; RoutePermissionMatrixTest |
| 19 | Detalhar auditoria | Implementado: metadados, usuário, IP, agente, existência da entidade e ações relacionadas | QueryAuditUseCasesContractTest; AuditRecordingContractTest |
| 20 | Autenticar/sair | Implementado com sessão compartilhada, expiração e tratamento de falhas | AuthContractTest; TenantSessionContractTest |
| 21 | Verificar permissões | Implementado nas rotas/ações e na API; tenant permanece obrigatório | RoutePermissionMatrixTest; ComprehensiveAcceptanceRegressionTest |

## Ajustes realizados nesta conferência

- Inclusão dos filtros de versão e autor em submissões; o filtro de autor é
  disponibilizado a gestores/administradores. A API mantém usuários finais
  restritos aos próprios registros, independentemente dos parâmetros enviados.
- Seleção de múltiplas versões e separador do CSV. O endpoint de exportação não
  aceita filtro por usuário; a interface bloqueia a exportação nesse contexto e
  pede a remoção do filtro, impedindo uma ampliação silenciosa dos resultados.
- Filtros aplicados, página e tamanho da página ficam na URL. Links para detalhes
  carregam um retorno interno validado. Atualizar, voltar e compartilhar uma URL
  de consulta restaura seus critérios; respostas da API continuam autenticadas.
- Confirmações para mudanças de status, publicação e criação de versão; cadastro
  de formulário redireciona ao editor inicial.
- Exibição de autor no detalhe da submissão e IP/agente no detalhe da auditoria.
  Links de entidade são apresentados somente quando a entidade existe e possui
  destino de detalhe disponível na interface (formulário ou submissão).

## Evidências executadas

- Frontend: **43 testes**, incluindo quatro perfis, sessão, consultas, retorno de
  detalhes, filtros, seleção de exportação, rascunhos e respostas concorrentes.
- Backend: **354 testes e 1.643 assertions** (247 de contratos/casos de uso e 107 de filtros/paginação), executados no contêiner com
  `phpunit.xml`: SQLite em memória, `APP_ENV=testing`, sem usar o banco local.
- Build de produção do frontend e `git diff --check`.
- Teste integrado local anterior: oito tipos de campo, duas publicações,
  rascunho parcial/limpeza/finalização na versão original, dois envios, CSV real
  e ativação/desativação. Registros identificados como validação funcional.
- Verificação de navegador com conta administrativa local: consulta por versão/autor, retorno preservando filtros e tamanho da página; histórico e leitura
  de versões, obrigatórios, salvamento parcial e retomada pelo detalhe.
- Exportação pela interface com duas versões e separador `;`: download confirmado,
  **2 registros e 583 bytes**, com estatísticas calculadas a partir do CSV real.

Os testes de permissões e isolamento cobrem múltiplos perfis/tenants. A exploração
manual de navegador realizada até aqui usou a conta administrativa local; não é
uma homologação manual completa de todos os perfis ou dispositivos. Essa
distinção deve acompanhar qualquer declaração de fechamento da v1.
