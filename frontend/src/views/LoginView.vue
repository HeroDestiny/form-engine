<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { login } from '../api'

const router = useRouter()

const loginForm = reactive({
  email: '',
  password: '',
  tenantSlug: '',
})

const loading = ref(false)
const errorMessage = ref('')

async function handleLogin() {
  loading.value = true
  errorMessage.value = ''

  try {
    await login({
      email: loginForm.email,
      password: loginForm.password,
      tenantSlug: loginForm.tenantSlug,
    })

    await router.push('/')
  } catch (error) {
    errorMessage.value = error?.body?.message || 'Falha ao autenticar. Verifique os dados.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <div class="brand">
        <span class="brand-mark">FE</span>
        <div class="brand-text">
          <h1>Form Engine</h1>
          <p>Frontend · Vue 3 SPA</p>
        </div>
      </div>
    </header>

    <main class="app-main">
      <section class="panel panel-login">
        <h2>Acessar</h2>
        <p class="panel-subtitle">Use as credenciais já cadastradas no backend.</p>

        <form class="form" @submit.prevent="handleLogin">
          <label class="field">
            <span>E-mail</span>
            <input v-model="loginForm.email" type="email" required placeholder="test@example.com" />
          </label>

          <label class="field">
            <span>Senha</span>
            <input v-model="loginForm.password" type="password" required placeholder="password" />
          </label>

          <label class="field">
            <span>Tenant (slug) <small>(opcional)</small></span>
            <input
              v-model="loginForm.tenantSlug"
              type="text"
              placeholder="prefeitura-demo"
              autocomplete="off"
            />
          </label>

          <p v-if="errorMessage" class="feedback error">{{ errorMessage }}</p>

          <button class="primary-button" type="submit" :disabled="loading">
            <span v-if="!loading">Entrar</span>
            <span v-else>Autenticando…</span>
          </button>
        </form>
      </section>
    </main>
  </div>
</template>
