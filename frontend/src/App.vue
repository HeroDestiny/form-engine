<script setup>
import { useRoute } from 'vue-router'
import { computed, onMounted, ref } from 'vue'
import { listForms, listAllForms } from './api'
import { canManageForms } from './auth/session.js'
import { useQueryListing } from './composables/useQueryListing.js'
import { versionStatus } from './utils/display.js'
import ListContent from './components/ListContent.vue'
import StatusBadge from './components/StatusBadge.vue'
import AppIcon from './components/AppIcon.vue'

const route = useRoute()
const displayMode = ref('grid')

const { state, filters, applied, load, apply, reset } = useQueryListing(
  (params, options) => (canManageForms.value ? listAllForms : listForms)(params, options),
  'forms',
  { search: '', sort: 'name', order: 'asc' },
  { paginated: false },
)

const hasActiveFilters = computed(
  () => !!applied.value.search || applied.value.sort !== 'name' || applied.value.order !== 'asc',
)

const publishedActiveCount = computed(
  () => state.items.filter(item => item.is_active && item.latest_version?.is_published).length,
)
const draftCount = computed(
  () => state.items.filter(item => item.latest_version && !item.latest_version.is_published).length,
)

function versionTone(version) {
  if (!version) return 'muted'
  return version.is_published ? 'success' : 'warning'
}

onMounted(load)
</script>

<template>
  <div class="page-stack">
    <!-- Cabeçalho da Página -->
    <div class="page-header">
      <div>
        <h1>Catálogo de Formulários</h1>
        <p class="panel-subtitle">
          {{ canManageForms ? 'Estruture, versione e acompanhe os formulários institucionais da unidade.' : 'Selecione um formulário publicado para iniciar ou retomar seu preenchimento.' }}
        </p>
      </div>
      <div class="panel-actions">
        <button class="ghost-button" type="button" :disabled="state.loading" @click="load">
          <AppIcon name="refresh" :size="16" />
          <span>Atualizar</span>
        </button>
        <RouterLink v-if="canManageForms" class="primary-button" to="/forms/new">
          <AppIcon name="plus" :size="16" />
          <span>Novo formulário</span>
        </RouterLink>
      </div>
    </div>

    <!-- Faixa de Indicadores Executivos (KPIs) -->
    <div v-if="!state.loading && !state.error" class="kpi-grid" aria-label="Resumo do catálogo">
      <div class="kpi-card">
        <span class="kpi-icon"><AppIcon name="forms" :size="20" /></span>
        <div class="kpi-body">
          <span class="kpi-label">Formulários listados</span>
          <strong class="kpi-value">{{ state.total }}</strong>
        </div>
      </div>

      <div class="kpi-card">
        <span class="kpi-icon success"><AppIcon name="check" :size="20" /></span>
        <div class="kpi-body">
          <span class="kpi-label">Prontos para preenchimento</span>
          <strong class="kpi-value">{{ publishedActiveCount }}</strong>
        </div>
      </div>

      <div v-if="canManageForms" class="kpi-card">
        <span class="kpi-icon warning"><AppIcon name="clock" :size="20" /></span>
        <div class="kpi-body">
          <span class="kpi-label">Versões em edição</span>
          <strong class="kpi-value">{{ draftCount }}</strong>
        </div>
      </div>
    </div>

    <!-- Barra de Ferramentas e Filtros -->
    <div class="toolbar-card">
      <form class="filter-bar" aria-label="Filtros de formulários" @submit.prevent="apply">
        <div class="field filter-search">
          <label for="forms-search">Buscar por nome</label>
          <div class="input-with-icon">
            <span class="input-leading-icon"><AppIcon name="search" :size="16" /></span>
            <input
              id="forms-search"
              v-model="filters.search"
              type="search"
              maxlength="255"
              placeholder="Pesquisar formulário pelo título…"
            />
          </div>
        </div>

        <div class="field">
          <label for="forms-sort">Ordenar por</label>
          <select id="forms-sort" v-model="filters.sort">
            <option value="name">Nome</option>
            <option value="created_at">Data de criação</option>
          </select>
        </div>

        <div class="field">
          <label for="forms-order">Ordem</label>
          <select id="forms-order" v-model="filters.order">
            <option value="asc">Crescente (A–Z)</option>
            <option value="desc">Decrescente (Z–A)</option>
          </select>
        </div>

        <div class="filter-actions">
          <button class="primary-button" type="submit" :disabled="state.loading">Buscar</button>
          <button class="ghost-button" type="button" :disabled="state.loading" @click="reset">Limpar</button>
        </div>
      </form>

      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <p v-if="!state.loading && !state.error" class="result-summary" role="status" aria-live="polite">
          Exibindo <strong>{{ state.total }}</strong> formulário(s){{ applied.search ? ` para “${applied.search}”` : '' }}
        </p>

        <div class="view-switcher" role="group" aria-label="Formato de exibição do catálogo">
          <button
            type="button"
            :aria-pressed="displayMode === 'grid'"
            @click="displayMode = 'grid'"
          >
            <AppIcon name="grid" :size="15" />
            <span style="margin-left: 0.35rem;">Grade</span>
          </button>
          <button
            type="button"
            :aria-pressed="displayMode === 'table'"
            @click="displayMode = 'table'"
          >
            <AppIcon name="table" :size="15" />
            <span style="margin-left: 0.35rem;">Tabela</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Conteúdo do Catálogo -->
    <ListContent
      :variant="displayMode === 'grid' ? 'cards' : 'rows'"
      :skeleton-count="6"
      :loading="state.loading"
      :error="state.error"
      :empty="!state.items.length"
      :can-clear-filters="hasActiveFilters"
      :empty-message="applied.search ? 'Nenhum formulário corresponde à busca. Tente outro termo ou limpe os filtros.' : 'Nenhum formulário disponível no momento.'"
      @retry="load"
      @clear="reset"
    >
      <!-- Modo Grade de Cartões Executivos -->
      <div v-if="displayMode === 'grid'" class="forms-grid">
        <article
          v-for="form in state.items"
          :key="form.id"
          class="catalog-card"
          :class="{ 'is-active': form.is_active }"
        >
          <div class="catalog-card-top">
            <div class="catalog-card-title-row">
              <h2 class="catalog-card-title">
                <RouterLink :to="{ path: `/forms/${form.id}`, query: { returnTo: route.fullPath } }">
                  {{ form.name }}
                </RouterLink>
              </h2>
              <StatusBadge
                :tone="form.is_active ? 'success' : 'muted'"
                :label="form.is_active ? 'Ativo' : 'Inativo'"
              />
            </div>

            <p class="catalog-card-desc">{{ form.description || 'Sem descrição cadastrada para este formulário.' }}</p>

            <div class="form-meta">
              <StatusBadge
                :tone="versionTone(form.latest_version)"
                :label="versionStatus(form.latest_version)"
              />
              <span v-if="form.latest_version" class="tabular">
                v{{ form.latest_version.version_number }} · {{ form.latest_version.fields_count ?? 0 }} campo(s)
              </span>
            </div>
          </div>

          <div class="catalog-card-footer">
            <RouterLink
              v-if="form.is_active && form.latest_version?.is_published"
              class="primary-button"
              :to="{ path: `/forms/${form.id}/fill`, query: { returnTo: route.fullPath } }"
            >
              <span>Preencher</span>
              <AppIcon name="arrow-right" :size="15" />
            </RouterLink>
            <span v-else class="field-hint">Indisponível para envio</span>

            <RouterLink
              class="ghost-button"
              :to="{ path: `/forms/${form.id}`, query: { returnTo: route.fullPath } }"
            >
              Detalhes
            </RouterLink>
          </div>
        </article>
      </div>

      <!-- Modo Tabela Compacta -->
      <div v-else class="table-card">
        <div class="data-table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Formulário</th>
                <th scope="col">Disponibilidade</th>
                <th scope="col">Última Versão</th>
                <th scope="col">Campos</th>
                <th scope="col" class="col-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="form in state.items" :key="form.id">
                <td data-label="Formulário">
                  <div class="cell-stack">
                    <strong>{{ form.name }}</strong>
                    <small>{{ form.description || 'Sem descrição cadastrada.' }}</small>
                  </div>
                </td>
                <td data-label="Disponibilidade">
                  <StatusBadge
                    :tone="form.is_active ? 'success' : 'muted'"
                    :label="form.is_active ? 'Ativo' : 'Inativo'"
                  />
                </td>
                <td data-label="Última Versão">
                  <StatusBadge
                    :tone="versionTone(form.latest_version)"
                    :label="form.latest_version ? `v${form.latest_version.version_number} · ${versionStatus(form.latest_version)}` : 'Sem versão'"
                  />
                </td>
                <td data-label="Campos" class="tabular">
                  {{ form.latest_version?.fields_count ?? 0 }}
                </td>
                <td data-label="Ações" class="col-right">
                  <div class="panel-actions" style="justify-content: flex-end;">
                    <RouterLink
                      v-if="form.is_active && form.latest_version?.is_published"
                      class="primary-button"
                      :to="{ path: `/forms/${form.id}/fill`, query: { returnTo: route.fullPath } }"
                    >
                      Preencher
                    </RouterLink>
                    <RouterLink
                      class="ghost-button"
                      :to="{ path: `/forms/${form.id}`, query: { returnTo: route.fullPath } }"
                    >
                      Detalhes
                    </RouterLink>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </ListContent>
  </div>
</template>
