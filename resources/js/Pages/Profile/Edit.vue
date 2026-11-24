<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  mustVerifyEmail: {
    type: Boolean,
  },
  status: {
    type: String,
  },
});
</script>

<template>
  <Head title="Profile" />

  <AuthenticatedLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-white">Profile Settings</h1>
        <p class="mt-1 text-sm text-zinc-400">
          Manage your account details, password, and security preferences.
        </p>
      </div>
    </template>

    <div class="py-8">
      <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-lg border border-[#222226] bg-[#111114] p-6 sm:p-8 shadow-xl">
          <UpdateProfileInformationForm
            :must-verify-email="mustVerifyEmail"
            :status="status"
            class="max-w-xl"
          />
        </div>

        <div class="rounded-lg border border-[#222226] bg-[#111114] p-6 sm:p-8 shadow-xl">
          <UpdatePasswordForm class="max-w-xl" />
        </div>

        <div class="rounded-lg border border-[#222226] bg-[#111114] p-6 sm:p-8 shadow-xl">
          <DeleteUserForm class="max-w-xl" />
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>