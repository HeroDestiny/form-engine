<script setup>
import { onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { listSubmissions, exportSubmissionsCsv } from '../api'

const router = useRouter()

const state = reactive({
  loading: false,
  error: '',
  submissions: [],
  pagination: null,
  exporting: false,
})

async function loadSubmissions() {
  state.loading = true
  state.error = ''

  try {
    const data = await listSubmissions({ per_page: 50 })
    state.submissions = data?.data?.submissions ?? []
    state.pagination = data?.data?.pagination ?? null
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar submissões.'
  } finally {
    state.loading = false
  }
}

async function handleExport() {
  state.exporting = true
  state.error = ''
  try {
    // exporta todas as submissões do tenant; backend exige form_id, então usamos o primeiro da lista como atalho
    const first = state.submissions[0]
    if (!first) {
      state.error = 'Não há submissões para exportar.'
      return
    }
    await exportSubmissionsCsv({ form_id: first.form.id })
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao exportar submissões.'
  } finally {
    state.exporting = false
  }
}

onMounted(() => {
  loadSubmissions()
})
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Submissões</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="router.push('/')">Voltar</button>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <div class="panel-header">
          <div>
            <h2>Submissões</h2>
            <p class="panel-subtitle">Lista de submissões do tenant (apenas suas, se papel = user).</p>
          </div>
          <button class="ghost-button" type="button" :disabled="state.exporting" @click="handleExport">
            <span v-if="!state.exporting">Exportar CSV</span>
            <span v-else>Exportando…</span>
          </button>
        </div>

        <p v-if="state.error" class="feedback error">{{ state.error }}</p>
        <p v-if="state.loading" class="panel-subtitle">Carregando submissões…</p>

        <div v-if="!state.loading && !state.submissions.length" class="empty-state">
          <p>Nenhuma submissão encontrada.</p>
        </div>

        <ul v-else class="fields-list">
          <li v-for="s in state.submissions" :key="s.id" class="field-row">
            <div class="field-row-main">
              <strong>{{ s.form.name }}</strong>
              <span class="field-row-meta">
                #{{ s.id }} · versão {{ s.version.version_number }} · {{ s.submitted_at }} · {{ s.fields_count }} campos
              </span>
            </div>
            <button class="ghost-button" type="button" @click="router.push(`/submissions/${s.id}`)">
              Ver detalhes
            </button>
          </li>
        </ul>
      </section>
    </main>
  </div>
</template>
