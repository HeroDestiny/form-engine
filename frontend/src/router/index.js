import { createRouter, createWebHistory } from 'vue-router'
import App from '../App.vue'
import LoginView from '../views/LoginView.vue'
import FormDetailView from '../views/FormDetailView.vue'
import FormCreateView from '../views/FormCreateView.vue'
import FormFieldsView from '../views/FormFieldsView.vue'
import FormFillView from '../views/FormFillView.vue'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
  },
  {
    path: '/',
    name: 'home',
    component: App,
  },
  {
    path: '/forms/new',
    name: 'form-create',
    component: FormCreateView,
  },
  {
    path: '/forms/:formId',
    name: 'form-detail',
    component: FormDetailView,
    props: true,
  },
  {
    path: '/forms/:formId/versions/:versionId/fields',
    name: 'form-fields',
    component: FormFieldsView,
    props: true,
  },
  {
    path: '/forms/:formId/fill',
    name: 'form-fill',
    component: FormFillView,
    props: true,
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

export default router
