<script setup>
import { useRoute } from 'vue-router'
import { useQueryListing } from '../composables/useQueryListing.js'
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { exportSubmissionsCsv, listAllForms, listForms, listSubmissions, listFormVersions } from '../api'
import { canManageForms } from '../auth/session.js'
import { errorMessage, validatePeriod } from '../composables/useListing.js'
import { buildExportFilters, formatDate } from '../utils/display.js'
import { initialsFromName } from '../composables/useTheme.js'
import { pushToast } from '../composables/useToast.js'
import ListContent from '../components/ListContent.vue'
import ListPagination from '../components/ListPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import AppIcon from '../components/AppIcon.vue'

const route = useRoute()
const { state, filters, applied, perPage, load, apply, reset, setPage, setPerPage } = useQueryListing(
  (params, options) => listSubmissions({ ...params, user_id: canManageForms.value ? params.user_id : '' }, options),
  'submissions',
  { form_id: '', version_id: '', user_id: '', status: 'submitted', date_from: '', date_to: '' },
  { validate: validatePeriod },
)

const versions = ref([])
const versionsLoading = ref(false)
const versionsError = ref('')
const exportSeparator = ref(',')
const exportVersionIds = ref(applied.value.version_id ? [applied.value.version_id] : [])
const exportModalOpen = ref(false)
const showAdvanced = ref(Boolean(
  applied.value.version_id || applied.value.user_id || applied.value.date_from || applied.value.date_to,
))
let versionRequest = 0

const forms = ref([])
const formsError = ref('')
const exporting = reactive({ busy: false, error: '', message: '' })

const advancedFilterCount = computed(() => (
  [applied.value.version_id, applied.value.user_id, applied.value.date_from, applied.value.date_to]
    .filter(Boolean).length
))

const hasActiveFilters = computed(() => (
  !!applied.value.form_id ||
  !!applied.value.version_id ||
  !!applied.value.user_id ||
  applied.value.status !== 'submitted' ||
  !!applied.value.date_from ||
  !!applied.value.date_to
))

const authorOptions = computed(() => [
  ...new Map(state.items.filter(item => item.user).map(item => [item.user.id, item.user])).values(),
])

const publishedVersions = computed(() => versions.value.filter(item => item.is_published))

const selectedFormName = computed(() => {
  const match = forms.value.find(item => String(item.id) === String(applied.value.form_id))
  return match?.name || (applied.value.form_id ? `Formulário #${applied.value.form_id}` : '')
})

const canExport = computed(() => (
  !exporting.busy &&
  !state.loading &&
  !state.error &&
  !!applied.value.form_id &&
  applied.value.status !== 'draft' &&
  !applied.value.user_id &&
  filters.form_id === applied.value.form_id &&
  !versionsLoading.value &&
  !versionsError.value
))

const estimatedExportCount = computed(() => {
  if (!state.pagination) return 'Todos os registros correspondentes'
  const matchesAppliedVersion = (
    (!exportVersionIds.value.length && !applied.value.version_id) ||
    (exportVersionIds.value.length === 1 && String(exportVersionIds.value[0]) === String(applied.value.version_id))
  )
  return matchesAppliedVersion
    ? `${state.pagination.total} registro(s) estimado(s)`
    : `Seleção personalizada entre até ${state.pagination.total} envio(s) do formulário`
})

async function loadVersions(formId) {
  const request = ++versionRequest
  versions.value = []
  versionsError.value = ''
  versionsLoading.value = !!formId
  if (!formId) return
  try {
    const data = await listFormVersions(formId)
    if (request === versionRequest) versions.value = data?.data?.versions ?? []
  } catch (error) {
    if (request === versionRequest) versionsError.value = errorMessage(error)
  } finally {
    if (request === versionRequest) versionsLoading.value = false
  }
}

watch(() => filters.form_id, id => {
  filters.version_id = ''
  exportVersionIds.value = []
  loadVersions(id)
})

onUnmounted(() => { versionRequest++ })

async function loadForms() {
  formsError.value = ''
  try {
    const response = await (canManageForms.value ? listAllForms() : listForms())
    forms.value = response?.data?.forms ?? []
  } catch (error) {
    formsError.value = errorMessage(error)
  }
}

function openExportModal() {
  if (!canExport.value) return
  exporting.error = ''
  if (applied.value.version_id && !exportVersionIds.value.length) {
    exportVersionIds.value = [String(applied.value.version_id)]
  }
  exportModalOpen.value = true
}

async function handleExport() {
  exporting.error = ''
  exporting.message = ''
  let params
  try {
    if (!canExport.value) return
    params = buildExportFilters({
      ...applied.value,
      version_ids: exportVersionIds.value,
      separator: exportSeparator.value,
    })
  } catch (error) {
    exporting.error = error.message
    return
  }
  exporting.busy = true
  try {
    const result = await exportSubmissionsCsv(params)
    exportModalOpen.value = false
    exporting.message = `CSV exportado: ${result.rows} registro(s), ${result.bytes} bytes.`
    pushToast(exporting.message)
  } catch (error) {
    exporting.error = errorMessage(error)
  } finally {
    exporting.busy = false
  }
}

onMounted(() => {
  load()
  loadForms()
  loadVersions(filters.form_id)
})
</script>

<template>
  <div class="page-stack">
    <div class="page-header">
      <div>
        <h1>Submissões e Rascunhos</h1>
        <p class="panel-subtitle">Consulte protocolos enviados, retome rascunhos ou extraia planilhas consolidadas.</p>
      </div>
      <div class="panel-actions">
        <button class="ghost-button" type="button" :disabled="state.loading" @click="load">
          <AppIcon name="refresh" :size="16" />
          <span>Atualizar</span>
        </button>
        <button
          v-if="canManageForms"
          class="primary-button"
          type="button"
          :disabled="!canExport"
          :title="!canExport ? 'Selecione e aplique um formulário com status Enviadas (sem filtro de usuário) para exportar' : 'Configurar exportação CSV'"
          @click="openExportModal"
        >
          <AppIcon name="download" :size="16" />
          <span>{{ exporting.busy ? 'Exportando…' : 'Exportar CSV' }}</span>
        </button>
      </div>
    </div>

    <div v-if="formsError" class="feedback error" role="alert">
      <span>Não foi possível carregar a lista de formulários.</span>
      <button class="ghost-button" type="button" @click="loadForms">Tentar novamente</button>
    </div>

    <!-- Barra de Filtros Compacta + Gaveta Avançada -->
    <div class="toolbar-card">
      <form aria-label="Filtros de submissões" @submit.prevent="apply">
        <div class="filter-bar">
          <div class="field filter-search">
            <label for="sub-filter-form">Formulário</label>
            <select id="sub-filter-form" v-model="filters.form_id">
              <option value="">Todos os formulários</option>
              <option v-for="form in forms" :key="form.id" :value="String(form.id)">
                {{ form.name }}
              </option>
            </select>
          </div>

          <div class="field">
            <label for="sub-filter-status">Status</label>
            <select id="sub-filter-status" v-model="filters.status">
              <option value="submitted">Enviadas</option>
              <option value="draft">Rascunhos</option>
            </select>
          </div>

          <div class="filter-actions">
            <button
              class="ghost-button"
              type="button"
              :aria-expanded="showAdvanced"
              @click="showAdvanced = !showAdvanced"
            >
              <AppIcon name="filter" :size="15" />
              <span>Avançado{{ advancedFilterCount ? ` (${advancedFilterCount})` : '' }}</span>
            </button>
            <button class="primary-button" type="submit" :disabled="state.loading">Aplicar</button>
            <button class="ghost-button" type="button" :disabled="state.loading" @click="reset">Limpar</button>
          </div>
        </div>

        <div v-if="showAdvanced" class="filter-drawer" style="margin-top: 0.9rem;">
          <div class="field">
            <label for="sub-filter-version">Versão</label>
            <select id="sub-filter-version" v-model="filters.version_id" :disabled="!filters.form_id || versionsLoading">
              <option value="">Todas as versões</option>
              <option v-for="version in versions" :key="version.id" :value="String(version.id)">
                Versão {{ version.version_number }} · {{ version.is_published ? 'Publicada' : 'Em edição' }}
              </option>
            </select>
          </div>

          <div v-if="canManageForms" class="field">
            <label for="sub-filter-user">ID do respondente</label>
            <input
              id="sub-filter-user"
              v-model="filters.user_id"
              type="number"
              min="1"
              max="2147483647"
              step="1"
              list="submission-authors"
              placeholder="Todos os usuários"
            />
            <datalist id="submission-authors">
              <option v-for="author in authorOptions" :key="author.id" :value="author.id">
                {{ author.name }} · {{ author.email }}
              </option>
            </datalist>
          </div>

          <div class="field">
            <label for="sub-filter-from">Data inicial</label>
            <input id="sub-filter-from" v-model="filters.date_from" type="date" :max="filters.date_to || '9999-12-31'" />
          </div>

          <div class="field">
            <label for="sub-filter-to">Data final</label>
            <input id="sub-filter-to" v-model="filters.date_to" type="date" :min="filters.date_from" max="9999-12-31" />
          </div>
        </div>
      </form>

      <div v-if="hasActiveFilters" class="filter-chips" aria-label="Filtros aplicados">
        <span v-if="applied.form_id" class="filter-chip">Formulário: {{ selectedFormName }}</span>
        <span v-if="applied.status === 'draft'" class="filter-chip">Status: Rascunhos</span>
        <span v-if="applied.version_id" class="filter-chip">Versão ID #{{ applied.version_id }}</span>
        <span v-if="applied.user_id" class="filter-chip">Usuário #{{ applied.user_id }}</span>
        <span v-if="applied.date_from || applied.date_to" class="filter-chip">
          Período: {{ applied.date_from || '…' }} até {{ applied.date_to || '…' }}
        </span>
      </div>

      <p v-if="canManageForms" class="filter-help">
        <template v-if="canExport">
          Exportação habilitada para <strong>{{ selectedFormName }}</strong> ({{ estimatedExportCount }}).
        </template>
        <template v-else>
          Para exportar em CSV, filtre por um formulário específico com status “Enviadas” (sem filtro individual de usuário).
        </template>
      </p>
    </div>

    <p v-if="versionsError" class="feedback error" role="alert">
      <span>{{ versionsError }}</span>
      <button class="ghost-button" type="button" @click="loadVersions(filters.form_id)">Recarregar versões</button>
    </p>
    <p v-if="exporting.error && !exportModalOpen" class="feedback error" role="alert">{{ exporting.error }}</p>

    <!-- Data Table Corporativa -->
    <div class="table-card">
      <ListContent
        :loading="state.loading"
        :error="state.error"
        :empty="!state.items.length"
        :can-clear-filters="hasActiveFilters"
        @retry="load"
        @clear="reset"
      >
        <div class="data-table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Protocolo</th>
                <th scope="col">Formulário</th>
                <th v-if="canManageForms" scope="col">Respondente</th>
                <th scope="col">Status</th>
                <th scope="col">Data</th>
                <th scope="col" class="col-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="submission in state.items" :key="submission.id">
                <td data-label="Protocolo">
                  <span class="mono-badge">#{{ submission.id }}</span>
                </td>
                <td data-label="Formulário">
                  <div class="cell-stack">
                    <strong>{{ submission.form?.name || 'Formulário' }}</strong>
                    <small class="tabular">
                      Versão {{ submission.version?.version_number }} · {{ submission.fields_count }} campo(s)
                    </small>
                  </div>
                </td>
                <td v-if="canManageForms" data-label="Respondente">
                  <div v-if="submission.user" class="cell-user">
                    <span class="avatar-badge sm" aria-hidden="true">{{ initialsFromName(submission.user.name) }}</span>
                    <div class="cell-stack">
                      <strong>{{ submission.user.name || 'Usuário removido' }}</strong>
                      <small>{{ submission.user.email }} · ID {{ submission.user.id }}</small>
                    </div>
                  </div>
                  <span v-else class="field-hint">—</span>
                </td>
                <td data-label="Status">
                  <StatusBadge
                    :tone="submission.submitted_at ? 'success' : 'warning'"
                    :label="submission.submitted_at ? 'Enviada' : 'Rascunho'"
                  />
                </td>
                <td data-label="Data" class="tabular">
                  {{ submission.submitted_at ? formatDate(submission.submitted_at) : 'Em rascunho' }}
                </td>
                <td data-label="Ações" class="col-right">
                  <RouterLink
                    class="ghost-button"
                    :to="{ path: `/submissions/${submission.id}`, query: { returnTo: route.fullPath } }"
                  >
                    Abrir prontuário
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </ListContent>

      <ListPagination
        select-id="submissions-per-page"
        :pagination="state.pagination"
        :loading="state.loading"
        :per-page="perPage"
        @page="setPage"
        @per-page="setPerPage"
      />
    </div>

    <!-- Modal Acessível de Exportação CSV -->
    <ConfirmDialog
      :open="exportModalOpen"
      dialog-id="csv-export-modal"
      title="Exportar submissões em CSV"
      description="Revise o escopo da exportação. O arquivo inclui todas as páginas correspondentes em UTF-8 com proteção contra injeção de fórmulas."
      :confirm-label="exporting.busy ? 'Gerando arquivo…' : 'Baixar arquivo CSV'"
      cancel-label="Fechar"
      :busy="exporting.busy"
      :confirm-disabled="!canExport"
      @confirm="handleExport"
      @cancel="exportModalOpen = false"
    >
      <ul class="export-summary-list">
        <li><strong>Formulário:</strong> {{ selectedFormName }}</li>
        <li><strong>Período aplicado:</strong> {{ applied.date_from || 'Início' }} até {{ applied.date_to || 'Hoje' }}</li>
        <li><strong>Volume estimado:</strong> {{ estimatedExportCount }}</li>
      </ul>

      <div class="form" style="margin-top: 1rem;">
        <div class="field">
          <label for="modal-export-separator">Separador de colunas</label>
          <select id="modal-export-separator" v-model="exportSeparator" :disabled="exporting.busy">
            <option value=",">Vírgula (,) — Padrão internacional</option>
            <option value=";">Ponto e vírgula (;) — Compatível com Excel PT-BR</option>
          </select>
        </div>

        <fieldset v-if="publishedVersions.length" class="response-options" :disabled="exporting.busy">
          <legend>Versões publicadas a incluir <small>(nenhuma marcada = todas)</small></legend>
          <div class="field-options">
            <label
              v-for="version in publishedVersions"
              :key="version.id"
              :for="`export-ver-${version.id}`"
              class="field-option"
            >
              <input
                :id="`export-ver-${version.id}`"
                v-model="exportVersionIds"
                type="checkbox"
                :value="String(version.id)"
              />
              <span>Versão {{ version.version_number }}</span>
            </label>
          </div>
        </fieldset>

        <p v-if="exporting.error" class="feedback error" role="alert" style="margin-bottom: 0;">
          {{ exporting.error }}
        </p>
      </div>
    </ConfirmDialog>
  </div>
</template>
