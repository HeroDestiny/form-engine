# Documentação do Form Engine

| Documento | Finalidade |
|---|---|
| [README do projeto](../README.md) | Instalação, execução local e contribuição. |
| [Visão do produto](visao.md) | Objetivos e escopo da v1. |
| [Arquitetura](arquitetura.md) | Camadas, isolamento, segurança e decisões técnicas. |
| [Modelo de dados](modelo_de_dados.md) | Entidades, relacionamentos e restrições. |
| [Casos de uso](casos-de-uso/README.md) | Fluxos e regras de negócio dos 21 casos de uso. |
| [Guia da API](api/README.md) | Autenticação, operações, exemplos e limites. |
| [OpenAPI](api/openapi.yaml) | Contrato formal das 31 operações. |
| [Coleção Postman](api/postman-collection.json) | Requisições para testar a API. |
| [Conferência funcional da v1](fechamento-funcional-v1.md) | Matriz dos 21 casos de uso, implementações e evidências de validação. |
| [Testes](testes.md) | Execução das suítes isoladas e verificações de qualidade. |
| [Operação](operacao-producao.md) | Configuração de deploy, saúde, backup, restauração e rollback. |
| [Scripts](../scripts/README.md) | Comandos de desenvolvimento e operação. |

Ao alterar comportamento, atualize os casos de uso e o contrato da API afetados. Comandos e configuração devem acompanhar os arquivos executáveis do projeto.
