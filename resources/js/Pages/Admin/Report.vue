<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, DollarSign, TrendingUp, Cpu } from 'lucide-vue-next';

defineProps({
  summary: Object,
  dailyRows: Array,
  userRows: Array,
  providerUsage: Object,
  providerUsageError: String,
});
</script>

<template>
  <Head title="Admin Financial Report" />

  <div class="min-h-screen bg-[#09090b] text-[#ededef] font-sans antialiased">
    <header class="border-b border-[#1f1f23] bg-[#09090b]">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
          <Link :href="route('studio.index')" class="inline-flex items-center gap-1.5 text-sm text-zinc-400 hover:text-white">
            <ArrowLeft class="h-4 w-4" />
            Back to Studio
          </Link>
          <span class="text-zinc-700">/</span>
          <h1 class="text-base font-semibold text-white">Operator Financial Report</h1>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
      <!-- Top Summary Cards -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-[#222226] bg-[#111114] p-5">
          <div class="flex items-center justify-between text-xs text-zinc-400">
            <span>Credit Spend (Charged to Users)</span>
            <DollarSign class="h-4 w-4 text-zinc-400" />
          </div>
          <p class="mt-2 font-mono text-xl font-bold text-white">${{ summary.credit_spend }}</p>
        </div>

        <div class="rounded-lg border border-[#222226] bg-[#111114] p-5">
          <div class="flex items-center justify-between text-xs text-zinc-400">
            <span>Provider Cost (Pixelforge)</span>
            <Cpu class="h-4 w-4 text-sky-400" />
          </div>
          <p class="mt-2 font-mono text-xl font-bold text-sky-300">${{ summary.provider_cost }}</p>
        </div>

        <div class="rounded-lg border border-[#222226] bg-[#111114] p-5">
          <div class="flex items-center justify-between text-xs text-zinc-400">
            <span>Net Profit</span>
            <TrendingUp class="h-4 w-4 text-emerald-400" />
          </div>
          <p class="mt-2 font-mono text-xl font-bold text-emerald-300">${{ summary.profit }}</p>
        </div>
      </div>

      <!-- Daily Breakdown -->
      <div class="rounded-lg border border-[#222226] bg-[#111114] overflow-hidden">
        <div class="border-b border-[#1f1f24] px-5 py-4">
          <h2 class="text-sm font-semibold text-white">By Day</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="border-b border-[#1f1f24] bg-[#09090b] text-zinc-400">
              <tr>
                <th class="px-5 py-3">Date</th>
                <th class="px-5 py-3">Settled Jobs</th>
                <th class="px-5 py-3">Credit Spend</th>
                <th class="px-5 py-3">Provider Cost</th>
                <th class="px-5 py-3">Profit</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1c1c21]">
              <tr v-for="row in dailyRows" :key="row.date">
                <td class="px-5 py-3 font-mono text-white">{{ row.date }}</td>
                <td class="px-5 py-3 font-mono text-zinc-300">{{ row.generations_count }}</td>
                <td class="px-5 py-3 font-mono text-white">${{ row.credit_spend }}</td>
                <td class="px-5 py-3 font-mono text-sky-300">${{ row.provider_cost }}</td>
                <td class="px-5 py-3 font-mono text-emerald-300">${{ row.profit }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Per-User Breakdown -->
      <div class="rounded-lg border border-[#222226] bg-[#111114] overflow-hidden">
        <div class="border-b border-[#1f1f24] px-5 py-4">
          <h2 class="text-sm font-semibold text-white">By User</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="border-b border-[#1f1f24] bg-[#09090b] text-zinc-400">
              <tr>
                <th class="px-5 py-3">User</th>
                <th class="px-5 py-3">Remaining Balance</th>
                <th class="px-5 py-3">Settled Jobs</th>
                <th class="px-5 py-3">Credit Spend</th>
                <th class="px-5 py-3">Provider Cost</th>
                <th class="px-5 py-3">Profit</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1c1c21]">
              <tr v-for="row in userRows" :key="row.user_id">
                <td class="px-5 py-3">
                  <div class="font-medium text-white">{{ row.name }}</div>
                  <div class="font-mono text-[11px] text-zinc-500">{{ row.email }}</div>
                </td>
                <td class="px-5 py-3 font-mono text-zinc-300">${{ row.credit_balance }}</td>
                <td class="px-5 py-3 font-mono text-zinc-300">{{ row.generations_count }}</td>
                <td class="px-5 py-3 font-mono text-white">${{ row.credit_spend }}</td>
                <td class="px-5 py-3 font-mono text-sky-300">${{ row.provider_cost }}</td>
                <td class="px-5 py-3 font-mono text-emerald-300">${{ row.profit }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pixelforge GET /v1/usage Reconciliation -->
      <div class="rounded-lg border border-[#222226] bg-[#111114] p-5 space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-white">Pixelforge GET /v1/usage Audit</h2>
            <p class="text-xs text-zinc-400">
              Local recorded provider cost: <span class="font-mono text-sky-300">${{ summary.provider_cost }}</span>
            </p>
          </div>
        </div>

        <div v-if="providerUsageError" class="rounded border border-red-500/30 bg-red-950/30 p-3 font-mono text-xs text-red-300">
          {{ providerUsageError }}
        </div>
        <pre v-else class="overflow-x-auto rounded border border-[#222226] bg-[#09090b] p-4 font-mono text-xs text-zinc-300">{{ JSON.stringify(providerUsage, null, 2) }}</pre>
      </div>
    </main>
  </div>
</template>