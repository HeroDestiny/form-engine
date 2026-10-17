<script setup>
import AppLayout from './components/AppLayout.vue'
import { session } from './auth/session.js'
</script>

<template>
  <AppLayout>
    <RouterView v-if="$route.meta.public || session.user" :key="$route.fullPath" />
    <section v-else class="panel" role="status">Verificando sessão…</section>
  </AppLayout>
</template>
