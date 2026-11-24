<!-- resources/js/Layouts/GuestLayout.vue -->
<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-[#09090b] text-[#ededef] font-sans antialiased selection:bg-sky-500/30 selection:text-sky-200">
    <!-- Pixelforge Brand Mark -->
    <div>
      <Link href="/" class="flex items-center gap-2.5">
        <div class="grid grid-cols-2 gap-0.5">
          <span class="h-2.5 w-2.5 rounded-[1px] bg-white"></span>
          <span class="h-2.5 w-2.5 rounded-[1px] bg-zinc-500"></span>
          <span class="h-2.5 w-2.5 rounded-[1px] bg-zinc-400"></span>
          <span class="h-2.5 w-2.5 rounded-[1px] bg-zinc-200"></span>
        </div>
        <span class="text-xl font-semibold tracking-tight text-white">Pixelforge</span>
      </Link>
    </div>

    <!-- Elevated Dark Card Container -->
    <div class="w-full sm:max-w-md mt-6 px-6 py-6 bg-[#111114] border border-[#222226] shadow-2xl overflow-hidden sm:rounded-lg">
      <slot />
    </div>
  </div>
</template>