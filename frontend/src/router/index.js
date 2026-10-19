import { createRouter, createWebHistory } from 'vue-router'
import App from '../App.vue'
import LoginView from '../views/LoginView.vue'
import FormDetailView from '../views/FormDetailView.vue'
import FormCreateView from '../views/FormCreateView.vue'
import FormFieldsView from '../views/FormFieldsView.vue'
import FormFillView from '../views/FormFillView.vue'
import AdminTenantsView from '../views/AdminTenantsView.vue'
import TenantUsersView from '../views/TenantUsersView.vue'
import SubmissionsListView from '../views/SubmissionsListView.vue'
import SubmissionDetailView from '../views/SubmissionDetailView.vue'
import AuditLogsListView from '../views/AuditLogsListView.vue'
import AuditLogDetailView from '../views/AuditLogDetailView.vue'
import AccessStateView from '../views/AccessStateView.vue'
import { adminRoles, managementRoles, safeReturnPath } from '../auth/permissions.js'
import { getAuthToken, onTokenChange } from '../auth/token.js'
import { guardRoute } from './guards.js'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { public: true, title: 'Acessar' },
  },
  {
    path: '/',
    name: 'home',
    component: App,
    meta: { title: 'Formulários' },
  },
  {
    path: '/forms/new',
    name: 'form-create',
    component: FormCreateView,
    meta: { title: 'Novo formulário', roles: managementRoles, back: '/' },
  },
  {
    path: '/forms/:formId',
    name: 'form-detail',
    component: FormDetailView,
    meta: { title: 'Detalhe do formulário', back: '/' },
    props: true,
  },
  {
    path: '/forms/:formId/versions/:versionId/fields',
    name: 'form-fields',
    component: FormFieldsView,
    meta: { title: 'Campos da versão', roles: managementRoles, formBack: true },
    props: true,
  },
  {
    path: '/forms/:formId/fill',
    name: 'form-fill',
    component: FormFillView,
    meta: { title: 'Preencher formulário', formBack: true },
    props: true,
  },
  {
    path: '/admin/tenants',
    name: 'admin-tenants',
    component: AdminTenantsView,
    meta: { title: 'Tenants', roles: ['admin-sistema'], back: '/' },
  },
  {
    path: '/admin/users',
    name: 'tenant-users',
    component: TenantUsersView,
    meta: { title: 'Usuários', roles: adminRoles, back: '/' },
  },
  {
    path: '/submissions',
    name: 'submissions-list',
    component: SubmissionsListView,
    meta: { title: 'Submissões' },
  },
  {
    path: '/submissions/:submissionId',
    name: 'submission-detail',
    component: SubmissionDetailView,
    meta: { title: 'Detalhe da submissão', back: '/submissions' },
    props: true,
  },
  {
    path: '/audit-logs',
    name: 'audit-logs-list',
    component: AuditLogsListView,
    meta: { title: 'Auditoria', roles: adminRoles },
  },
  {
    path: '/audit-logs/:logId',
    name: 'audit-log-detail',
    component: AuditLogDetailView,
    meta: { title: 'Detalhe de auditoria', roles: adminRoles, back: '/audit-logs' },
    props: true,
  },
  { path: '/forbidden', name: 'forbidden', component: AccessStateView, meta: { title: 'Acesso restrito' } },
  { path: '/session-error', name: 'session-error', component: AccessStateView, meta: { public: true, title: 'Verificar sessão' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: AccessStateView, meta: { title: 'Página não encontrada' } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(guardRoute)
router.afterEach((to) => {
  document.title = `${to.meta.title || 'Início'} · Form Engine`
})

onTokenChange((reason) => {
  const route = router.currentRoute.value
  if (route.name && !route.meta.public && !getAuthToken()) {
    void router.replace({ name: 'login', query: {
      redirect: safeReturnPath(route.fullPath),
      ...(reason === 'expired' ? { reason: 'expired' } : {}),
    } })
  } else if (reason === 'storage' && route.name) {
    // Recheck identity/permissions when another tab changes the session.
    void router.replace(getAuthToken() ? { name: 'login', query: { redirect: safeReturnPath(route.fullPath) } } : '/login')
  }
})

export default router
