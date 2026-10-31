import { test } from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import { createSSRApp, h } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createMemoryHistory, createRouter } from 'vue-router'

import {
  formFillProgress,
  isFieldFilled,
  previewFieldFromDraft,
  reorderFieldPair,
} from '../src/utils/formValues.js'
import { useFormFill } from '../src/composables/useFormFill.js'
import {
  announce,
  clearBreadcrumbs,
  politeAnnouncement,
  resolveBreadcrumbs,
  setBreadcrumbs,
} from '../src/composables/useBreadcrumbs.js'
import { cycleTheme, initialsFromName, setTheme, themeMode } from '../src/composables/useTheme.js'
import { dismissToast, pushToast, toasts } from '../src/composables/useToast.js'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const frontendDir = path.resolve(__dirname, '..')
const srcDir = path.join(frontendDir, 'src')
const sfcUrlCache = new Map()

function compileVueSfcUrl(fullPath) {
  if (sfcUrlCache.has(fullPath)) return sfcUrlCache.get(fullPath)
  const source = fs.readFileSync(fullPath, 'utf8')
  const { descriptor } = parse(source, { filename: fullPath })
  const id = path.basename(fullPath, '.vue')

  let code
  if (descriptor.script || descriptor.scriptSetup) {
    const compiled = compileScript(descriptor, { id, inlineTemplate: true })
    code = compiled.content
  } else {
    const tpl = compileTemplate({ source: descriptor.template.content, filename: fullPath, id })
    code = `${tpl.code}\nexport default { render }`
  }

  const rewritten = code.replace(
    /\bfrom\s+(['"])([^'"]+)\1/g,
    (_, quote, spec) => {
      let resolved
      if (spec.startsWith('.')) {
        const targetPath = path.resolve(path.dirname(fullPath), spec)
        resolved = targetPath.endsWith('.vue')
          ? compileVueSfcUrl(targetPath)
          : pathToFileURL(targetPath).href
      } else {
        resolved = import.meta.resolve(spec)
      }
      return `from ${quote}${resolved}${quote}`
    },
  )

  const url = `data:text/javascript;base64,${Buffer.from(rewritten).toString('base64')}`
  sfcUrlCache.set(fullPath, url)
  return url
}

async function loadVueSfc(relFromSrc) {
  const fullPath = path.join(srcDir, relFromSrc)
  const mod = await import(compileVueSfcUrl(fullPath))
  return mod.default
}

function walkFiles(dir, extensions) {
  const results = []
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, entry.name)
    if (entry.isDirectory()) {
      results.push(...walkFiles(fullPath, extensions))
    } else if (extensions.some(ext => entry.name.endsWith(ext))) {
      results.push(fullPath)
    }
  }
  return results
}

function walkTemplateAst(node, visitor) {
  if (!node) return
  visitor(node)
  if (Array.isArray(node.children)) {
    for (const child of node.children) {
      walkTemplateAst(child, visitor)
    }
  }
}

function getElementAttrs(node) {
  const staticAttrs = Object.create(null)
  const boundAttrs = Object.create(null)
  for (const prop of node.props || []) {
    if (prop.type === 6) {
      staticAttrs[prop.name] = prop.value?.content ?? ''
    } else if (prop.type === 7 && prop.name === 'bind' && prop.arg?.type === 4) {
      boundAttrs[prop.arg.content] = prop.exp?.content ?? ''
    }
  }
  return { staticAttrs, boundAttrs }
}

test('HTML entry point and CSS design system enforce WCAG 2.2 tokens and user preferences', () => {
  const indexHtml = fs.readFileSync(path.join(frontendDir, 'index.html'), 'utf8')
  assert.match(indexHtml, /<html[^>]*\blang="pt-BR"/i)
  assert.match(indexHtml, /<title>[^<]+<\/title>/i)

  const styleCss = fs.readFileSync(path.join(srcDir, 'style.css'), 'utf8')
  for (const token of [
    '--color-bg-canvas',
    '--color-surface',
    '--color-surface-hover',
    '--color-border-subtle',
    '--color-brand-primary',
    '--color-brand-focus',
    '--color-danger',
    '--color-warning',
    '--color-success',
  ]) {
    assert.ok(styleCss.includes(token), `Missing design token ${token} in style.css`)
  }

  assert.match(styleCss, /:focus-visible\s*\{/)
  assert.match(styleCss, /\.skip-link\b/)
  assert.match(styleCss, /\.sr-only\b/)
  assert.match(styleCss, /@media\s*\(prefers-reduced-motion:\s*reduce\)/)
  assert.match(styleCss, /@media\s*\(forced-colors:\s*active\)/)
})

test('all active Vue templates satisfy strict label/id parity, button types, SVG semantics and no window.confirm', () => {
  const allCodeFiles = walkFiles(srcDir, ['.vue', '.js']).filter(
    file => !file.endsWith('HelloWorld.vue'),
  )
  for (const file of allCodeFiles) {
    const content = fs.readFileSync(file, 'utf8')
    const rel = path.relative(frontendDir, file)
    assert.ok(!content.includes('window.confirm('), `${rel} must use ConfirmDialog instead of window.confirm`)
  }

  const vueFiles = allCodeFiles.filter(file => file.endsWith('.vue'))
  assert.ok(vueFiles.length >= 18, 'Expected all application Vue SFCs to be audited')

  for (const file of vueFiles) {
    const rel = path.relative(frontendDir, file)
    const content = fs.readFileSync(file, 'utf8')
    const { descriptor } = parse(content, { filename: file })
    assert.ok(descriptor.template?.ast, `${rel} should have a valid template AST`)

    const staticLabelFors = new Set()
    const boundLabelFors = []
    const controls = []

    walkTemplateAst(descriptor.template.ast, (node) => {
      if (node.type !== 1) return
      const { staticAttrs, boundAttrs } = getElementAttrs(node)

      if (node.tag === 'label') {
        if (staticAttrs.for) staticLabelFors.add(staticAttrs.for)
        if (boundAttrs.for) boundLabelFors.push(boundAttrs.for)
      }

      if (['input', 'select', 'textarea'].includes(node.tag)) {
        if (staticAttrs.type === 'hidden') return
        controls.push({ tag: node.tag, staticAttrs, boundAttrs, line: node.loc?.start?.line })
      }

      if (node.tag === 'button') {
        const hasType = Boolean(staticAttrs.type || boundAttrs.type)
        assert.ok(hasType, `${rel}:${node.loc?.start?.line} <button> must declare an explicit type`)
      }

      if (node.tag === 'svg') {
        const hidden = staticAttrs['aria-hidden'] === 'true'
        const labelledImg = staticAttrs.role === 'img' && Boolean(staticAttrs['aria-label'] || boundAttrs['aria-label'])
        assert.ok(hidden || labelledImg, `${rel}:${node.loc?.start?.line} <svg> must have aria-hidden="true" or role="img" with aria-label`)
      }
    })

    for (const ctrl of controls) {
      const hasAriaLabel = Boolean(
        ctrl.staticAttrs['aria-label'] ||
        ctrl.boundAttrs['aria-label'] ||
        ctrl.staticAttrs['aria-labelledby'] ||
        ctrl.boundAttrs['aria-labelledby'],
      )
      if (ctrl.staticAttrs.id) {
        assert.ok(
          staticLabelFors.has(ctrl.staticAttrs.id) || hasAriaLabel,
          `${rel}:${ctrl.line} <${ctrl.tag} id="${ctrl.staticAttrs.id}"> lacks matching <label for="${ctrl.staticAttrs.id}">`,
        )
      } else if (ctrl.boundAttrs.id) {
        assert.ok(
          boundLabelFors.length > 0 || hasAriaLabel,
          `${rel}:${ctrl.line} <${ctrl.tag} :id="${ctrl.boundAttrs.id}"> lacks dynamic <label :for> or aria-label`,
        )
      } else {
        assert.ok(
          hasAriaLabel,
          `${rel}:${ctrl.line} <${ctrl.tag}> must define an explicit id + <label for> or aria-label`,
        )
      }
    }
  }

  const appLayoutSource = fs.readFileSync(path.join(srcDir, 'components', 'AppLayout.vue'), 'utf8')
  assert.match(appLayoutSource, /href="#main-content"/)
  assert.match(appLayoutSource, /<main[^>]*\bid="main-content"/)
})

test('accessible UI components render semantic ARIA roles, icons, skeletons and error associations', async () => {
  const [
    StatusBadge,
    ConfirmDialog,
    FormResponseField,
    ListContent,
    ListPagination,
    AppBreadcrumbs,
  ] = await Promise.all([
    loadVueSfc('components/StatusBadge.vue'),
    loadVueSfc('components/ConfirmDialog.vue'),
    loadVueSfc('components/FormResponseField.vue'),
    loadVueSfc('components/ListContent.vue'),
    loadVueSfc('components/ListPagination.vue'),
    loadVueSfc('components/AppBreadcrumbs.vue'),
  ])

  const badgeHtml = await renderToString(
    createSSRApp({ render: () => h(StatusBadge, { tone: 'success', label: 'Ativo' }) }),
  )
  assert.match(badgeHtml, /status-success/)
  assert.match(badgeHtml, /<svg[^>]*aria-hidden="true"/)
  assert.match(badgeHtml, /<span>Ativo<\/span>/)

  const dialogHtml = await renderToString(
    createSSRApp({
      render: () => h(ConfirmDialog, {
        open: true,
        dialogId: 'delete-test',
        title: 'Excluir registro?',
        description: 'Esta ação não pode ser desfeita.',
        tone: 'danger',
      }),
    }),
  )
  assert.match(dialogHtml, /role="dialog"/)
  assert.match(dialogHtml, /aria-modal="true"/)
  assert.match(dialogHtml, /aria-labelledby="delete-test-title"/)
  assert.match(dialogHtml, /aria-describedby="delete-test-desc"/)

  const fieldHtml = await renderToString(
    createSSRApp({
      render: () => h(FormResponseField, {
        field: { id: 7, name: 'cpf', label: 'CPF do requerente', type: 'text', is_required: true },
        modelValue: '',
        error: 'O campo CPF é obrigatório.',
      }),
    }),
  )
  assert.match(fieldHtml, /for="response-7"/)
  assert.match(fieldHtml, /id="response-7"/)
  assert.match(fieldHtml, /aria-invalid="true"/)
  assert.match(fieldHtml, /aria-describedby="response-7-error"/)
  assert.match(fieldHtml, /id="response-7-error"[^>]*role="alert"/)

  const previewFieldHtml = await renderToString(
    createSSRApp({
      render: () => h(FormResponseField, {
        field: { id: 7, name: 'cpf', label: 'CPF do requerente', type: 'text', is_required: false },
        modelValue: '',
        idPrefix: 'preview',
      }),
    }),
  )
  assert.match(previewFieldHtml, /for="preview-7"/)
  assert.match(previewFieldHtml, /id="preview-7"/)

  const fileFieldHtml = await renderToString(
    createSSRApp({
      render: () => h(FormResponseField, {
        field: { id: 8, name: 'anexo', label: 'Comprovante em PDF', type: 'file', is_required: true, options: [{ value: 'pdf', label: 'PDF' }] },
        modelValue: null,
      }),
    }),
  )
  assert.match(fileFieldHtml, /for="response-8"/)
  assert.match(fileFieldHtml, /id="response-8"/)
  assert.match(fileFieldHtml, /type="file"/)
  assert.match(fileFieldHtml, /attachment-dropzone/)

  const fileAttachedHtml = await renderToString(
    createSSRApp({
      render: () => h(FormResponseField, {
        field: { id: 9, name: 'anexo2', label: 'Foto RG', type: 'file', is_required: false },
        modelValue: { name: 'rg_frente.png', size: 204800 },
      }),
    }),
  )
  assert.match(fileAttachedHtml, /attachment-card/)
  assert.match(fileAttachedHtml, /rg_frente\.png/)
  assert.match(fileAttachedHtml, /200\.0 KB/)
  assert.match(fileAttachedHtml, /<button[^>]*type="button"[^>]*class="attachment-remove-btn"/)

  const skeletonHtml = await renderToString(
    createSSRApp({
      render: () => h(ListContent, {
        loading: true,
        loadingMessage: 'Carregando formulários…',
        variant: 'cards',
        skeletonCount: 3,
      }),
    }),
  )
  assert.match(skeletonHtml, /aria-busy="true"/)
  assert.match(skeletonHtml, /class="sr-only"[^>]*role="status"[^>]*>Carregando formulários…<\/span>/)
  assert.match(skeletonHtml, /class="forms-grid"[^>]*aria-hidden="true"/)
  assert.match(skeletonHtml, /class="skeleton-card"/)

  const emptyClearHtml = await renderToString(
    createSSRApp({
      render: () => h(ListContent, {
        loading: false,
        empty: true,
        emptyMessage: 'Nenhum formulário encontrado.',
        canClearFilters: true,
      }),
    }),
  )
  assert.match(emptyClearHtml, /Limpar filtros/)

  const paginationHtml = await renderToString(
    createSSRApp({
      render: () => h(ListPagination, {
        pagination: { current_page: 2, last_page: 5, total: 92 },
        perPage: 20,
        selectId: 'test-per-page',
      }),
    }),
  )
  assert.match(paginationHtml, /for="test-per-page"/)
  assert.match(paginationHtml, /id="test-per-page"/)
  assert.match(paginationHtml, /aria-live="polite"/)

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: { render: () => null } },
      { path: '/forms/:formId', name: 'form-detail', component: { render: () => null } },
      { path: '/forms/:formId/fill', name: 'form-fill', component: { render: () => null } },
    ],
  })
  await router.push('/forms/15/fill')
  clearBreadcrumbs()
  const breadcrumbsApp = createSSRApp({ render: () => h(AppBreadcrumbs) })
  breadcrumbsApp.use(router)
  const breadcrumbsHtml = await renderToString(breadcrumbsApp)
  assert.match(breadcrumbsHtml, /aria-label="Trilha de navegação"/)
  assert.match(breadcrumbsHtml, /Formulário #15/)
  assert.match(breadcrumbsHtml, /aria-current="page">Preencher formulário<\/span>/)
})

test('formFillProgress computes required and optional completion accurately across types', () => {
  assert.equal(isFieldFilled(''), false)
  assert.equal(isFieldFilled('   '), false)
  assert.equal(isFieldFilled([]), false)
  assert.equal(isFieldFilled(0), true)
  assert.equal(isFieldFilled(['opcao_a']), true)

  const fields = [
    { name: 'nome', is_required: true },
    { name: 'dependentes', is_required: true },
    { name: 'interesses', is_required: false },
  ]
  const step1 = formFillProgress(fields, { nome: 'Maria', dependentes: '', interesses: [] })
  assert.equal(step1.requiredFilled, 1)
  assert.equal(step1.requiredTotal, 2)
  assert.equal(step1.percent, 50)
  assert.match(step1.label, /1 de 2/)

  const step2 = formFillProgress(fields, { nome: 'Maria', dependentes: 0, interesses: ['a'] })
  assert.equal(step2.requiredFilled, 2)
  assert.equal(step2.filled, 3)
  assert.equal(step2.percent, 100)

  const optionalOnly = formFillProgress([{ name: 'obs', is_required: false }], { obs: 'ok' })
  assert.equal(optionalOnly.requiredTotal, 0)
  assert.equal(optionalOnly.percent, 100)
})

test('reorderFieldPair and previewFieldFromDraft support keyboard reordering and live builder preview', () => {
  const fields = [
    { id: 10, label: 'Nome', name: 'nome', order: 1 },
    { id: 11, label: 'E-mail', name: 'email', order: 2 },
    { id: 12, label: 'Data', name: 'data', order: 2 },
  ]
  assert.equal(reorderFieldPair(fields, 0, -1), null)
  assert.equal(reorderFieldPair(fields, 2, 1), null)

  const swapDistinct = reorderFieldPair(fields, 0, 1)
  assert.equal(swapDistinct.current.id, 10)
  assert.equal(swapDistinct.target.id, 11)
  assert.equal(swapDistinct.currentNextOrder, 2)
  assert.equal(swapDistinct.targetNextOrder, 1)

  const swapTied = reorderFieldPair(fields, 1, 1)
  assert.notEqual(swapTied.currentNextOrder, swapTied.targetNextOrder)

  assert.equal(previewFieldFromDraft({ label: '   ', name: 'vazio', type: 'text' }), null)
  const preview = previewFieldFromDraft({
    label: 'Turno preferencial',
    name: '',
    type: 'radio',
    is_required: true,
    order: 4,
    optionsText: 'manha|Manhã\ntarde|Tarde',
  })
  assert.equal(preview.name, 'campo_em_edicao')
  assert.deepEqual(preview.options, [
    { value: 'manha', label: 'Manhã' },
    { value: 'tarde', label: 'Tarde' },
  ])
})

test('useFormFill autosave skips blank new drafts, persists dirty answers quietly and avoids duplicate saves', async () => {
  const calls = []
  const client = {
    getForm: async () => ({ data: { form: { id: 3, name: 'Cadastro', is_active: true } } }),
    listFormVersions: async () => ({ data: { versions: [{ id: 8, version_number: 1, is_published: true }] } }),
    listFields: async () => ({
      data: {
        fields: [
          { id: 1, name: 'nome', label: 'Nome', type: 'text', is_required: true, order: 1 },
        ],
      },
    }),
    listDrafts: async () => ({ data: { drafts: calls.length ? [{ id: 55 }] : [] } }),
    saveDraft: async (formId, payload, versionId) => {
      calls.push({ op: 'saveDraft', formId, payload, versionId })
      return { data: { draft: { id: 55 } } }
    },
    updateDraft: async (formId, draftId, payload) => {
      calls.push({ op: 'updateDraft', formId, draftId, payload })
      return { data: { draft: { id: draftId } } }
    },
  }

  const fill = useFormFill(3, 9, client)
  await fill.initialize()

  // Blank new form should not create empty drafts via autosave
  assert.equal(await fill.autosave(), false)
  assert.equal(calls.length, 0)

  fill.values.nome = 'Ana Souza'
  assert.equal(await fill.autosave(), true)
  assert.equal(calls.length, 1)
  assert.equal(calls[0].op, 'saveDraft')
  assert.equal(fill.state.activeDraftId, 55)
  assert.ok(fill.state.lastSavedAt)
  assert.equal(fill.state.message, '')

  // Subsequent autosave with no new edits is a no-op
  assert.equal(await fill.autosave(), false)
  assert.equal(calls.length, 1)

  // Editing an existing draft updates that draft
  fill.values.nome = 'Ana Paula Souza'
  assert.equal(await fill.autosave(), true)
  assert.equal(calls.length, 2)
  assert.equal(calls[1].op, 'updateDraft')
})

test('resolveBreadcrumbs and polite live announcer support contextual navigation and screen reader updates', async () => {
  clearBreadcrumbs()
  assert.deepEqual(resolveBreadcrumbs({ name: 'login', meta: { public: true } }), [])
  assert.deepEqual(resolveBreadcrumbs({ name: 'home', meta: {} }), [])
  assert.deepEqual(
    resolveBreadcrumbs({ name: 'form-fields', params: { formId: '12' }, meta: {} }),
    [
      { label: 'Formulários', to: '/' },
      { label: 'Formulário #12', to: '/forms/12' },
      { label: 'Campos da versão' },
    ],
  )

  setBreadcrumbs([{ label: 'Formulários', to: '/' }, { label: 'Ficha Social' }])
  assert.deepEqual(resolveBreadcrumbs({ name: 'form-detail', params: { formId: '12' } }), [
    { label: 'Formulários', to: '/' },
    { label: 'Ficha Social' },
  ])
  clearBreadcrumbs()

  announce('12 formulários carregados.')
  await new Promise(resolve => queueMicrotask(resolve))
  assert.equal(politeAnnouncement.value, '12 formulários carregados.')
})

test('Executive Blue UI tokens, Sidebar+Topbar shell, manual theme switcher and toast notifications work cleanly', async () => {
  const styleCss = fs.readFileSync(path.join(srcDir, 'style.css'), 'utf8')
  assert.match(styleCss, /--color-brand-primary:\s*#1d4ed8/i)
  assert.match(styleCss, /--sidebar-bg:\s*#0b192c/i)
  assert.match(styleCss, /\.workspace-shell\b/)
  assert.match(styleCss, /\.data-table\b/)

  const appLayoutSource = fs.readFileSync(path.join(srcDir, 'components', 'AppLayout.vue'), 'utf8')
  assert.match(appLayoutSource, /class="app-sidebar"/)
  assert.match(appLayoutSource, /class="app-topbar"/)

  assert.equal(initialsFromName('Paulo Silva'), 'PS')
  assert.equal(initialsFromName('Admin'), 'AD')
  assert.equal(initialsFromName(''), 'U')

  setTheme('dark')
  assert.equal(themeMode.value, 'dark')
  cycleTheme()
  assert.equal(themeMode.value, 'system')
  setTheme('invalid-mode')
  assert.equal(themeMode.value, 'system')

  const toastId = pushToast('Operação concluída com sucesso.', 'success', 0)
  assert.equal(toasts.value.some(item => item.id === toastId), true)

  const AppToast = await loadVueSfc('components/AppToast.vue')
  const toastHtml = await renderToString(createSSRApp({ render: () => h(AppToast) }))
  assert.match(toastHtml, /Operação concluída com sucesso\./)
  assert.match(toastHtml, /aria-label="Fechar notificação"/)

  dismissToast(toastId)
  assert.equal(toasts.value.some(item => item.id === toastId), false)
})
