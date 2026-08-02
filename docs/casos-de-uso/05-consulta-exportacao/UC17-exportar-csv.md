# UC17 - Exportar Dados em CSV

**Categoria:** Consulta e Exportação
**Ator Principal:** Gestor (Manager) / Administrador

---

## Descrição

Permite exportar submissões de formulários para arquivo CSV, facilitando análise em planilhas e sistemas externos.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Existem submissões para exportar

---

## Pós-condições

- Arquivo CSV gerado
- Download disponível
- Ação registrada no audit log

---

## Fluxo Principal

1. Gestor acessa funcionalidade de exportação (a partir de UC15 ou diretamente)
2. Sistema exibe opções de exportação:
   - Selecionar formulário
   - Selecionar versão (ou todas)
   - Filtrar por período (data inicial e final)
   - Filtrar por usuário (opcional)
3. Gestor define parâmetros e confirma exportação
4. Sistema valida filtros aplicados
5. Sistema busca dados do tenant aplicando filtros:
   - Apenas submissões finalizadas (status = submitted)
   - Isolamento por tenant_id
6. Sistema gera arquivo CSV:
   - Cabeçalho com nomes dos campos (labels)
   - Uma linha por submission
   - Colunas adicionais: ID, usuário, data
   - Valores formatados por tipo
7. Sistema registra ação no log de auditoria
8. Sistema disponibiliza arquivo para download
9. Sistema exibe confirmação com estatísticas:
   - Quantidade de registros exportados
   - Período coberto
   - Tamanho do arquivo

---

## Fluxos Alternativos

### FA01 - Nenhuma submissão para exportar

**Quando:** Passo 5 - Filtros não retornam resultados

1. Sistema detecta lista vazia
2. Sistema exibe mensagem: "Nenhuma submissão encontrada para exportar"
3. Sistema sugere ajustar filtros
4. Caso de uso é encerrado

### FA02 - Múltiplas versões com campos diferentes

**Quando:** Passo 6 - Exportação de múltiplas versões

1. Sistema combina campos de todas versões
2. Campos que não existem em versão são deixados vazios
3. CSV contém união de todos campos
4. Adiciona coluna `versão` para identificar
5. Continua para passo 7

### FA03 - Volume muito grande

**Quando:** Passo 5 - Mais de X mil registros (futuro)

1. Sistema detecta volume alto
2. Sistema exibe aviso: "Exportação grande. Será processada em background."
3. Sistema agenda job assíncrono
4. Gestor recebe notificação quando concluir
5. Caso de uso é encerrado (download será feito depois)

---

## Regras de Negócio

- **RN01:** Exportação respeita isolamento do tenant
- **RN02:** Apenas submissions finalizadas (submitted) são exportadas
- **RN03:** Dados são exportados na estrutura da versão
- **RN04:** Múltiplas versões geram colunas combinadas
- **RN05:** Encoding UTF-8 com BOM (compatibilidade Excel)
- **RN06:** Separador: vírgula ou ponto-e-vírgula (configurável)
- **RN07:** Valores com vírgula/aspas são escapados corretamente

---

## Estrutura do CSV

### CSV Simples (uma versão)

```csv
ID,Usuário,Email,Data de Submissão,Versão,Nome Completo,Estado Civil,Data de Nascimento,Telefone,Observações
452,Maria Santos,maria@example.com,22/03/2026 19:30,1,Maria Santos,Solteiro(a),15/05/1990,(11) 98765-4321,
451,João Silva,joao@example.com,22/03/2026 18:15,1,João Silva,Casado(a),10/03/1985,(11) 91234-5678,Primeira solicitação
```

### CSV com Múltiplas Versões

```csv
ID,Usuário,Data,Versão,Nome,Estado Civil,CPF,Telefone,Endereço
450,Ana,22/03 17:00,2,Ana Silva,Divorciado(a),123.456.789-00,(11) 99999-9999,Rua X
449,Pedro,21/03 16:00,1,Pedro Costa,Solteiro(a),,,
```
*Nota: CPF e Endereço são campos da v2, vazios na v1.*

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| form_id | integer | Sim | ID do formulário |
| version_ids | array | Não | IDs de versões específicas (ou todas) |
| date_from | date | Não | Data inicial |
| date_to | date | Não | Data final |
| user_ids | array | Não | IDs de usuários específicos |
| format | string | Não | csv (default), xlsx (futuro) |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```
HTTP/1.1 200 OK
Content-Type: text/csv; charset=utf-8
Content-Disposition: attachment; filename="cadastro-beneficiarios-20260322.csv"

[conteúdo CSV]
```

### Metadados da Exportação (opcional)

```json
{
  "success": true,
  "data": {
    "filename": "cadastro-beneficiarios-20260322.csv",
    "records_count": 127,
    "file_size": "45 KB",
    "form": {
      "id": 5,
      "name": "Cadastro de Beneficiários"
    },
    "versions": [1, 2],
    "date_range": {
      "from": "2026-03-01",
      "to": "2026-03-31"
    }
  },
  "message": "Exportação gerada com sucesso",
  "errors": null
}
```

---

## Endpoint da API

```
POST /api/submissions/export
Content-Type: application/json
Authorization: Bearer {token}

{
  "form_id": 5,
  "version_ids": [1],
  "date_from": "2026-03-01",
  "date_to": "2026-03-31",
  "format": "csv"
}
```

Ou via GET com query parameters:

```
GET /api/submissions/export?form_id=5&date_from=2026-03-01&date_to=2026-03-31
Authorization: Bearer {token}
```

---

## Implementação na Interface

Na interface, a exportação em CSV deve estar ligada principalmente à lista geral de submissões:

1. Usuário com papel `manager` ou `admin` acessa `/submissions` pelo menu superior.
2. A tela deve exibir um botão "Exportar CSV" associado ao conjunto de filtros atualmente aplicado (formulário, período, etc.).
3. Ao acionar o botão, o frontend deve chamar `POST /api/submissions/export` (ou `GET` equivalente) com os parâmetros de filtro selecionados e iniciar o download do arquivo CSV retornado pela API.
4. A interface deve deixar claro, na mensagem ou no nome do arquivo, qual formulário/período está sendo exportado (por exemplo, refletindo `form_id` e intervalo de datas).

---

## Formatação de Valores no CSV

| Tipo | Valor Original | Valor no CSV |
|------|----------------|--------------|
| text | "João Silva" | João Silva |
| number | "42" | 42 |
| email | "user@example.com" | user@example.com |
| date | "2026-03-22" | 22/03/2026 |
| select | "casado" | Casado(a) |
| checkbox | ["opt1","opt2"] | "Opção 1, Opção 2" |
| textarea | "linha1\nlinha2" | "linha1 linha2" (sem quebras) |

---

## Testes Requeridos

### Teste de Sucesso
✅ Exportar submissões de uma versão
✅ Exportar múltiplas versões
✅ Exportar com filtro por período
✅ Verificar encoding UTF-8 com BOM
✅ Verificar registro no audit log

### Testes de Formatação
✅ Verificar escape de valores com vírgula
✅ Verificar valores com aspas
✅ Verificar campos vazios
✅ Verificar formatação de datas
✅ Verificar labels de options

### Testes de Isolamento
🔒 Verificar que apenas dados do tenant são exportados
🔒 Verificar que user não pode exportar

### Testes de Integridade
🔍 Verificar quantidade de linhas vs submissões
🔍 Verificar completude dos dados
🔍 Verificar compatibilidade com Excel

---

## Exceções

- `ValidationException`: Parâmetros inválidos
- `NoDataToExportException`: Nenhuma submissão encontrada
- `UnauthorizedException`: Sem permissão (papel user)

---

## Dependências

- Service: `ExportService`, `FormSubmissionService`
- Repository: `FormSubmissionRepository`
- Model: `FormSubmission`, `Form`, `FormVersion`, `FormField`
- Package: `league/csv` ou similar
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC15:** Consultar Submissões (origem da exportação)
- **UC16:** Visualizar Detalhes (visualização individual)

---

## Notas de Implementação

### Gerar CSV com League CSV

```php
use League\Csv\Writer;

public function exportToCsv(Collection $submissions, FormVersion $version)
{
    $csv = Writer::createFromString('');
    $csv->setOutputBOM(Writer::BOM_UTF8); // Para Excel

    // Cabeçalho
    $headers = ['ID', 'Usuário', 'Email', 'Data', 'Versão'];
    foreach ($version->fields as $field) {
        $headers[] = $field->label;
    }
    $csv->insertOne($headers);

    // Dados
    foreach ($submissions as $submission) {
        $row = [
            $submission->id,
            $submission->user->name,
            $submission->user->email,
            $submission->submitted_at->format('d/m/Y H:i'),
            $submission->formVersion->version_number,
        ];

        foreach ($version->fields as $field) {
            $value = $submission->values
                ->firstWhere('form_field_id', $field->id);
            $row[] = $this->formatValue($value, $field);
        }

        $csv->insertOne($row);
    }

    return $csv->toString();
}
```

### Nome do Arquivo

```php
$formSlug = Str::slug($form->name);
$date = now()->format('Ymd');
$filename = "{$formSlug}-{$date}.csv";
```

### Response com Download

```php
return response($csvContent)
    ->header('Content-Type', 'text/csv; charset=utf-8')
    ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
```

### Exportação Assíncrona (Futuro)

```php
// Para volumes grandes
ExportJob::dispatch($formId, $filters, auth()->user());

// Job processa em background e envia e-mail com link de download
```

---

## Melhorias Futuras (Fora do Escopo v1)

### Formatos Adicionais
- XLSX (Excel nativo)
- JSON
- XML
- PDF (relatório formatado)

### Funcionalidades
- Templates de exportação customizáveis
- Agendamento de exportações periódicas
- Exportação incremental (apenas novos dados)
- Compactação (ZIP para grandes volumes)

### Performance
- Streaming de grandes volumes
- Cache de exportações recentes
- Processamento assíncrono

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Alta (Funcionalidade core)
