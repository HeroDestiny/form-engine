# UC12 - Listar Formulários Disponíveis

**Categoria:** Preenchimento de Formulários
**Ator Principal:** Usuário Final

---

## Descrição

Permite ao usuário visualizar todos os formulários ativos e publicados do seu tenant, prontos para serem preenchidos.

---

## Pré-condições

- Usuário autenticado
- Tenant está ativo

---

## Pós-condições

- Lista de formulários exibida
- Usuário pode selecionar formulário para preencher (UC13)

---

## Fluxo Principal

1. Usuário acessa área de formulários
2. Sistema busca formulários do tenant do usuário com critérios:
   - `tenant_id` = tenant do usuário
   - `is_active = true`
   - Possui versão publicada
3. Sistema ordena formulários por:
   - Nome (alfabética)
   - Ou data de criação (mais recentes primeiro)
4. Sistema exibe lista com informações:
   - Nome do formulário
   - Descrição
   - Versão atual publicada
   - Ícone ou indicador visual
5. Usuário visualiza lista
6. Usuário pode:
   - Selecionar formulário para preencher (UC13)
   - Aplicar filtros (opcional)
   - Ver própriassubmissões anteriores

---

## Fluxos Alternativos

### FA01 - Nenhum formulário disponível

**Quando:** Passo 2 - Tenant sem formulários ativos

1. Sistema detecta lista vazia
2. Sistema exibe mensagem: "Nenhum formulário disponível no momento"
3. Usuário visualiza tela vazia com orientações

### FA02 - Filtrar formulários (opcional)

**Quando:** Passo 5 - Usuário aplica filtro

1. Usuário insere termo de busca
2. Sistema filtra por nome ou descrição
3. Sistema exibe resultados filtrados
4. Retorna ao passo 5 do fluxo principal

---

## Regras de Negócio

- **RN01:** Apenas formulários ativos são listados
- **RN02:** Apenas formulários com versão publicada aparecem
- **RN03:** Isolamento por tenant é obrigatório
- **RN04:** Ordenação padrão por nome (alfabética)
- **RN05:** Usuário vê todos formulários do tenant (não há filtro por permissão individual)

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "forms": [
      {
        "id": 5,
        "name": "Cadastro de Beneficiários",
        "description": "Formulário para registro de novos beneficiários do programa",
        "latest_version": {
          "id": 8,
          "version_number": 1,
          "published_at": "2026-03-22T16:00:00Z",
          "fields_count": 5
        },
        "created_at": "2026-03-22T15:00:00Z"
      },
      {
        "id": 7,
        "name": "Pesquisa de Satisfação",
        "description": "Avaliação dos serviços prestados",
        "latest_version": {
          "id": 15,
          "version_number": 2,
          "published_at": "2026-03-20T10:00:00Z",
          "fields_count": 8
        },
        "created_at": "2026-03-18T09:00:00Z"
      }
    ],
    "total": 2
  },
  "message": null,
  "errors": null
}
```

### Resposta com Lista Vazia (200 OK)

```json
{
  "success": true,
  "data": {
    "forms": [],
    "total": 0
  },
  "message": "Nenhum formulário disponível no momento",
  "errors": null
}
```

---

## Endpoint da API

```
GET /api/forms
Authorization: Bearer {token}

Query Parameters (opcional):
- search: termo de busca
- sort: name|created_at
- order: asc|desc
```

---

## Modelo de Dados Consultado

```sql
SELECT f.*, fv.*
FROM forms f
INNER JOIN form_versions fv ON f.id = fv.form_id
WHERE f.tenant_id = ?
  AND f.is_active = true
  AND fv.is_published = true
  AND fv.version_number = (
    SELECT MAX(version_number)
    FROM form_versions
    WHERE form_id = f.id AND is_published = true
  )
ORDER BY f.name ASC;
```

---

## Interface Visual Sugerida

### Card de Formulário

```
┌────────────────────────────────────────┐
│ 📋 Cadastro de Beneficiários          │
│                                        │
│ Formulário para registro de novos     │
│ beneficiários do programa              │
│                                        │
│ Versão: 1  •  5 campos                │
│                                        │
│ [Preencher Formulário]                 │
└────────────────────────────────────────┘
```

---

## Testes Requeridos

### Teste de Sucesso
✅ Listar formulários ativos do tenant
✅ Verificar que apenas formulários ativos aparecem
✅ Verificar que apenas formulários publicados aparecem
✅ Verificar isolamento por tenant

### Testes de Isolamento
🔒 Verificar que usuário não vê formulários de outros tenants
🔒 Verificar que formulários desativados não aparecem

### Testes Funcionais
🔍 Verificar ordenação alfabética
🔍 Verificar filtro por nome (se implementado)
🔍 Verificar exibição de versão correta (última publicada)

### Testes de Caso Vazio
📭 Verificar comportamento quando não há formulários
📭 Verificar mensagem apropriada

---

## Exceções

- `UnauthorizedException`: Usuário não autenticado
- `TenantInactiveException`: Tenant desativado

---

## Dependências

- Service: `FormService`
- Repository: `FormRepository`
- Model: `Form`, `FormVersion`
- Middleware: `Authenticate`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC13:** Preencher Formulário (próximo passo)
- **UC11 :** Ativar/Desativar Formulário (afeta listagem)
- **UC09:** Publicar Versão (torna formulário visível)

---

## Melhorias Futuras (Fora do Escopo v1)

### Filtros Avançados
- Por categoria
- Por data de publicação
- Por departamento

### Informações Adicionais
- Número de submissões do usuário
- Prazo de preenchimento
- Status: novo/em andamento/concluído

### Visualização
- Modo lista ou grade
- Preview dos campos
- Tags e categorias

---

## Notas de Implementação

### Query Otimizada com Relacionamentos

```php
Form::with(['latestPublishedVersion.fields'])
    ->where('tenant_id', auth()->user()->tenant_id)
    ->where('is_active', true)
    ->whereHas('versions', function ($query) {
        $query->where('is_published', true);
    })
    ->orderBy('name')
    ->get();
```

### Scope para Versão Publicada

```php
// No model Form
public function latestPublishedVersion()
{
    return $this->hasOne(FormVersion::class)
        ->where('is_published', true)
        ->latest('version_number');
}
```

### Paginação (Recomendado)

```php
Form::where('tenant_id', auth()->user()->tenant_id)
    ->where('is_active', true)
    ->paginate(20);
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Alta (Experiência do usuário)
