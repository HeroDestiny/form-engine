<script setup>
import { useRoute } from 'vue-router'
import { useQueryListing } from '../composables/useQueryListing.js'
import { onMounted } from 'vue'
import { listAuditLogs } from '../api'
import { validatePeriod } from '../composables/useListing.js'
import { formatDate } from '../utils/display.js'
import ListContent from '../components/ListContent.vue'
import ListPagination from '../components/ListPagination.vue'

const route = useRoute()
const { state, filters, perPage, load, apply, reset, setPage, setPerPage } = useQueryListing(
  listAuditLogs, 'logs', { action: '', user_id: '', entity_type: '', entity_id: '', date_from: '', date_to: '' }, { validate: validatePeriod },
)
onMounted(load)
</script>

<template>
  <section class="panel panel-forms">
    <div class="panel-header"><div><h1>Registros de auditoria</h1><p class="panel-subtitle">Consulte as ações registradas na sua organização.</p></div><button class="ghost-button" type="button" :disabled="state.loading" @click="load">Atualizar</button></div>
    <form class="filter-bar audit-filters" aria-label="Filtros de auditoria" @submit.prevent="apply">
      <label class="field"><span>Ação</span><input v-model="filters.action" type="text" maxlength="100" placeholder="Ex.: LOGIN_SUCCESS" /></label>
      <label class="field"><span>ID do usuário</span><input v-model="filters.user_id" type="number" min="1" max="2147483647" step="1" /></label>
      <label class="field"><span>Tipo de entidade</span><input v-model="filters.entity_type" type="text" maxlength="100" placeholder="Ex.: form" /></label>
      <label class="field"><span>ID da entidade</span><input v-model="filters.entity_id" type="number" min="1" max="2147483647" step="1" /></label>
      <label class="field"><span>Data inicial</span><input v-model="filters.date_from" type="date" :max="filters.date_to || '9999-12-31'" /></label>
      <label class="field"><span>Data final</span><input v-model="filters.date_to" type="date" :min="filters.date_from" max="9999-12-31" /></label>
      <div class="filter-actions"><button class="primary-button" type="submit" :disabled="state.loading">Aplicar filtros</button><button class="ghost-button" type="button" :disabled="state.loading" @click="reset">Limpar</button></div>
    </form>
    <p class="filter-help">Ação e entidade usam correspondência exata. O período considera dias completos em UTC.</p>
    <ListContent :loading="state.loading" :error="state.error" :empty="!state.items.length" @retry="load">
      <ul class="fields-list">
        <li v-for="log in state.items" :key="log.id" class="field-row">
          <div class="field-row-main"><strong>{{ log.action }}</strong><span class="field-row-meta">{{ log.entity_type }} #{{ log.entity_id ?? '—' }} · {{ formatDate(log.created_at) }} · {{ log.user?.name || 'Sistema' }}</span></div>
          <RouterLink class="ghost-button" :to="{ path: `/audit-logs/${log.id}`, query: { returnTo: route.fullPath } }">Ver detalhes</RouterLink>
        </li>
      </ul>
    </ListContent>
    <ListPagination :pagination="state.pagination" :loading="state.loading" :per-page="perPage" @page="setPage" @per-page="setPerPage" />
  </section>
</template>
