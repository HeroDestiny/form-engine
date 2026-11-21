<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createForm } from '../api'
import { canManageForms } from '../auth/session.js'
import { pushToast } from '../composables/useToast.js'
import AppIcon from '../components/AppIcon.vue'

const router = useRouter()

const form = reactive({
  name: '',
  description: '',
})

const loading = ref(false)
const errorMessage = ref('')

async function handleSubmit() {
  if (!canManageForms.value) return

  loading.value = true
  errorMessage.value = ''

  try {
    const data = await createForm({ name: form.name, description: form.description || null })
    pushToast(data?.message || 'Formulário criado com sucesso.')

    const createdFormId = data?.data?.form?.id
    if (createdFormId) {
      const versionId = data?.data?.version?.id
      await router.push(versionId ? `/forms/${createdFormId}/versions/${versionId}/fields` : `/forms/${createdFormId}`)
    } else {
      await router.push('/')
    }
  } catch (error) {
    if (error.status === 403) {
      errorMessage.value = 'Você não tem permissão para criar formulários.'
    } else if (error.status === 422 && error.body?.errors) {
      const errors = error.body.errors
      errorMessage.value = Object.values(errors)[0]?.[0] || 'Dados inválidos.'
    } else {
      errorMessage.value = error?.body?.message || 'Erro ao criar formulário.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page-stack">
    <section class="panel panel-narrow">
      <div class="card-heading" style="margin-bottom: 0.45rem;">
        <h1>Novo formulário</h1>
        <span class="kpi-icon"><AppIcon name="forms" :size="19" /></span>
      </div>
      <p class="panel-subtitle" style="margin-bottom: 1.5rem;">
        Defina a identificação básica da ficha. Em seguida, você será direcionado ao construtor interativo da Versão 1.
      </p>

      <form class="form" :aria-busy="loading" @submit.prevent="handleSubmit">
        <div class="field">
          <label for="create-form-name">
            <span>Título do formulário</span>
            <span class="required-indicator" aria-hidden="true">*</span>
          </label>
          <input
            id="create-form-name"
            v-model="form.name"
            type="text"
            required
            minlength="3"
            maxlength="255"
            placeholder="Ex.: Cadastro de Benefícios Sociais"
            :aria-invalid="!!errorMessage"
            :aria-describedby="errorMessage ? 'create-form-error' : 'create-form-name-hint'"
          />
          <small id="create-form-name-hint" class="field-hint">Mínimo de 3 e máximo de 255 caracteres.</small>
        </div>

        <div class="field">
          <label for="create-form-desc">
            <span>Descrição institucional <small>(opcional)</small></span>
          </label>
          <textarea
            id="create-form-desc"
            v-model="form.description"
            rows="3"
            maxlength="1000"
            placeholder="Descreva a finalidade, orientações gerais e o público-alvo deste formulário."
            aria-describedby="create-form-desc-hint"
          />
          <small id="create-form-desc-hint" class="field-hint">Até 1.000 caracteres. Exibida no topo durante o preenchimento.</small>
        </div>

        <p v-if="errorMessage" id="create-form-error" class="feedback error" role="alert">{{ errorMessage }}</p>

        <div class="panel-actions" style="margin-top: 0.35rem;">
          <button class="primary-button" type="submit" :disabled="loading || !canManageForms">
            <span v-if="!loading">Criar e configurar campos</span>
            <span v-else>Criando…</span>
            <AppIcon v-if="!loading" name="arrow-right" :size="16" />
          </button>
          <RouterLink class="ghost-button" to="/">Cancelar</RouterLink>
        </div>
      </form>
    </section>
  </div>
</template>
