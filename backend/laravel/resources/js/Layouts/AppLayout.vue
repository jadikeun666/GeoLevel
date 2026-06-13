<template>
  <div class="min-h-screen bg-stone-50 font-mono">

    <!-- ── Global nav ──────────────────────────────────────── -->
    <nav class="border-b border-stone-200 bg-white sticky top-0 z-30">
      <div class="flex items-center justify-between px-6 h-11">

        <!-- Brand -->
        <Link :href="route('projects.index')" class="flex items-center gap-2 group">
          <span class="text-stone-400 text-xs tracking-widest uppercase group-hover:text-stone-700 transition-colors">
            GeoLevel
          </span>
        </Link>

        <!-- Right: user + logout -->
        <div class="flex items-center gap-4 text-xs text-stone-500">
          <span class="hidden sm:inline truncate max-w-[160px]">{{ page.props.auth?.user?.name }}</span>
          <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="text-stone-400 hover:text-stone-700 transition-colors"
          >
            Keluar
          </Link>
        </div>
      </div>
    </nav>

    <!-- ── Flash messages ──────────────────────────────────── -->
    <div
      v-if="flash.success"
      class="bg-green-50 border-b border-green-200 px-6 py-2 text-xs text-green-700 flex items-center gap-2"
    >
      <span>✓</span> {{ flash.success }}
    </div>
    <div
      v-if="flash.error"
      class="bg-red-50 border-b border-red-200 px-6 py-2 text-xs text-red-700 flex items-center gap-2"
    >
      <span>✗</span> {{ flash.error }}
    </div>

    <!-- ── Page content ────────────────────────────────────── -->
    <main>
      <slot />
    </main>

  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

defineProps({
  title: { type: String, default: 'GeoLevel' },
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})
</script>