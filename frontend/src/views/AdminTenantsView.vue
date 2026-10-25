<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useQueryListing } from '../composables/useQueryListing.js'
import { pushToast } from '../composables/useToast.js'
import ListContent from '../components/ListContent.vue'
import ListPagination from '../components/ListPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import AppIcon from '../components/AppIcon.vue'
import { adminListTenants, adminCreateTenant, adminUpdateTenantStatus } from '../api'

const createModalOpen = ref(false)
const createFormRef = ref(null)

const state = reactive({
  error: '',
  createError: '',
  saving: false,
  confirmTenant: null,
})

const form = reactive({
  name: '',
  slug: '',
})

const { state: listing, perPage, load: loadTenants, setPage, setPerPage } = useQueryListing(
  adminListTenants,
  'tenants',
)

function openCreateModal() {
  state.createError = ''
  createModalOpen.value = true
}

function submitCreateModal() {
  if (createFormRef.value && !createFormRef.value.reportValidity()) return
  void handleCreateTenant()
}

async function handleCreateTenant() {
  state.saving = true
  state.createError = ''
  try {
    await adminCreateTenant({ name: form.name, slug: form.slug })
    form.name = ''
    form.slug = ''
    createModalOpen.value = false
    pushToast('Unidade administrativa (Tenant) criada com sucesso.')
    await loadTenants()
    if (listing.pagination) await setPage(listing.pagination.last_page)
  } catch (error) {
    if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      state.createError = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      state.createError = error?.body?.message || 'Erro ao criar tenant.'
    }
  } finally {
    state.saving = false
  }
}

async function confirmToggleTenantStatus() {
  const tenant = state.confirmTenant
  if (!tenant) return
  state.error = ''
  state.saving = true
  try {
    const nextActive = !tenant.is_active
    await adminUpdateTenantStatus(tenant.id, nextActive)
    state.confirmTenant = null
    pushToast(`Tenant “${tenant.name}” ${nextActive ? 'ativado' : 'desativado'} com sucesso.`)
    await loadTenants()
  } catch (error) {
    state.confirmTenant = null
    state.error = error?.body?.message || 'Erro ao alterar status do tenant.'
  } finally {
    state.saving = false
  }
}

onMounted(loadTenants)
</script>

<template>
  <div class="page-stack">
    <div class="page-header">
      <div>
        <h1>Unidades Administrativas (Tenants)</h1>
        <p class="panel-subtitle">Governança global de ambientes isolados (exclusivo para <code>admin-sistema</code>).</p>
      </div>
      <div class="panel-actions">
        <button class="ghost-button" type="button" :disabled="listing.loading" @click="loadTenants">
          <AppIcon name="refresh" :size="16" />
          <span>Atualizar</span>
        </button>
        <button class="primary-button" type="button" @click="openCreateModal">
          <AppIcon name="plus" :size="16" />
          <span>Nova unidade</span>
        </button>
      </div>
    </div>

    <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>

    <div class="table-card">
      <ListContent
        :loading="listing.loading"
        :error="listing.error"
        :empty="!listing.items.length"
        empty-message="Nenhum tenant cadastrado."
        @retry="loadTenants"
      >
        <div class="data-table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Unidade Administrativa</th>
                <th scope="col">Slug Técnico</th>
                <th scope="col">Status</th>
                <th scope="col" class="col-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in listing.items" :key="t.id">
                <td data-label="ID">
                  <span class="mono-badge">#{{ t.id }}</span>
                </td>
                <td data-label="Unidade Administrativa">
                  <div class="cell-user">
                    <span class="avatar-badge sm" aria-hidden="true"><AppIcon name="building" :size="14" /></span>
                    <strong>{{ t.name }}</strong>
                  </div>
                </td>
                <td data-label="Slug Técnico">
                  <code>{{ t.slug }}</code>
                </td>
                <td data-label="Status">
                  <StatusBadge
                    :tone="t.is_active ? 'success' : 'muted'"
                    :label="t.is_active ? 'Ativo' : 'Inativo'"
                  />
                </td>
                <td data-label="Ações" class="col-right">
                  <button
                    class="ghost-button"
                    :class="{ 'ghost-danger': t.is_active }"
                    type="button"
                    :disabled="state.saving || listing.loading"
                    :aria-label="`${t.is_active ? 'Desativar' : 'Ativar'} tenant ${t.name}`"
                    @click="state.confirmTenant = t"
                  >
                    {{ t.is_active ? 'Desativar' : 'Ativar' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </ListContent>

      <ListPagination
        select-id="tenants-per-page"
        :pagination="listing.pagination"
        :loading="listing.loading"
        :per-page="perPage"
        @page="setPage"
        @per-page="setPerPage"
      />
    </div>

    <!-- Modal de Criação de Tenant -->
    <ConfirmDialog
      :open="createModalOpen"
      dialog-id="create-tenant-modal"
      title="Criar nova unidade administrativa"
      description="Cada unidade possui catálogo de formulários, usuários e submissões totalmente isolados."
      :confirm-label="state.saving ? 'Salvando…' : 'Criar unidade'"
      :busy="state.saving"
      @confirm="submitCreateModal"
      @cancel="createModalOpen = false"
    >
      <form ref="createFormRef" class="form" style="margin-top: 0.75rem;" @submit.prevent="handleCreateTenant">
        <div class="field">
          <label for="tenant-name">
            <span>Nome da unidade</span>
            <span class="required-indicator" aria-hidden="true">*</span>
          </label>
          <input
            id="tenant-name"
            v-model="form.name"
            type="text"
            required
            minlength="3"
            maxlength="255"
            placeholder="Ex.: Secretaria Municipal de Saúde"
          />
        </div>

        <div class="field">
          <label for="tenant-slug">
            <span>Slug identificador</span>
            <span class="required-indicator" aria-hidden="true">*</span>
          </label>
          <input
            id="tenant-slug"
            v-model="form.slug"
            type="text"
            required
            pattern="^[a-z0-9-]+$"
            placeholder="ex.: secretaria-saude"
            aria-describedby="tenant-slug-hint"
          />
          <small id="tenant-slug-hint" class="field-hint">
            Use apenas letras minúsculas sem acento, números e hífen (<code>-</code>).
          </small>
        </div>

        <p v-if="state.createError" class="feedback error" role="alert" style="margin-bottom: 0;">
          {{ state.createError }}
        </p>
      </form>
    </ConfirmDialog>

    <!-- Modal de Ativação/Desativação de Tenant -->
    <ConfirmDialog
      :open="!!state.confirmTenant"
      dialog-id="toggle-tenant-confirm"
      :title="state.confirmTenant?.is_active ? `Desativar o tenant “${state.confirmTenant.name}”?` : `Ativar o tenant “${state.confirmTenant?.name || ''}”?`"
      :description="state.confirmTenant?.is_active
        ? 'A desativação bloqueia imediatamente o acesso às operações de dados para todos os usuários desta unidade, preservando integralmente seus formulários e envios.'
        : 'Os usuários ativos desta unidade voltarão a ter acesso normal ao sistema.'"
      :confirm-label="state.saving ? 'Atualizando…' : (state.confirmTenant?.is_active ? 'Desativar tenant' : 'Ativar tenant')"
      :tone="state.confirmTenant?.is_active ? 'danger' : 'primary'"
      :busy="state.saving"
      @confirm="confirmToggleTenantStatus"
      @cancel="state.confirmTenant = null"
    />
  </div>
</template>
