<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
  Sparkles,
  Plus,
  Trash2,
  Loader2,
  CheckCircle2,
  AlertCircle,
  ExternalLink,
  LogOut,
  DollarSign,
  Image as ImageIcon,
  User,
} from 'lucide-vue-next';

const props = defineProps({
  auth: Object,
  generations: Array,
  totalSpend: String,
  pricingConfig: Object,
});

const localGenerations = ref([...props.generations]);

watch(
  () => props.generations,
  (newVal) => {
    localGenerations.value = [...newVal];
  },
  { deep: true }
);

const form = useForm({
  prompt: '',
  size: '1024x1024',
  quality: 'medium',
  reference_urls: [],
});

const sizes = [
  { id: '1024x1024', label: '1024 × 1024', ratio: '1:1 Square' },
  { id: '1536x1024', label: '1536 × 1024', ratio: '3:2 Landscape' },
  { id: '1024x1536', label: '1024 × 1536', ratio: '2:3 Portrait' },
];

const qualities = [
  { id: 'low', label: 'Low', desc: 'Fast draft' },
  { id: 'medium', label: 'Medium', desc: 'Balanced' },
  { id: 'high', label: 'High', desc: 'Max detail' },
];

const addReferenceUrl = () => {
  if (form.reference_urls.length < 4) {
    form.reference_urls.push('');
  }
};

const removeReferenceUrl = (index) => {
  form.reference_urls.splice(index, 1);
};

// Exact integer-scaled 8-decimal USD math matching PixelforgeService.php
const toUnits = (str) => Math.round(parseFloat(str || '0') * 100_000_000);
const fromUnits = (units) => (units / 100_000_000).toFixed(8);

const costBreakdown = computed(() => {
  const baseUnits = toUnits(props.pricingConfig.baseRates[form.quality] || '0.04200000');
  const multBps = Math.round(parseFloat(props.pricingConfig.sizeMultipliers[form.size] || '1') * 100);
  const outputUnits = Math.floor((baseUnits * multBps) / 100);

  const trimmed = form.prompt.trim();
  const estTokens = trimmed.length > 0 ? Math.max(1, Math.ceil(trimmed.length / 4)) : 1;
  const promptUnits = estTokens * toUnits(props.pricingConfig.tokenRate);

  const validRefs = form.reference_urls.filter((u) => u.trim().length > 0).length;
  const refUnits = validRefs * toUnits(props.pricingConfig.referenceImageRate);

  const providerUnits = outputUnits + promptUnits + refUnits;
  const markupPct = props.pricingConfig.markupPercent ?? 25;
  const totalUserUnits = Math.floor((providerUnits * (100 + markupPct) + 50) / 100);
  const markupUnits = totalUserUnits - providerUnits;

  return {
    providerSubtotal: fromUnits(providerUnits),
    markup: fromUnits(markupUnits),
    markupPct,
    total: fromUnits(totalUserUnits),
    validRefCount: validRefs,
    estTokens,
  };
});

const submit = () => {
  form.post(route('generations.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('prompt', 'reference_urls');
    },
  });
};

// Non-overlapping background polling for queued / processing generations
let pollTimer = null;
let isPolling = false;

const pollPendingGenerations = async () => {
  if (isPolling) return;

  const pending = localGenerations.value.filter((g) =>
    ['queued', 'processing'].includes(g.status)
  );

  if (pending.length === 0) return;

  isPolling = true;
  let anyCompleted = false;

  try {
    for (const item of pending) {
      try {
        const res = await fetch(route('generations.sync', item.id), {
          headers: { Accept: 'application/json' },
        });
        if (!res.ok) continue;
        const updated = await res.json();

        const idx = localGenerations.value.findIndex((g) => g.id === item.id);
        if (idx !== -1) {
          localGenerations.value[idx] = updated;
        }
        if (['succeeded', 'failed'].includes(updated.status)) {
          anyCompleted = true;
        }
      } catch (_) {
        // Ignore transient network errors during polling
      }
    }

    if (anyCompleted) {
      router.reload({ only: ['totalSpend', 'generations'] });
    }
  } finally {
    isPolling = false;
  }
};

onMounted(() => {
  pollTimer = setInterval(pollPendingGenerations, 2500);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
  <Head title="Pixelforge Studio" />

  <div class="min-h-screen bg-[#09090b] text-[#ededef] font-sans antialiased selection:bg-sky-500/30 selection:text-sky-200">
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 border-b border-[#1f1f23] bg-[#09090b]/90 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-6">
          <Link :href="route('studio.index')" class="flex items-center gap-2.5">
            <div class="grid grid-cols-2 gap-0.5">
              <span class="h-2 w-2 rounded-[1px] bg-white"></span>
              <span class="h-2 w-2 rounded-[1px] bg-zinc-500"></span>
              <span class="h-2 w-2 rounded-[1px] bg-zinc-400"></span>
              <span class="h-2 w-2 rounded-[1px] bg-zinc-200"></span>
            </div>
            <span class="text-base font-semibold tracking-tight text-white">Pixelforge</span>
          </Link>

          <nav class="hidden md:flex items-center gap-5 text-sm text-zinc-400">
            <Link :href="route('studio.index')" class="text-white font-medium">
              Studio
            </Link>
            <Link :href="route('profile.edit')" class="hover:text-white transition">
              Profile
            </Link>
            <a
              href="https://pixelforge.promptedit.dev"
              target="_blank"
              rel="noopener noreferrer"
              class="hover:text-white transition"
            >
              API docs
            </a>
          </nav>
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
          <!-- Total User Spend Pill -->
          <div class="flex items-center gap-1.5 rounded-md border border-[#242429] bg-[#111114] px-3 py-1.5 text-xs">
            <DollarSign class="h-3.5 w-3.5 text-zinc-400" />
            <span class="hidden sm:inline text-zinc-400">Total Spent:</span>
            <span class="font-mono font-medium text-white">${{ totalSpend }}</span>
          </div>

          <Link
            :href="route('profile.edit')"
            class="hidden sm:inline-flex items-center gap-1.5 text-xs text-zinc-400 hover:text-white transition"
          >
            <User class="h-3.5 w-3.5" />
            {{ auth.user.email }}
          </Link>

          <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="inline-flex items-center gap-1.5 rounded-md bg-white px-3.5 py-1.5 text-xs font-semibold text-black hover:bg-zinc-200 transition"
          >
            <LogOut class="h-3.5 w-3.5" />
            Sign out
          </Link>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
        <!-- Left Column: Prompt Composer & Pre-Submit Cost Estimator -->
        <div class="lg:col-span-5 space-y-6">
          <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Create a generation</h1>
            <p class="mt-1 text-sm text-zinc-400">
              Configure your prompt, output dimensions, quality tier, and optional reference images.
            </p>
          </div>

          <form @submit.prevent="submit" class="space-y-5 rounded-lg border border-[#222226] bg-[#111114] p-5 shadow-xl">
            <!-- Prompt Input -->
            <div>
              <div class="flex items-center justify-between">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                  Prompt
                </label>
                <span class="font-mono text-[11px] text-zinc-500">
                  ~{{ costBreakdown.estTokens }} tokens
                </span>
              </div>
              <textarea
                v-model="form.prompt"
                rows="4"
                required
                placeholder="Isometric brutalist concrete observatory at twilight, volumetric fog, 35mm lens..."
                class="mt-2 w-full rounded-md border border-[#27272c] bg-[#09090b] p-3 text-sm text-white placeholder-zinc-600 focus:border-zinc-400 focus:outline-none focus:ring-0"
              />
              <p v-if="form.errors.prompt" class="mt-1.5 text-xs text-red-400">
                {{ form.errors.prompt }}
              </p>
            </div>

            <!-- Size Selector -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                Size
              </label>
              <div class="mt-2 grid grid-cols-3 gap-2">
                <button
                  v-for="s in sizes"
                  :key="s.id"
                  type="button"
                  @click="form.size = s.id"
                  :class="[
                    'flex flex-col items-start rounded-md border p-2.5 text-left transition',
                    form.size === s.id
                      ? 'border-white bg-[#1a1a1f] text-white'
                      : 'border-[#242429] bg-[#09090b] text-zinc-400 hover:border-zinc-700',
                  ]"
                >
                  <span class="font-mono text-xs font-medium">{{ s.label }}</span>
                  <span class="mt-0.5 text-[11px] text-zinc-500">{{ s.ratio }}</span>
                </button>
              </div>
            </div>

            <!-- Quality Selector -->
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                Quality
              </label>
              <div class="mt-2 grid grid-cols-3 gap-2">
                <button
                  v-for="q in qualities"
                  :key="q.id"
                  type="button"
                  @click="form.quality = q.id"
                  :class="[
                    'flex flex-col items-start rounded-md border p-2.5 text-left transition',
                    form.quality === q.id
                      ? 'border-white bg-[#1a1a1f] text-white'
                      : 'border-[#242429] bg-[#09090b] text-zinc-400 hover:border-zinc-700',
                  ]"
                >
                  <span class="text-xs font-semibold">{{ q.label }}</span>
                  <span class="mt-0.5 text-[11px] text-zinc-500">{{ q.desc }}</span>
                </button>
              </div>
            </div>

            <!-- Optional Reference Image URLs -->
            <div>
              <div class="flex items-center justify-between">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400">
                  Reference Image URLs <span class="text-zinc-600">(Optional)</span>
                </label>
                <button
                  v-if="form.reference_urls.length < 4"
                  type="button"
                  @click="addReferenceUrl"
                  class="inline-flex items-center gap-1 text-xs font-medium text-zinc-300 hover:text-white"
                >
                  <Plus class="h-3.5 w-3.5" />
                  Add URL
                </button>
              </div>

              <div v-if="form.reference_urls.length > 0" class="mt-2 space-y-2">
                <div
                  v-for="(url, index) in form.reference_urls"
                  :key="index"
                  class="flex items-center gap-2"
                >
                  <input
                    v-model="form.reference_urls[index]"
                    type="url"
                    placeholder="https://example.com/reference.png"
                    class="w-full rounded-md border border-[#27272c] bg-[#09090b] px-3 py-2 font-mono text-xs text-white placeholder-zinc-600 focus:border-zinc-400 focus:outline-none focus:ring-0"
                  />
                  <button
                    type="button"
                    @click="removeReferenceUrl(index)"
                    class="rounded-md border border-[#27272c] bg-[#09090b] p-2 text-zinc-400 hover:text-red-400"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
              <p v-else class="mt-1.5 text-xs text-zinc-500">
                No reference images attached.
              </p>
              <p v-if="form.errors['reference_urls.0']" class="mt-1.5 text-xs text-red-400">
                {{ form.errors['reference_urls.0'] }}
              </p>
            </div>

            <!-- Pre-Submit Cost Breakdown Box (Base + 25% Markup) -->
            <div class="rounded-md border border-[#26262b] bg-[#09090b] p-4 space-y-2">
              <div class="flex items-center justify-between text-xs text-zinc-400">
                <span>
                  Pixelforge base est. ({{ form.size }}, {{ form.quality }}, ~{{ costBreakdown.estTokens }} tok<template v-if="costBreakdown.validRefCount > 0">, {{ costBreakdown.validRefCount }} ref</template>)
                </span>
                <span class="font-mono text-zinc-300">${{ costBreakdown.providerSubtotal }}</span>
              </div>
              <div class="flex items-center justify-between text-xs text-zinc-400">
                <span>Platform fee ({{ costBreakdown.markupPct }}% markup)</span>
                <span class="font-mono text-zinc-300">+${{ costBreakdown.markup }}</span>
              </div>

              <div class="border-t border-[#1f1f24] pt-2.5 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-300">
                  Estimated Total (USD)
                </span>
                <span class="rounded border border-[#2b2b31] bg-[#141418] px-2 py-0.5 font-mono text-sm font-semibold text-white">
                  ${{ costBreakdown.total }}
                </span>
              </div>
              <p class="text-[11px] text-zinc-500">
                Final cost settles after render completes. Failed generations cost $0.00.
              </p>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="form.processing || !form.prompt.trim()"
              class="flex w-full items-center justify-center gap-2 rounded-md bg-white px-4 py-2.5 text-sm font-semibold text-black hover:bg-zinc-200 disabled:cursor-not-allowed disabled:opacity-50 transition"
            >
              <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
              <Sparkles v-else class="h-4 w-4" />
              <span>{{ form.processing ? 'Queuing Generation...' : `Generate Image · $${costBreakdown.total}` }}</span>
            </button>
          </form>

          <!-- Info Callout matching Pixelforge Docs Banner -->
          <div class="rounded-md border-l-2 border-sky-400 bg-[#081b24] p-4 text-xs leading-relaxed text-zinc-300">
            <p class="font-semibold text-white">Asynchronous Generation</p>
            <p class="mt-1 text-zinc-400">
              Requests are queued on <code class="rounded bg-[#0d2733] px-1.5 py-0.5 font-mono text-sky-300">pixelforge.promptedit.dev/v1</code> and update automatically via webhook or background polling once settled.
            </p>
          </div>
        </div>

        <!-- Right Column: User's Generation History & Cost Ledger -->
        <div class="lg:col-span-7 space-y-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-xl font-bold tracking-tight text-white">Your Generations</h2>
              <p class="mt-1 text-sm text-zinc-400">
                Completed renders and exact per-request billing records.
              </p>
            </div>
            <span class="rounded border border-[#242429] bg-[#111114] px-2.5 py-1 font-mono text-xs text-zinc-400">
              {{ localGenerations.length }} items
            </span>
          </div>

          <!-- Empty State -->
          <div
            v-if="localGenerations.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border border-dashed border-[#27272c] bg-[#111114]/50 py-20 text-center"
          >
            <ImageIcon class="h-10 w-10 text-zinc-600" />
            <p class="mt-3 text-sm font-medium text-zinc-300">No generations yet</p>
            <p class="mt-1 text-xs text-zinc-500">
              Write a prompt on the left to queue your first Pixelforge render.
            </p>
          </div>

          <!-- Generation Cards Grid -->
          <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div
              v-for="gen in localGenerations"
              :key="gen.id"
              class="flex flex-col overflow-hidden rounded-lg border border-[#222226] bg-[#111114]"
            >
              <!-- Image / Status Viewport -->
              <div class="relative aspect-square w-full bg-[#09090b] flex items-center justify-center overflow-hidden border-b border-[#1f1f24]">
                <img
                  v-if="gen.status === 'succeeded' && gen.image_url"
                  :src="gen.image_url"
                  :alt="gen.prompt"
                  class="h-full w-full object-cover"
                />

                <div
                  v-else-if="['queued', 'processing'].includes(gen.status)"
                  class="flex flex-col items-center gap-2.5 p-6 text-center"
                >
                  <Loader2 class="h-7 w-7 animate-spin text-sky-400" />
                  <span class="font-mono text-xs uppercase tracking-wider text-zinc-300">
                    {{ gen.status }}...
                  </span>
                  <span v-if="gen.pixelforge_id" class="font-mono text-[11px] text-zinc-500">
                    {{ gen.pixelforge_id }}
                  </span>
                </div>

                <div
                  v-else
                  class="flex flex-col items-center gap-2 p-6 text-center text-red-400"
                >
                  <AlertCircle class="h-7 w-7" />
                  <span class="text-xs font-medium">Generation Failed</span>
                  <span class="text-[11px] text-zinc-500 line-clamp-2">{{ gen.error_message }}</span>
                </div>

                <!-- Top-Right Status Badge -->
                <div class="absolute top-2.5 right-2.5">
                  <span
                    :class="[
                      'inline-flex items-center gap-1 rounded border px-2 py-0.5 font-mono text-[11px]',
                      gen.status === 'succeeded'
                        ? 'border-emerald-500/30 bg-emerald-950/80 text-emerald-300'
                        : gen.status === 'failed'
                        ? 'border-red-500/30 bg-red-950/80 text-red-300'
                        : 'border-sky-500/30 bg-sky-950/80 text-sky-300',
                    ]"
                  >
                    <CheckCircle2 v-if="gen.status === 'succeeded'" class="h-3 w-3" />
                    {{ gen.status }}
                  </span>
                </div>
              </div>

              <!-- Card Metadata & Cost Footer (Both Estimated & Actual) -->
              <div class="flex flex-1 flex-col justify-between p-4 space-y-3">
                <p class="text-xs leading-relaxed text-zinc-200 line-clamp-2" :title="gen.prompt">
                  {{ gen.prompt }}
                </p>

                <div class="space-y-1.5 pt-2 border-t border-[#1e1e23]">
                  <div class="flex items-center justify-between text-[11px] text-zinc-400">
                    <span class="font-mono">{{ gen.size }} · {{ gen.quality }}</span>
                    <a
                      v-if="gen.image_url"
                      :href="gen.image_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="inline-flex items-center gap-1 text-zinc-300 hover:text-white"
                    >
                      Open <ExternalLink class="h-3 w-3" />
                    </a>
                  </div>

                  <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-500">Estimated (incl. 25%)</span>
                    <span class="font-mono text-[11px] text-zinc-400">
                      ${{ gen.estimated_cost }}
                    </span>
                  </div>

                  <div class="flex items-center justify-between text-xs">
                    <span class="text-zinc-300 font-medium">Actual Cost</span>
                    <span
                      v-if="gen.actual_cost !== null && gen.actual_cost !== undefined"
                      :class="[
                        'rounded border px-2 py-0.5 font-mono text-[11px] font-semibold',
                        gen.status === 'failed'
                          ? 'border-zinc-800 bg-[#09090b] text-zinc-500'
                          : 'border-emerald-500/30 bg-emerald-950/40 text-emerald-300',
                      ]"
                    >
                      ${{ gen.actual_cost }}
                    </span>
                    <span v-else class="font-mono text-[11px] text-sky-400">
                      Pending...
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>