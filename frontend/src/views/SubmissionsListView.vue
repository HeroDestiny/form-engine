<script setup>
import { useRoute } from 'vue-router'
import { useQueryListing } from '../composables/useQueryListing.js'
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { exportSubmissionsCsv, listAllForms, listForms, listSubmissions, listFormVersions } from '../api'
import { canManageForms } from '../auth/session.js'
import { errorMessage, validatePeriod } from '../composables/useListing.js'
import { buildExportFilters, formatDate } from '../utils/display.js'
import ListContent from '../components/ListContent.vue'
import ListPagination from '../components/ListPagination.vue'

const route = useRoute()
const { state, filters, applied, perPage, load, apply, reset, setPage, setPerPage } = useQueryListing(
  (params, options) => listSubmissions({ ...params, user_id: canManageForms.value ? params.user_id : '' }, options), 'submissions', { form_id: '', version_id: '', user_id: '', status: 'submitted', date_from: '', date_to: '' }, { validate: validatePeriod },
)
const versions = ref([])
const versionsLoading = ref(false)
const versionsError = ref('')
const exportSeparator = ref(',')
const exportVersionIds = ref(applied.value.version_id ? [applied.value.version_id] : [])
let versionRequest = 0
const authorOptions = computed(() => [...new Map(state.items.filter(item => item.user).map(item => [item.user.id, item.user])).values()])
const canExport = computed(() => !exporting.busy && !state.loading && !state.error && !!applied.value.form_id && applied.value.status !== 'draft' && !applied.value.user_id && filters.form_id === applied.value.form_id && !versionsLoading.value && !versionsError.value)
async function loadVersions(formId) {
  const request = ++versionRequest
  versions.value = []
  versionsError.value = ''
  versionsLoading.value = !!formId
  if (!formId) return
  try {
    const data = await listFormVersions(formId)
    if (request === versionRequest) versions.value = data?.data?.versions ?? []
  } catch (error) { if (request === versionRequest) versionsError.value = errorMessage(error) }
  finally { if (request === versionRequest) versionsLoading.value = false }
}
watch(() => filters.form_id, id => { filters.version_id = ''; exportVersionIds.value = []; loadVersions(id) })
onUnmounted(() => { versionRequest++ })
const forms = ref([])
const formsError = ref('')
const exporting = reactive({ busy: false, error: '', message: '' })

async function loadForms() {
  formsError.value = ''
  try {
    const response = await (canManageForms.value ? listAllForms() : listForms())
    forms.value = response?.data?.forms ?? []
  } catch (error) { formsError.value = errorMessage(error) }
}

async function handleExport() {
  exporting.error = ''
  exporting.message = ''
  let params
  try {
    if (!canExport.value) return
    params = buildExportFilters({ ...applied.value, version_ids: exportVersionIds.value, separator: exportSeparator.value })
  } catch (error) {
    exporting.error = error.message
    return
  }
  exporting.busy = true
  try {
    const result = await exportSubmissionsCsv(params)
    exporting.message = `CSV exportado: ${result.rows} registro(s), ${result.bytes} bytes. Período: ${params.date_from || 'sem limite inicial'} a ${params.date_to || 'sem limite final'}.`
  } catch (error) { exporting.error = errorMessage(error) }
  finally { exporting.busy = false }
}
onMounted(() => { load(); loadForms(); loadVersions(filters.form_id) })
</script>

<template>
  <section class="panel panel-forms">
    <div class="panel-header"><div><h1>Submissões</h1><p class="panel-subtitle">Consulte os envios e rascunhos disponíveis para seu perfil.</p></div><button class="ghost-button" type="button" :disabled="state.loading" @click="load">Atualizar</button></div>
    <div v-if="formsError" class="feedback error" role="alert">Não foi possível carregar os formulários. <button class="ghost-button" type="button" @click="loadForms">Tentar novamente</button></div>
    <form class="filter-bar" aria-label="Filtros de submissões" @submit.prevent="apply">
      <label class="field filter-search"><span>Formulário</span><select v-model="filters.form_id"><option value="">Todos os formulários</option><option v-for="form in forms" :key="form.id" :value="String(form.id)">{{ form.name }}</option></select></label>
      <label class="field"><span>Versão</span><select v-model="filters.version_id" :disabled="!filters.form_id || versionsLoading"><option value="">Todas as versões</option><option v-for="version in versions" :key="version.id" :value="String(version.id)">Versão {{ version.version_number }} · {{ version.is_published ? 'Publicada' : 'Em edição' }}</option></select></label>
      <label v-if="canManageForms" class="field"><span>Usuário (ID)</span><input v-model="filters.user_id" type="number" min="1" max="2147483647" step="1" list="submission-authors" placeholder="Todos os usuários" /><datalist id="submission-authors"><option v-for="author in authorOptions" :key="author.id" :value="author.id">{{ author.name }} · {{ author.email }}</option></datalist></label>
      <label class="field"><span>Status</span><select v-model="filters.status"><option value="submitted">Enviadas</option><option value="draft">Rascunhos</option></select></label>
      <label class="field"><span>Data inicial</span><input v-model="filters.date_from" type="date" :max="filters.date_to || '9999-12-31'" /></label>
      <label class="field"><span>Data final</span><input v-model="filters.date_to" type="date" :min="filters.date_from" max="9999-12-31" /></label>
      <div class="filter-actions"><button class="primary-button" type="submit" :disabled="state.loading">Aplicar filtros</button><button class="ghost-button" type="button" :disabled="state.loading" @click="reset">Limpar</button></div>
    </form>
    <p v-if="versionsError" class="feedback error" role="alert">{{ versionsError }} <button class="ghost-button" type="button" @click="loadVersions(filters.form_id)">Recarregar versões</button></p>
    <p class="filter-help">O período considera a data de envio das submissões e a data de criação dos rascunhos.</p>
    <div v-if="canManageForms" class="export-panel">
      <div><strong>Exportar submissões</strong><p class="filter-help">Selecione e aplique um formulário. O CSV inclui os envios das versões selecionadas no período aplicado, em todas as páginas. Sem seleção de versões, inclui todas. Rascunhos não são exportados.</p></div>
      <label class="field"><span>Separador do CSV</span><select v-model="exportSeparator" :disabled="exporting.busy"><option value=",">Vírgula</option><option value=";">Ponto e vírgula</option></select></label>
      <button class="ghost-button" type="button" :disabled="!canExport" @click="handleExport">{{ exporting.busy ? 'Exportando…' : 'Exportar CSV' }}</button>
    </div>
    <fieldset v-if="canManageForms && applied.form_id && filters.form_id === applied.form_id" class="response-options export-versions" :disabled="exporting.busy || versionsLoading">
      <legend>Versões para o CSV</legend>
      <label v-for="version in versions.filter(item => item.is_published)" :key="version.id" class="field-option"><input v-model="exportVersionIds" type="checkbox" :value="String(version.id)" />Versão {{ version.version_number }}</label>
    </fieldset>
    <p v-if="canManageForms && applied.user_id" class="filter-help" role="status">O CSV não suporta filtro por usuário. Limpe esse filtro e aplique a consulta para exportar.</p>
    <p v-if="exporting.error" class="feedback error" role="alert">{{ exporting.error }}</p><p v-if="exporting.message" class="feedback" role="status">{{ exporting.message }}</p>
    <ListContent :loading="state.loading" :error="state.error" :empty="!state.items.length" @retry="load">
      <ul class="fields-list">
        <li v-for="submission in state.items" :key="submission.id" class="field-row">
          <div class="field-row-main"><strong>{{ submission.form?.name || 'Formulário' }}</strong><span class="field-row-meta">#{{ submission.id }} · versão {{ submission.version?.version_number }} · {{ submission.submitted_at ? formatDate(submission.submitted_at) : 'Ainda não enviado' }} · {{ submission.fields_count }} campos</span><span v-if="submission.user" class="field-row-meta">{{ submission.user.name || 'Usuário removido' }} · {{ submission.user.email }} · ID {{ submission.user.id }}</span><span class="status-badge" :class="submission.submitted_at ? 'status-success' : 'status-warning'">{{ submission.submitted_at ? 'Enviada' : 'Rascunho' }}</span></div>
          <RouterLink class="ghost-button" :to="{ path: `/submissions/${submission.id}`, query: { returnTo: route.fullPath } }">Ver detalhes</RouterLink>
        </li>
      </ul>
    </ListContent>
    <ListPagination :pagination="state.pagination" :loading="state.loading" :per-page="perPage" @page="setPage" @per-page="setPerPage" />
  </section>
</template>
