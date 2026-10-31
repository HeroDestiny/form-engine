<script setup>
import { computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auditEntityPath } from '../utils/auditEntity.js'
import { formatDate } from '../utils/display.js'
import { getAuditLog } from '../api'
import { usePageBreadcrumbs } from '../composables/useBreadcrumbs.js'
import { initialsFromName } from '../composables/useTheme.js'
import StatusBadge from '../components/StatusBadge.vue'
import AppIcon from '../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const { setBreadcrumbs } = usePageBreadcrumbs()

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
    if (state.payload?.log) {
      setBreadcrumbs([
        { label: 'Auditoria', to: '/audit-logs' },
        { label: `${state.payload.log.action} (#${logId.value})` },
      ])
    }
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
  <div class="page-stack" :aria-busy="state.loading">
    <template v-if="state.loading">
      <span class="sr-only" role="status">Carregando registro de auditoria…</span>
      <div class="surface-card skeleton-list" aria-hidden="true">
        <div class="skeleton-block w-35" style="height: 1.75rem;" />
        <div class="skeleton-block w-65" />
        <div class="skeleton-card">
          <div class="skeleton-block w-50" />
          <div class="skeleton-block w-80" />
        </div>
      </div>
    </template>

    <section v-else-if="state.error" class="surface-card">
      <p class="feedback error" role="alert">{{ state.error }}</p>
      <button class="ghost-button" type="button" @click="loadLog">Tentar novamente</button>
    </section>

    <template v-else-if="state.payload">
      <div class="page-header">
        <div>
          <div class="card-heading" style="margin-bottom: 0.35rem;">
            <h1><code>{{ state.payload.log.action }}</code></h1>
            <StatusBadge
              :tone="state.payload.entity_exists ? 'success' : 'muted'"
              :label="state.payload.entity_exists ? 'Entidade ativa no banco' : 'Entidade ausente ou sem vínculo'"
            />
          </div>
          <p class="panel-subtitle">Inspeção técnica do evento registrado na trilha de auditoria.</p>
        </div>

        <div v-if="auditEntityPath(state.payload)" class="panel-actions">
          <RouterLink
            class="primary-button"
            :to="{ path: auditEntityPath(state.payload), query: { returnTo: route.fullPath } }"
          >
            <span>Abrir entidade relacionada</span>
            <AppIcon name="arrow-right" :size="15" />
          </RouterLink>
        </div>
      </div>

      <div class="record-meta-grid">
        <div class="record-meta-cell">
          <small>Registro de Auditoria</small>
          <strong class="tabular">#{{ state.payload.log.id }}</strong>
        </div>
        <div class="record-meta-cell">
          <small>Entidade Alvo</small>
          <strong class="tabular">{{ state.payload.log.entity_type }} #{{ state.payload.log.entity_id ?? '—' }}</strong>
        </div>
        <div class="record-meta-cell">
          <small>Data e Hora</small>
          <strong class="tabular">{{ formatDate(state.payload.log.created_at) }}</strong>
        </div>
        <div class="record-meta-cell">
          <small>Endereço IP</small>
          <strong><code>{{ state.payload.log.ip_address || 'Não registrado' }}</code></strong>
        </div>
      </div>

      <div class="detail-grid">
        <section class="surface-card" aria-labelledby="audit-meta-heading">
          <h2 id="audit-meta-heading" style="margin-bottom: 0.85rem;">Metadados auditados (JSON)</h2>
          <pre class="code-block">{{ JSON.stringify(state.payload.log.metadata, null, 2) }}</pre>
        </section>

        <div class="page-stack">
          <section class="surface-card" aria-labelledby="audit-actor-heading">
            <h2 id="audit-actor-heading" style="margin-bottom: 0.75rem;">Ator da ação</h2>
            <p v-if="!state.payload.user" class="panel-subtitle">
              Sem usuário autenticado associado.
            </p>
            <div v-else class="cell-user">
              <span class="avatar-badge" aria-hidden="true">{{ initialsFromName(state.payload.user.name) }}</span>
              <div class="cell-stack">
                <div style="display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap;">
                  <strong>{{ state.payload.user.name }}</strong>
                  <StatusBadge
                    :tone="state.payload.user.is_active ? 'success' : 'muted'"
                    :label="state.payload.user.is_active ? 'Conta ativa' : 'Conta inativa'"
                  />
                </div>
                <small>{{ state.payload.user.email }} · Papel: {{ state.payload.user.role }}</small>
              </div>
            </div>
          </section>

          <section class="surface-card" aria-labelledby="audit-context-heading">
            <h2 id="audit-context-heading" style="margin-bottom: 0.5rem;">Contexto técnico</h2>
            <p class="panel-subtitle" style="margin-bottom: 0.65rem;">
              Eventos correlacionados nesta entidade: <strong class="tabular">{{ state.payload.related_actions_count }}</strong>
            </p>
            <small class="field-hint">
              Navegador / Agente: {{ state.payload.log.user_agent || 'Não registrado' }}
            </small>
          </section>
        </div>
      </div>
    </template>

    <section v-else class="surface-card">
      <p class="panel-subtitle">Registro não encontrado.</p>
    </section>
  </div>
</template>
