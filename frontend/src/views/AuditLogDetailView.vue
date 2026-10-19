<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auditEntityPath } from '../utils/auditEntity.js'
import { getAuditLog } from '../api'

const route = useRoute()
const router = useRouter()

const logId = computed(() => route.params.logId)

const state = reactive({
  loading: false,
  error: '',
  payload: null,
})

async function loadLog() {
  state.loading = true
  state.error = ''

  try {
    const data = await getAuditLog(logId.value)
    state.payload = data?.data ?? null
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao carregar registro de auditoria.'
  } finally {
    state.loading = false
  }
}

onMounted(() => {
  if (!logId.value) {
    router.replace('/audit-logs')
    return
  }
  loadLog()
})
</script>

<template>
  <section class="panel panel-forms">
    <template v-if="state.loading">
      <p class="panel-subtitle">Carregando registro…</p>
    </template>

    <template v-else-if="state.error">
      <p class="feedback error" role="alert">{{ state.error }}</p>
    </template>

    <template v-else-if="state.payload">
      <h1>{{ state.payload.log.action }}</h1>
      <p class="panel-subtitle">
        {{ state.payload.log.entity_type }}#{{ state.payload.log.entity_id }} · {{ state.payload.log.created_at }}
      </p>

      <p class="panel-subtitle">IP: {{ state.payload.log.ip_address || 'Não registrado' }} · Agente de navegação: {{ state.payload.log.user_agent || 'Não registrado' }}</p>
      <RouterLink v-if="auditEntityPath(state.payload)" class="ghost-button" :to="{ path: auditEntityPath(state.payload), query: { returnTo: route.fullPath } }">Abrir entidade relacionada</RouterLink>
      <div class="form-card" style="margin-top: 1rem;">
        <h3>Usuário</h3>
        <p v-if="!state.payload.user" class="panel-subtitle">Sem usuário associado.</p>
        <p v-else class="panel-subtitle">
          {{ state.payload.user.name }} ({{ state.payload.user.email }}) · {{ state.payload.user.role }} ·
          {{ state.payload.user.is_active ? 'ativo' : 'inativo' }}
        </p>
      </div>

      <div class="form-card" style="margin-top: 1rem;">
        <h3>Metadata</h3>
        <pre style="font-size: 0.8rem; white-space: pre-wrap;">{{ JSON.stringify(state.payload.log.metadata, null, 2) }}</pre>
      </div>

      <p class="panel-subtitle" style="margin-top: 1rem;">
        Entidade ainda existe? {{ state.payload.entity_exists ? 'sim' : 'não' }} · Ações relacionadas:
        {{ state.payload.related_actions_count }}
      </p>
    </template>

    <template v-else>
      <p class="panel-subtitle">Registro não encontrado.</p>
    </template>
  </section>
</template>
