<script setup>
import { useRoute } from 'vue-router'
import { useQueryListing } from '../composables/useQueryListing.js'
import { computed, onMounted, ref } from 'vue'
import { listAuditLogs } from '../api'
import { validatePeriod } from '../composables/useListing.js'
import { formatDate } from '../utils/display.js'
import { initialsFromName } from '../composables/useTheme.js'
import ListContent from '../components/ListContent.vue'
import ListPagination from '../components/ListPagination.vue'
import AppIcon from '../components/AppIcon.vue'

const route = useRoute()
const { state, filters, applied, perPage, load, apply, reset, setPage, setPerPage } = useQueryListing(
  listAuditLogs,
  'logs',
  { action: '', user_id: '', entity_type: '', entity_id: '', date_from: '', date_to: '' },
  { validate: validatePeriod },
)

const showAdvanced = ref(Boolean(
  applied.value.user_id || applied.value.entity_id || applied.value.date_from || applied.value.date_to,
))

const advancedCount = computed(() => (
  [applied.value.user_id, applied.value.entity_id, applied.value.date_from, applied.value.date_to]
    .filter(Boolean).length
))

const hasActiveFilters = computed(() => (
  !!applied.value.action ||
  !!applied.value.user_id ||
  !!applied.value.entity_type ||
  !!applied.value.entity_id ||
  !!applied.value.date_from ||
  !!applied.value.date_to
))

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <div class="page-header">
      <div>
        <h1>Trilha de Auditoria</h1>
        <p class="panel-subtitle">Histórico imutável de autenticações, alterações estruturais e envios na unidade.</p>
      </div>
      <button class="ghost-button" type="button" :disabled="state.loading" @click="load">
        <AppIcon name="refresh" :size="16" />
        <span>Atualizar</span>
      </button>
    </div>

    <div class="toolbar-card">
      <form aria-label="Filtros de auditoria" @submit.prevent="apply">
        <div class="filter-bar">
          <div class="field">
            <label for="audit-action">Código da ação</label>
            <input id="audit-action" v-model="filters.action" type="text" maxlength="100" placeholder="Ex.: LOGIN_SUCCESS" />
          </div>

          <div class="field">
            <label for="audit-entity-type">Tipo de entidade</label>
            <input id="audit-entity-type" v-model="filters.entity_type" type="text" maxlength="100" placeholder="Ex.: form" />
          </div>

          <div class="filter-actions">
            <button
              class="ghost-button"
              type="button"
              :aria-expanded="showAdvanced"
              @click="showAdvanced = !showAdvanced"
            >
              <AppIcon name="filter" :size="15" />
              <span>Avançado{{ advancedCount ? ` (${advancedCount})` : '' }}</span>
            </button>
            <button class="primary-button" type="submit" :disabled="state.loading">Filtrar</button>
            <button class="ghost-button" type="button" :disabled="state.loading" @click="reset">Limpar</button>
          </div>
        </div>

        <div v-if="showAdvanced" class="filter-drawer" style="margin-top: 0.9rem;">
          <div class="field">
            <label for="audit-user-id">ID do ator</label>
            <input id="audit-user-id" v-model="filters.user_id" type="number" min="1" max="2147483647" step="1" placeholder="Ex.: 1" />
          </div>

          <div class="field">
            <label for="audit-entity-id">ID da entidade</label>
            <input id="audit-entity-id" v-model="filters.entity_id" type="number" min="1" max="2147483647" step="1" placeholder="Ex.: 10" />
          </div>

          <div class="field">
            <label for="audit-date-from">Data inicial (UTC)</label>
            <input id="audit-date-from" v-model="filters.date_from" type="date" :max="filters.date_to || '9999-12-31'" />
          </div>

          <div class="field">
            <label for="audit-date-to">Data final (UTC)</label>
            <input id="audit-date-to" v-model="filters.date_to" type="date" :min="filters.date_from" max="9999-12-31" />
          </div>
        </div>
      </form>

      <div v-if="hasActiveFilters" class="filter-chips" aria-label="Filtros aplicados">
        <span v-if="applied.action" class="filter-chip">Ação: {{ applied.action }}</span>
        <span v-if="applied.entity_type" class="filter-chip">Entidade: {{ applied.entity_type }}</span>
        <span v-if="applied.entity_id" class="filter-chip">ID Entidade: #{{ applied.entity_id }}</span>
        <span v-if="applied.user_id" class="filter-chip">Ator: #{{ applied.user_id }}</span>
        <span v-if="applied.date_from || applied.date_to" class="filter-chip">
          Período: {{ applied.date_from || '…' }} até {{ applied.date_to || '…' }}
        </span>
      </div>
    </div>

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
                <th scope="col">ID</th>
                <th scope="col">Evento</th>
                <th scope="col">Entidade Alvo</th>
                <th scope="col">Ator</th>
                <th scope="col">Data / Hora</th>
                <th scope="col" class="col-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in state.items" :key="log.id">
                <td data-label="ID">
                  <span class="mono-badge">#{{ log.id }}</span>
                </td>
                <td data-label="Evento">
                  <code>{{ log.action }}</code>
                </td>
                <td data-label="Entidade Alvo" class="tabular">
                  {{ log.entity_type }} <span class="field-hint">#{{ log.entity_id ?? '—' }}</span>
                </td>
                <td data-label="Ator">
                  <div v-if="log.user" class="cell-user">
                    <span class="avatar-badge sm" aria-hidden="true">{{ initialsFromName(log.user.name) }}</span>
                    <div class="cell-stack">
                      <strong>{{ log.user.name }}</strong>
                      <small>{{ log.user.email }}</small>
                    </div>
                  </div>
                  <span v-else class="field-hint">Sistema / Não autenticado</span>
                </td>
                <td data-label="Data / Hora" class="tabular">
                  {{ formatDate(log.created_at) }}
                </td>
                <td data-label="Ações" class="col-right">
                  <RouterLink
                    class="ghost-button"
                    :to="{ path: `/audit-logs/${log.id}`, query: { returnTo: route.fullPath } }"
                  >
                    Inspecionar
                  </RouterLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </ListContent>

      <ListPagination
        select-id="audit-per-page"
        :pagination="state.pagination"
        :loading="state.loading"
        :per-page="perPage"
        @page="setPage"
        @per-page="setPerPage"
      />
    </div>
  </div>
</template>
