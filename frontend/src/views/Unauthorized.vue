<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

const goHome = () => {
  router.push('/')
}
</script>

<template>
  <div class="flex flex-col items-center justify-center h-screen text-center">
    <h1 class="text-6xl font-bold text-gray-900 dark:text-white">401</h1>
    <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">
      You are not authorized to access this page.
    </p>

    <button
      @click="goHome"
      class="mt-6 px-6 py-2 text-white bg-primary rounded-lg shadow-md hover:bg-gray-900 transition"
    >
      Go Home
    </button>
  </div>
</template>
