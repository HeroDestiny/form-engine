<script setup>
import { useRoute } from 'vue-router'
import { onMounted } from 'vue'
import { listForms, listAllForms } from './api'
import { canManageForms } from './auth/session.js'
import { useQueryListing } from './composables/useQueryListing.js'
import { versionStatus } from './utils/display.js'
import ListContent from './components/ListContent.vue'

const route = useRoute()
const { state, filters, applied, load, apply, reset } = useQueryListing(
  (params, options) => (canManageForms.value ? listAllForms : listForms)(params, options),
  'forms', { search: '', sort: 'name', order: 'asc' }, { paginated: false },
)
onMounted(load)
</script>

<template>
  <section class="panel panel-forms">
    <div class="panel-header">
      <div>
        <h1>Formulários</h1>
        <p class="panel-subtitle">{{ canManageForms ? 'Crie e gerencie os formulários da sua organização.' : 'Escolha um formulário disponível para preencher.' }}</p>
      </div>
      <div class="panel-actions">
        <button class="ghost-button" type="button" :disabled="state.loading" @click="load">Atualizar</button>
        <RouterLink v-if="canManageForms" class="primary-button" to="/forms/new">Novo formulário</RouterLink>
      </div>
    </div>

    <form class="filter-bar" aria-label="Filtros de formulários" @submit.prevent="apply">
      <label class="field filter-search"><span>Buscar por nome</span><input v-model="filters.search" type="search" maxlength="255" placeholder="Nome do formulário" /></label>
      <label class="field"><span>Ordenar por</span><select v-model="filters.sort"><option value="name">Nome</option><option value="created_at">Data de criação</option></select></label>
      <label class="field"><span>Ordem</span><select v-model="filters.order"><option value="asc">Crescente</option><option value="desc">Decrescente</option></select></label>
      <div class="filter-actions">
        <button class="primary-button" type="submit" :disabled="state.loading">Buscar</button>
        <button class="ghost-button" type="button" :disabled="state.loading" @click="reset">Limpar</button>
      </div>
    </form>
    <p v-if="!state.loading && !state.error" class="result-summary" role="status">{{ state.total }} formulário(s){{ applied.search ? ` para “${applied.search}”` : '' }}</p>

    <ListContent :loading="state.loading" :error="state.error" :empty="!state.items.length" :empty-message="applied.search ? 'Nenhum formulário corresponde à busca. Tente outro nome ou limpe os filtros.' : 'Nenhum formulário disponível no momento.'" @retry="load">
      <div class="forms-grid">
        <article v-for="form in state.items" :key="form.id" class="form-card">
          <RouterLink :to="{ path: `/forms/${form.id}`, query: { returnTo: route.fullPath } }">
            <div class="card-heading"><h2>{{ form.name }}</h2><span class="status-badge" :class="form.is_active ? 'status-success' : 'status-muted'">{{ form.is_active ? 'Ativo' : 'Inativo' }}</span></div>
            <p class="form-description">{{ form.description || 'Sem descrição.' }}</p>
            <div class="form-meta">
              <span class="status-badge" :class="form.latest_version?.is_published ? 'status-success' : 'status-warning'">{{ versionStatus(form.latest_version) }}</span>
              <span v-if="form.latest_version">Versão {{ form.latest_version.version_number }} · {{ form.latest_version.fields_count ?? 0 }} campo(s)</span>
            </div>
          </RouterLink>
        </article>
      </div>
    </ListContent>
  </section>
</template>
