<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { signIn } from '../auth/session.js'
import { safeReturnPath } from '../auth/permissions.js'
import AppIcon from '../components/AppIcon.vue'

const router = useRouter()
const route = useRoute()
const isDev = Boolean(import.meta.env?.DEV)

const loginForm = reactive({
  email: '',
  password: '',
  tenantSlug: '',
})

const showPassword = ref(false)
const loading = ref(false)
const errorMessage = ref('')

function fillDemoAccount(role) {
  loginForm.email = role === 'system' ? 'admin-sistema@formengine.local' : 'admin@formengine.local'
  loginForm.password = 'FormEngineLocal123!'
  loginForm.tenantSlug = 'demo'
  errorMessage.value = ''
}

async function handleLogin() {
  loading.value = true
  errorMessage.value = ''

  try {
    const user = await signIn({
      email: loginForm.email,
      password: loginForm.password,
      tenantSlug: loginForm.tenantSlug,
    })

    if (!user) throw new Error('Sua sessão não está disponível. Entre novamente.')
    await router.replace(safeReturnPath(route.query.redirect))
  } catch (error) {
    errorMessage.value = Object.values(error?.body?.errors || {})[0]?.[0] || (
      error.status
        ? error.message
        : 'Não foi possível acessar sua conta. Verifique sua conexão e tente novamente.'
    )
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-split">
    <!-- Coluna Institucional Executiva -->
    <section class="auth-hero" aria-label="Apresentação institucional">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true">FE</span>
        <span class="brand-text">
          <strong>Form Engine</strong>
          <span>Plataforma Executiva de Formulários</span>
        </span>
      </div>

      <div class="auth-hero-copy">
        <span class="auth-eyebrow">
          <AppIcon name="shield" :size="15" />
          <span>Governança, Rastreabilidade & Precisão</span>
        </span>
        <h1>Estruture, versione e colete dados institucionais com segurança.</h1>
        <p>
          Projetado para equipes que exigem controle rigoroso de versões, isolamento por unidade administrativa e exportação confiável.
        </p>

        <ul class="auth-pillars">
          <li>
            <span class="auth-pillar-icon"><AppIcon name="layers" :size="18" /></span>
            <div>
              <strong>Versionamento imutável</strong>
              <span>Evolua fichas cadastrais preservando integralmente o histórico de envios anteriores.</span>
            </div>
          </li>
          <li>
            <span class="auth-pillar-icon"><AppIcon name="building" :size="18" /></span>
            <div>
              <strong>Arquitetura Multi-Tenant</strong>
              <span>Operação isolada por secretaria, diretoria ou unidade organizacional.</span>
            </div>
          </li>
          <li>
            <span class="auth-pillar-icon"><AppIcon name="download" :size="18" /></span>
            <div>
              <strong>Auditoria e exportação estruturada</strong>
              <span>Registro de eventos críticos e extração CSV protegida contra injeção de fórmulas.</span>
            </div>
          </li>
        </ul>
      </div>

      <div class="auth-hero-foot">
        Conformidade com padrões de acessibilidade WCAG 2.2 AA
      </div>
    </section>

    <!-- Coluna do Cartão de Autenticação -->
    <section class="auth-panel-wrap" aria-labelledby="login-heading">
      <div class="auth-card">
        <h1 id="login-heading">Acessar conta</h1>
        <p class="panel-subtitle" style="margin-bottom: 1.35rem;">
          Informe suas credenciais institucionais para entrar no painel da sua unidade.
        </p>

        <p v-if="route.query.reason === 'expired'" class="feedback warning" role="status">
          Sua sessão expirou. Entre novamente para continuar de onde parou.
        </p>

        <form class="form" :aria-busy="loading" @submit.prevent="handleLogin">
          <div class="field">
            <label for="login-email">
              <span>E-mail institucional</span>
              <span class="required-indicator" aria-hidden="true">*</span>
            </label>
            <div class="input-with-icon">
              <span class="input-leading-icon"><AppIcon name="mail" :size="17" /></span>
              <input
                id="login-email"
                v-model="loginForm.email"
                type="email"
                autocomplete="username"
                required
                placeholder="voce@organizacao.gov.br"
                :aria-invalid="!!errorMessage"
                :aria-describedby="errorMessage ? 'login-error' : undefined"
              />
            </div>
          </div>

          <div class="field">
            <label for="login-password">
              <span>Senha</span>
              <span class="required-indicator" aria-hidden="true">*</span>
            </label>
            <div class="input-with-icon has-trailing">
              <span class="input-leading-icon"><AppIcon name="lock" :size="17" /></span>
              <input
                id="login-password"
                v-model="loginForm.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                placeholder="Digite sua senha"
                :aria-invalid="!!errorMessage"
                :aria-describedby="errorMessage ? 'login-error' : undefined"
              />
              <button
                type="button"
                class="input-trailing-btn"
                :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                :title="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                @click="showPassword = !showPassword"
              >
                <AppIcon :name="showPassword ? 'eye-off' : 'eye'" :size="17" />
              </button>
            </div>
          </div>

          <div class="field">
            <label for="login-tenant-slug">
              <span>Unidade / Tenant (slug) <small>(opcional)</small></span>
            </label>
            <div class="input-with-icon">
              <span class="input-leading-icon"><AppIcon name="building" :size="17" /></span>
              <input
                id="login-tenant-slug"
                v-model="loginForm.tenantSlug"
                type="text"
                placeholder="ex.: demo"
                autocomplete="off"
                aria-describedby="login-tenant-hint"
              />
            </div>
            <small id="login-tenant-hint" class="field-hint">
              Informe apenas se seu e-mail estiver vinculado a mais de uma unidade.
            </small>
          </div>

          <p v-if="errorMessage" id="login-error" class="feedback error" role="alert">
            {{ errorMessage }}
          </p>

          <button class="primary-button btn-full" type="submit" :disabled="loading">
            <span v-if="!loading">Entrar no sistema</span>
            <span v-else>Autenticando…</span>
            <AppIcon v-if="!loading" name="arrow-right" :size="16" />
          </button>
        </form>

        <div v-if="isDev" class="demo-shortcuts">
          <span class="demo-shortcuts-label">Ambiente local — preenchimento rápido:</span>
          <div class="demo-shortcuts-btns">
            <button type="button" class="ghost-button" @click="fillDemoAccount('unit')">
              Admin Unidade (demo)
            </button>
            <button type="button" class="ghost-button" @click="fillDemoAccount('system')">
              Admin Sistema
            </button>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
