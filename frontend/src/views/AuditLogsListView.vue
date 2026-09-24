<script setup>
import { onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { listAuditLogs } from '../api'

const router = useRouter()

const state = reactive({
  loading: false,
  error: '',
  logs: [],
  pagination: null,
})

async function loadLogs() {
  state.loading = true
  state.error = ''
  try {
    const data = await listAuditLogs({ per_page: 50 })
    state.logs = data?.data?.logs ?? []
    state.pagination = data?.data?.pagination ?? null
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar auditoria.'
  } finally {
    state.loading = false
  }
}

onMounted(() => {
  loadLogs()
})
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Auditoria</p>
        </div>
      </div>

      <button class="ghost-button" type="button" @click="router.push('/')">Voltar</button>
    </header>

    <main class="app-main">
      <section class="panel panel-forms">
        <h2>Registros de auditoria</h2>
        <p class="panel-subtitle">Últimas ações registradas no tenant.</p>

        <p v-if="state.error" class="feedback error">{{ state.error }}</p>
        <p v-if="state.loading" class="panel-subtitle">Carregando registros…</p>

        <div v-if="!state.loading && !state.logs.length" class="empty-state">
          <p>Nenhum registro encontrado.</p>
        </div>

        <ul v-else class="fields-list">
          <li v-for="log in state.logs" :key="log.id" class="field-row">
            <div class="field-row-main">
              <strong>{{ log.action }}</strong>
              <span class="field-row-meta">
                {{ log.entity_type }}#{{ log.entity_id }} · {{ log.created_at }} · {{ log.user?.name || 'sistema' }}
              </span>
            </div>
            <button class="ghost-button" type="button" @click="router.push(`/audit-logs/${log.id}`)">
              Ver detalhes
            </button>
          </li>
        </ul>
      </section>
    </main>
  </div>
</template>
