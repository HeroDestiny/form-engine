<script setup>
import AppIcon from './AppIcon.vue'

defineProps({
  loading: Boolean,
  error: { type: String, default: '' },
  empty: Boolean,
  loadingMessage: { type: String, default: 'Carregando registros…' },
  emptyMessage: { type: String, default: 'Nenhum registro encontrado com os filtros selecionados.' },
  variant: { type: String, default: 'rows' },
  skeletonCount: { type: Number, default: 4 },
  canClearFilters: Boolean,
})
defineEmits(['retry', 'clear'])
</script>

<template>
  <div class="list-content" :aria-busy="loading">
    <template v-if="loading">
      <span class="sr-only" role="status">{{ loadingMessage }}</span>
      <div v-if="variant === 'cards'" class="forms-grid" aria-hidden="true">
        <div v-for="n in skeletonCount" :key="n" class="skeleton-card">
          <div class="skeleton-block w-65" style="height: 1.15rem;" />
          <div class="skeleton-block w-80" />
          <div class="skeleton-block w-35" />
        </div>
      </div>
      <div v-else class="skeleton-list" aria-hidden="true" style="padding: 1rem;">
        <div v-for="n in skeletonCount" :key="n" class="skeleton-row">
          <div class="skeleton-lines">
            <div class="skeleton-block w-50" />
            <div class="skeleton-block w-80" />
          </div>
          <div class="skeleton-block btn" />
        </div>
      </div>
    </template>

    <div v-else-if="error" class="list-error" style="padding: 1rem;">
      <p class="feedback error" role="alert">{{ error }}</p>
      <button type="button" class="ghost-button" @click="$emit('retry')">Tentar novamente</button>
    </div>

    <div v-else-if="empty" class="empty-state" role="status">
      <span class="empty-state-icon" aria-hidden="true">
        <AppIcon name="inbox" :size="22" />
      </span>
      <p>{{ emptyMessage }}</p>
      <button v-if="canClearFilters" type="button" class="ghost-button" @click="$emit('clear')">
        Limpar filtros
      </button>
    </div>

    <slot v-else />
  </div>
</template>
