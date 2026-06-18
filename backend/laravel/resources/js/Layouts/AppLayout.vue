<template>
  <div class="min-h-screen bg-[#F7F6F3] font-sans">

    <!-- ── Global nav ──────────────────────────────────────── -->
    <nav class="bg-[#1A1A2E] border-b border-white/10 sticky top-0 z-30">
      <div class="flex items-center justify-between px-6 h-11">
        <Link :href="route('projects.index')" class="flex items-center gap-2 group">
          <!-- Mini grid icon -->
          <svg class="w-5 h-5 text-blue-400" viewBox="0 0 20 20" fill="none">
            <rect x="2" y="2" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
            <rect x="11" y="2" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5" opacity=".5"/>
            <rect x="2" y="11" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5" opacity=".5"/>
            <rect x="11" y="11" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5" opacity=".3"/>
          </svg>
          <span class="font-mono text-xs tracking-[0.2em] text-white/80 group-hover:text-white transition-colors uppercase">GeoLevel</span>
        </Link>

        <div class="flex items-center gap-4">
          <span class="hidden sm:block font-mono text-[11px] text-slate-400 truncate max-w-[160px]">{{ page.props.auth?.user?.name }}</span>
          <Link
            :href="route('logout')"
            method="post"
            as="button"
            class="font-mono text-[11px] text-slate-500 hover:text-white transition-colors px-2 py-1 rounded hover:bg-white/10"
          >Keluar</Link>
        </div>
      </div>
    </nav>

    <!-- ── Flash messages ──────────────────────────────────── -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="flash.success"
        class="bg-emerald-600 px-6 py-2.5 text-xs text-white flex items-center gap-2 font-medium">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        {{ flash.success }}
      </div>
    </Transition>
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="flash.error"
        class="bg-red-600 px-6 py-2.5 text-xs text-white flex items-center gap-2 font-medium">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ flash.error }}
      </div>
    </Transition>

    <main>
      <slot />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

defineProps({ title: { type: String, default: 'GeoLevel' } })

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})
</script>