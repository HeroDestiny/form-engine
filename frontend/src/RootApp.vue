<script setup>
import AppLayout from './components/AppLayout.vue'
import { session } from './auth/session.js'
</script>

<template>
  <AppLayout>
    <RouterView v-if="$route.meta.public || session.user" :key="$route.fullPath" />
    <section v-else class="panel" aria-busy="true">
      <span class="sr-only" role="status">Verificando sessão…</span>
      <div class="skeleton-list" aria-hidden="true">
        <div class="skeleton-block w-35" style="height: 1.5rem;" />
        <div class="skeleton-block w-65" />
        <div class="skeleton-row">
          <div class="skeleton-lines">
            <div class="skeleton-block w-50" />
            <div class="skeleton-block w-80" />
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>
