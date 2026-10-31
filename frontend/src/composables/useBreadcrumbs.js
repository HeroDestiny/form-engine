import { onBeforeUnmount, readonly, ref } from 'vue'

const customItems = ref(null)
const liveAnnouncement = ref('')

export const activeBreadcrumbs = readonly(customItems)
export const politeAnnouncement = readonly(liveAnnouncement)

export function setBreadcrumbs(items) {
  customItems.value = Array.isArray(items) && items.length ? items : null
}

export function clearBreadcrumbs() {
  customItems.value = null
}

export function usePageBreadcrumbs(initialItems = null) {
  if (initialItems) setBreadcrumbs(initialItems)
  onBeforeUnmount(() => {
    clearBreadcrumbs()
  })
  return { setBreadcrumbs, clearBreadcrumbs }
}

export function announce(message) {
  liveAnnouncement.value = ''
  queueMicrotask(() => {
    liveAnnouncement.value = message || ''
  })
}

export function resolveBreadcrumbs(route, custom = customItems.value) {
  if (Array.isArray(custom) && custom.length) return custom
  if (!route || route.meta?.public) return []

  const { name, params = {} } = route
  if (name === 'form-create') {
    return [{ label: 'Formulários', to: '/' }, { label: 'Novo formulário' }]
  }
  if (name === 'form-detail') {
    return [{ label: 'Formulários', to: '/' }, { label: `Formulário #${params.formId}` }]
  }
  if (name === 'form-fields') {
    return [
      { label: 'Formulários', to: '/' },
      { label: `Formulário #${params.formId}`, to: `/forms/${params.formId}` },
      { label: 'Campos da versão' },
    ]
  }
  if (name === 'form-fill') {
    return [
      { label: 'Formulários', to: '/' },
      { label: `Formulário #${params.formId}`, to: `/forms/${params.formId}` },
      { label: 'Preencher formulário' },
    ]
  }
  if (name === 'submission-detail') {
    return [
      { label: 'Submissões', to: '/submissions' },
      { label: `Submissão #${params.submissionId}` },
    ]
  }
  if (name === 'audit-log-detail') {
    return [
      { label: 'Auditoria', to: '/audit-logs' },
      { label: `Registro #${params.logId}` },
    ]
  }
  if (name === 'tenant-users') {
    return [{ label: 'Formulários', to: '/' }, { label: 'Usuários' }]
  }
  if (name === 'admin-tenants') {
    return [{ label: 'Formulários', to: '/' }, { label: 'Tenants' }]
  }
  return []
}
