<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { session } from '../auth/session.js'
import { useFormFill } from '../composables/useFormFill.js'
import { usePageBreadcrumbs } from '../composables/useBreadcrumbs.js'
import { pushToast } from '../composables/useToast.js'
import { uploadFormAttachment } from '../api.js'
import FormResponseField from '../components/FormResponseField.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import StatusBadge from '../components/StatusBadge.vue'
import AppIcon from '../components/AppIcon.vue'
import { formatDate } from '../utils/display.js'
import { isFieldFilled } from '../utils/formValues.js'
import { getFieldWidthClass, getFormLayoutMode, groupFieldsBySection } from '../utils/formLayout.js'

const route = useRoute()
const router = useRouter()
const { setBreadcrumbs } = usePageBreadcrumbs()

const formRef = ref(null)
const autosaveEnabled = ref(true)
const bypassLeaveGuard = ref(false)
const discardDialog = reactive({ open: false, type: '', target: '' })
const selectedDraftId = ref(typeof route.query.draft === 'string' ? route.query.draft : '')
const uploadingFields = reactive({})

async function handleFileUpload({ file, field }) {
  if (file.size > 10 * 1024 * 1024) {
    state.fieldErrors[field.name] = 'O arquivo selecionado excede o limite máximo de 10 MB.'
    return
  }
  uploadingFields[field.name] = true
  delete state.fieldErrors[field.name]
  try {
    const res = await uploadFormAttachment(route.params.formId, file, field.name)
    values[field.name] = res.data
    pushToast(`Arquivo "${res.data.name}" anexado com sucesso.`)
  } catch (err) {
    const msg = err.body?.errors?.file?.[0] || err.body?.message || err.message || 'Falha ao enviar arquivo.'
    state.fieldErrors[field.name] = msg
    pushToast(msg, 'error')
  } finally {
    uploadingFields[field.name] = false
  }
}

const {
  state,
  values,
  busy,
  dirty,
  canWrite,
  progress,
  visibleFields,
  initialize,
  switchContext,
  loadDrafts,
  save,
  autosave,
} = useFormFill(route.params.formId, session.user.id)

let autosaveInterval = null
let blurTimeout = null

watch(() => state.activeDraftId, value => {
  selectedDraftId.value = value || ''
})

watch(() => state.form?.name, name => {
  if (name) {
    setBreadcrumbs([
      { label: 'Formulários', to: '/' },
      { label: name, to: `/forms/${route.params.formId}` },
      { label: 'Preencher' },
    ])
  }
})

const layoutMode = computed(() => getFormLayoutMode(state.fields))
const groupedSections = computed(() => groupFieldsBySection(visibleFields.value))

const currentStepIndex = ref(0)
const totalSteps = computed(() => groupedSections.value.length)
const currentSection = computed(() => groupedSections.value[currentStepIndex.value] || null)

watch(totalSteps, count => {
  if (currentStepIndex.value >= count) {
    currentStepIndex.value = Math.max(0, count - 1)
  }
})

function canGoNext() {
  return currentStepIndex.value < totalSteps.value - 1
}

function handleNextStep() {
  if (!currentSection.value) return
  const missing = currentSection.value.fields.filter(f => f.is_required && !isFieldFilled(values[f.name]))
  if (missing.length > 0) {
    state.fieldErrors[missing[0].name] = 'Este campo é obrigatório antes de avançar.'
    focusFirstInvalidField()
    return
  }
  if (canGoNext()) {
    currentStepIndex.value++
    formRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
  }
}

function handlePrevStep() {
  if (currentStepIndex.value > 0) {
    currentStepIndex.value--
    formRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
  }
}

function isSectionComplete(sec) {
  if (!sec || !sec.fields.length) return false
  const required = sec.fields.filter(f => f.is_required)
  if (!required.length) {
    return sec.fields.some(f => isFieldFilled(values[f.name]))
  }
  return required.every(f => isFieldFilled(values[f.name]))
}

const autosaveStatusText = computed(() => {
  if (state.autosaving) return 'Salvando rascunho automaticamente…'
  if (state.saving) return 'Salvando rascunho…'
  if (dirty.value) return 'Alterações ainda não salvas'
  if (state.lastSavedAt) {
    const d = new Date(state.lastSavedAt)
    const time = Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    return time ? `Rascunho salvo às ${time}` : 'Rascunho salvo'
  }
  return 'Nenhuma alteração pendente'
})

function requestChangeContext(id = '') {
  if (busy.value) return
  if (dirty.value) {
    discardDialog.open = true
    discardDialog.type = 'context'
    discardDialog.target = id
    return
  }
  switchContext(id)
}

async function confirmDiscardAction() {
  const { type, target } = discardDialog
  discardDialog.open = false
  discardDialog.type = ''
  discardDialog.target = ''
  if (type === 'context') {
    await switchContext(target)
  } else if (type === 'route' && target) {
    bypassLeaveGuard.value = true
    await router.push(target)
  }
}

async function focusFirstInvalidField() {
  await nextTick()
  const firstInvalid = state.fields.find(field => state.fieldErrors[field.name])
  if (!firstInvalid || !formRef.value) return
  const escapedName = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
    ? CSS.escape(firstInvalid.name)
    : String(firstInvalid.name).replace(/["\\]/g, '\\$&')
  const wrapper = formRef.value.querySelector(`[data-field-name="${escapedName}"]`)
  const control = wrapper?.querySelector('input, select, textarea')
  if (control) {
    const reduceMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    control.focus()
    control.scrollIntoView?.({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' })
  }
}

async function handleSaveDraft() {
  const ok = await save(false)
  if (ok) pushToast('Rascunho salvo com sucesso.')
  else await focusFirstInvalidField()
}

async function handleSubmitForm() {
  const ok = await save(true)
  if (ok) pushToast('Formulário enviado com sucesso!')
  else await focusFirstInvalidField()
}

function handleFormFocusOut() {
  if (!autosaveEnabled.value || !dirty.value || !canWrite.value) return
  clearTimeout(blurTimeout)
  blurTimeout = setTimeout(() => {
    if (autosaveEnabled.value && dirty.value && canWrite.value) {
      autosave()
    }
  }, 2000)
}

onBeforeRouteLeave((to) => {
  if (bypassLeaveGuard.value || !session.user || (!state.saving && !state.submitting && !dirty.value)) {
    bypassLeaveGuard.value = false
    return true
  }
  if (state.saving || state.submitting) return false
  discardDialog.open = true
  discardDialog.type = 'route'
  discardDialog.target = to.fullPath
  return false
})

onMounted(() => {
  initialize(selectedDraftId.value)
  autosaveInterval = setInterval(() => {
    if (autosaveEnabled.value && dirty.value && canWrite.value) {
      autosave()
    }
  }, 30000)
})

onBeforeUnmount(() => {
  clearInterval(autosaveInterval)
  clearTimeout(blurTimeout)
})
</script>

<template>
  <div class="page-stack" :aria-busy="state.loading || state.switching">
    <!-- Cabeçalho do Formulário -->
    <div class="page-header">
      <div>
        <div class="card-heading" style="margin-bottom: 0.35rem;">
          <h1>{{ state.form?.name || 'Preencher formulário' }}</h1>
          <StatusBadge
            v-if="state.version"
            tone="success"
            :label="`Versão ${state.version.version_number}`"
          />
        </div>
        <p v-if="state.form?.description" class="panel-subtitle">{{ state.form.description }}</p>
      </div>
    </div>

    <template v-if="state.loading || state.switching">
      <span class="sr-only" role="status">Carregando formulário…</span>
      <div class="skeleton-list" aria-hidden="true">
        <div v-for="n in 3" :key="n" class="skeleton-card">
          <div class="skeleton-block w-35" />
          <div class="skeleton-block w-80" style="height: 2.5rem;" />
        </div>
      </div>
    </template>

    <p v-if="state.error" class="feedback error" role="alert">{{ state.error }}</p>
    <p v-if="state.draftsError" class="feedback error" role="alert">
      <span>{{ state.draftsError }}</span>
      <button class="ghost-button" type="button" :disabled="busy" @click="loadDrafts">Atualizar rascunhos</button>
    </p>

    <!-- Seletor de Rascunhos -->
    <div v-if="!state.loading && !state.switching && state.drafts.length && !state.completedId" class="toolbar-card">
      <div class="panel-actions">
        <div class="field" style="flex: 1 1 240px;">
          <label for="fill-draft-selector">Rascunhos salvos anteriormente</label>
          <select id="fill-draft-selector" v-model="selectedDraftId" :disabled="busy">
            <option value="">Selecione um rascunho…</option>
            <option v-for="draft in state.drafts" :key="draft.id" :value="draft.id">
              Rascunho #{{ draft.id }} · {{ formatDate(draft.updated_at || draft.created_at) }}
            </option>
          </select>
        </div>
        <div class="panel-actions" style="align-self: flex-end;">
          <button
            class="ghost-button"
            type="button"
            :disabled="busy || !selectedDraftId"
            @click="requestChangeContext(selectedDraftId)"
          >
            Retomar selecionado
          </button>
          <button class="ghost-button" type="button" :disabled="busy" @click="requestChangeContext()">
            Novo em branco
          </button>
        </div>
      </div>
    </div>

    <!-- Cartão Celebratório de Submissão Concluída -->
    <section v-if="state.completedId" class="completion-banner" role="status">
      <span class="completion-icon" aria-hidden="true">
        <AppIcon name="check" :size="26" />
      </span>
      <h2 style="margin: 0;">Formulário enviado com sucesso!</h2>
      <p class="panel-subtitle">
        Seu registro foi confirmado sob o protocolo <strong class="tabular">#{{ state.completedId }}</strong>.
      </p>
      <div class="panel-actions" style="margin-top: 0.5rem;">
        <RouterLink class="primary-button" :to="`/submissions/${state.completedId}`">
          <span>Abrir comprovante #{{ state.completedId }}</span>
          <AppIcon name="arrow-right" :size="15" />
        </RouterLink>
        <button class="ghost-button" type="button" :disabled="busy" @click="requestChangeContext()">
          Realizar novo preenchimento
        </button>
      </div>
    </section>

    <!-- Área de Preenchimento Ativa -->
    <template v-if="state.ready && !state.completedId && !state.loading && !state.switching">
      <div class="progress-card">
        <div class="progress-header">
          <strong id="fill-progress-label">{{ progress.label }}</strong>
          <span class="tabular" style="font-weight: 700; color: var(--accent);">{{ progress.percent }}%</span>
        </div>
        <div
          class="progress-track"
          role="progressbar"
          :aria-valuenow="progress.percent"
          aria-valuemin="0"
          aria-valuemax="100"
          aria-labelledby="fill-progress-label"
        >
          <div class="progress-fill" :style="{ width: `${progress.percent}%` }" />
        </div>

        <div class="autosave-bar">
          <span role="status" aria-live="polite">
            {{ state.activeDraftId ? `Rascunho #${state.activeDraftId} · ` : '' }}{{ autosaveStatusText }}
          </span>
          <label for="fill-autosave-toggle" class="autosave-toggle">
            <input id="fill-autosave-toggle" v-model="autosaveEnabled" type="checkbox" />
            <span>Salvamento automático</span>
          </label>
        </div>
      </div>

      <form
        ref="formRef"
        class="form"
        novalidate
        @submit.prevent="handleSubmitForm"
        @focusout="handleFormFocusOut"
      >
        <fieldset class="response-fields form" :disabled="busy || !state.ready">
          <!-- Modo Wizard (Etapas Passo a Passo) -->
          <template v-if="layoutMode === 'wizard' && groupedSections.length > 1">
            <nav class="wizard-stepper" aria-label="Etapas do formulário">
              <button
                v-for="(sec, idx) in groupedSections"
                :key="sec.id"
                type="button"
                class="wizard-step-pill"
                :class="{
                  active: currentStepIndex === idx,
                  completed: isSectionComplete(sec) && currentStepIndex !== idx,
                }"
                :aria-current="currentStepIndex === idx ? 'step' : undefined"
                @click="currentStepIndex = idx"
              >
                <span class="wizard-step-number" aria-hidden="true">
                  {{ isSectionComplete(sec) && currentStepIndex !== idx ? '✓' : idx + 1 }}
                </span>
                <span>{{ sec.title }}</span>
              </button>
            </nav>

            <section
              v-if="currentSection"
              :key="currentSection.id"
              class="form-section-card"
              :aria-labelledby="`sec-title-${currentSection.id}`"
            >
              <div class="section-card-header">
                <div>
                  <h2 :id="`sec-title-${currentSection.id}`" class="section-card-title">
                    <span>{{ currentSection.title }}</span>
                  </h2>
                  <p v-if="currentSection.description" class="section-card-desc">
                    {{ currentSection.description }}
                  </p>
                </div>
                <span class="mono-badge">Etapa {{ currentStepIndex + 1 }} de {{ totalSteps }}</span>
              </div>

              <div class="form-grid">
                <div
                  v-for="field in currentSection.fields"
                  :key="field.id"
                  :class="getFieldWidthClass(field)"
                >
                  <FormResponseField
                    v-model="values[field.name]"
                    :field="field"
                    :error="state.fieldErrors[field.name] || ''"
                    :uploading="Boolean(uploadingFields[field.name])"
                    @file-selected="payload => handleFileUpload(payload)"
                  />
                </div>
              </div>

              <div class="wizard-actions">
                <button
                  class="ghost-button"
                  type="button"
                  :disabled="currentStepIndex === 0 || busy"
                  @click="handlePrevStep"
                >
                  ← Etapa anterior
                </button>
                <div class="panel-actions">
                  <button class="ghost-button" type="button" :disabled="!canWrite" @click="handleSaveDraft">
                    Salvar rascunho
                  </button>
                  <button
                    v-if="currentStepIndex < totalSteps - 1"
                    class="primary-button"
                    type="button"
                    :disabled="busy"
                    @click="handleNextStep"
                  >
                    <span>Próxima etapa</span>
                    <AppIcon name="arrow-right" :size="15" />
                  </button>
                  <button
                    v-else
                    class="primary-button"
                    type="submit"
                    :disabled="!canWrite"
                  >
                    <AppIcon name="check" :size="16" />
                    <span>{{ state.submitting ? 'Enviando…' : 'Enviar formulário' }}</span>
                  </button>
                </div>
              </div>
            </section>
          </template>

          <!-- Modo Cards Contínuos (Página única com Seções) -->
          <template v-else>
            <section
              v-for="sec in groupedSections"
              :key="sec.id"
              class="form-section-card"
              :aria-labelledby="`sec-title-${sec.id}`"
            >
              <div v-if="!sec.isDefault || groupedSections.length > 1" class="section-card-header">
                <div>
                  <h2 :id="`sec-title-${sec.id}`" class="section-card-title">
                    <span>{{ sec.title }}</span>
                  </h2>
                  <p v-if="sec.description" class="section-card-desc">
                    {{ sec.description }}
                  </p>
                </div>
                <span class="mono-badge">{{ sec.fields.length }} campo(s)</span>
              </div>

              <div class="form-grid">
                <div
                  v-for="field in sec.fields"
                  :key="field.id"
                  :class="getFieldWidthClass(field)"
                >
                  <FormResponseField
                    v-model="values[field.name]"
                    :field="field"
                    :error="state.fieldErrors[field.name] || ''"
                    :uploading="Boolean(uploadingFields[field.name])"
                    @file-selected="payload => handleFileUpload(payload)"
                  />
                </div>
              </div>
            </section>

            <div class="surface-card panel-actions" style="justify-content: flex-end;">
              <button class="ghost-button" type="button" :disabled="!canWrite" @click="handleSaveDraft">
                {{ state.saving && !state.autosaving ? 'Salvando…' : 'Salvar rascunho' }}
              </button>
              <button class="primary-button" type="submit" :disabled="!canWrite">
                <AppIcon name="check" :size="16" />
                <span>{{ state.submitting ? 'Enviando…' : 'Enviar formulário' }}</span>
              </button>
            </div>
          </template>
        </fieldset>
      </form>
    </template>

    <div v-else-if="!state.completedId && !busy && state.error">
      <button class="ghost-button" type="button" @click="initialize(selectedDraftId)">
        Tentar novamente
      </button>
    </div>

    <ConfirmDialog
      :open="discardDialog.open"
      dialog-id="discard-changes-confirm"
      title="Descartar alterações não salvas?"
      description="Você possui respostas modificadas que ainda não foram salvas como rascunho. Se continuar, essas alterações serão perdidas."
      confirm-label="Descartar alterações"
      cancel-label="Continuar preenchendo"
      tone="danger"
      @confirm="confirmDiscardAction"
      @cancel="discardDialog.open = false"
    />
  </div>
</template>
