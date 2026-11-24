<script setup>
import { onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

onMounted(() => {
  router.visit(route('studio.index'), { replace: true });
});
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-bold leading-tight tracking-tight text-white">
        Dashboard
      </h2>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="rounded-lg border border-[#222226] bg-[#111114] p-6 text-sm text-zinc-300">
          Redirecting to
          <Link :href="route('studio.index')" class="font-medium text-white underline">
            Pixelforge Studio
          </Link>
          ...
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>