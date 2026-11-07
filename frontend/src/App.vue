<script setup>
import { ref, onMounted } from 'vue'
import api from './api/axios'

const status = ref('Bezig met verbinden…')

onMounted(async () => {
  try {
    const { data } = await api.get('/ping')
    status.value = data.status === 'ok' ? 'Verbonden met de backend' : 'Onverwacht antwoord'
  } catch (e) {
    status.value = 'Geen verbinding: ' + e.message
  }
})
</script>

<template>
  <main class="min-h-screen grid place-items-center bg-zinc-100">
    <p class="rounded-xl bg-white px-6 py-4 text-zinc-800 shadow-sm">{{ status }}</p>
  </main>
</template>