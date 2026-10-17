<script setup>
import { onMounted, reactive } from 'vue'
import { useQueryListing } from '../composables/useQueryListing.js'
import ListContent from '../components/ListContent.vue'
import ListPagination from '../components/ListPagination.vue'
import { adminListTenants, adminCreateTenant, adminUpdateTenantStatus } from '../api'


const state = reactive({
  error: '',
  message: '',
  saving: false,
})

const form = reactive({
  name: '',
  slug: '',
})

const { state: listing, perPage, load: loadTenants, setPage, setPerPage } = useQueryListing(
  adminListTenants, 'tenants',
)

async function handleCreateTenant() {
  state.saving = true
  state.error = ''
  state.message = ''
  try {
    await adminCreateTenant({ name: form.name, slug: form.slug })
    form.name = ''
    form.slug = ''
    state.message = 'Tenant criado com sucesso.'
    await loadTenants()
    if (listing.pagination) await setPage(listing.pagination.last_page)
  } catch (error) {
    if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      state.error = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      state.error = error?.body?.message || 'Erro ao criar tenant.'
    }
  } finally {
    state.saving = false
  }
}

async function toggleTenantStatus(tenant) {
  if (!window.confirm(`${tenant.is_active ? 'Desativar' : 'Ativar'} o tenant ${tenant.name}? A desativação bloqueia o acesso de todos os seus usuários, preservando os dados.`)) return
  state.error = ''
  state.message = ''
  state.saving = true
  try {
    await adminUpdateTenantStatus(tenant.id, !tenant.is_active)
    await loadTenants()
  } catch (error) {
    state.error = error?.body?.message || 'Erro ao alterar status do tenant.'
  } finally {
    state.saving = false
  }
}

onMounted(loadTenants)
</script>

<template>
  <section class="panel panel-forms">
    <h1>Tenants</h1>
    <p class="panel-subtitle">Criação e ativação/desativação de tenants (admin-sistema).</p>

    <p v-if="state.message" class="feedback" role="status">{{ state.message }}</p>
    <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>

    <div class="forms-grid">
      <div class="form-card">
        <h3>Lista</h3>
        <ListContent :loading="listing.loading" :error="listing.error" :empty="!listing.items.length" empty-message="Nenhum tenant cadastrado." @retry="loadTenants">
          <ul class="fields-list">
            <li v-for="t in listing.items" :key="t.id" class="field-row">
              <div class="field-row-main">
                <strong>{{ t.name }}</strong>
                <span class="field-row-meta">
                  slug: {{ t.slug }} · {{ t.is_active ? 'ativo' : 'inativo' }}
                </span>
              </div>
              <button class="ghost-button" type="button" :disabled="state.saving || listing.loading" @click="toggleTenantStatus(t)">
                {{ t.is_active ? 'Desativar' : 'Ativar' }}
              </button>
            </li>
          </ul>
        </ListContent>
        <ListPagination :pagination="listing.pagination" :loading="listing.loading" :per-page="perPage" @page="setPage" @per-page="setPerPage" />
      </div>

      <div class="form-card">
        <h3>Novo tenant</h3>
        <form class="form" @submit.prevent="handleCreateTenant">
          <label class="field">
            <span>Nome</span>
            <input v-model="form.name" type="text" required minlength="3" maxlength="255" />
          </label>
          <label class="field">
            <span>Slug</span>
            <input
              v-model="form.slug"
              type="text"
              required
              pattern="^[a-z0-9-]+$"
              placeholder="ex: prefeitura-demo"
            />
          </label>
          <button class="primary-button" type="submit" :disabled="state.saving || listing.loading">
            <span v-if="!state.saving">Criar tenant</span>
            <span v-else>Salvando…</span>
          </button>
        </form>
      </div>
    </div>
  </section>
</template>
