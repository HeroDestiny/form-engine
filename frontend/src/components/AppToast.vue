<script setup>
import { dismissToast, toasts } from '../composables/useToast.js'
import AppIcon from './AppIcon.vue'
</script>

<template>
  <div v-if="toasts.length" class="toast-viewport" aria-label="Notificações do sistema">
    <div
      v-for="item in toasts"
      :key="item.id"
      class="toast-card"
      :class="`toast-${item.tone}`"
      role="status"
    >
      <span class="toast-icon">
        <AppIcon :name="item.tone === 'error' ? 'close' : item.tone === 'warning' ? 'clock' : 'check'" :size="16" />
      </span>
      <span class="toast-text">{{ item.message }}</span>
      <button
        type="button"
        class="toast-dismiss"
        aria-label="Fechar notificação"
        @click="dismissToast(item.id)"
      >
        <AppIcon name="close" :size="15" />
      </button>
    </div>
  </div>
</template>
