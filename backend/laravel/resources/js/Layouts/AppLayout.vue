<template>
  <div class="min-h-screen bg-[#F7F6F3] font-sans">
    <!-- ── Global nav ──────────────────────────────────────── -->
    <nav class="bg-[#1A1A2E] border-b border-white/10 sticky top-0 z-30">
      <div class="flex items-center justify-between px-6 h-11">
        <Link :href="route('projects.index')" class="flex items-center gap-2 group">
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

    <!-- ── Toast notifications ─────────────────────────────── -->
    <Teleport to="body">
      <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
        <TransitionGroup
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 translate-x-4 scale-95"
          enter-to-class="opacity-100 translate-x-0 scale-100"
          leave-active-class="transition duration-200 ease-in"
          leave-from-class="opacity-100 translate-x-0 scale-100"
          leave-to-class="opacity-0 translate-x-4 scale-95"
        >
          <div
            v-for="toast in toasts"
            :key="toast.id"
            class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-xl max-w-sm w-full border"
            :class="toastClass(toast.type)"
          >
            <!-- Icon -->
            <div class="shrink-0 mt-0.5">
              <svg v-if="toast.type === 'success'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              <svg v-else-if="toast.type === 'error'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              <svg v-else-if="toast.type === 'info'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
            </div>
            <!-- Message -->
            <p class="text-xs font-medium flex-1 leading-relaxed">{{ toast.message }}</p>
            <!-- Close -->
            <button @click="removeToast(toast.id)" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity mt-0.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </TransitionGroup>
      </div>
    </Teleport>

    <main>
      <slot />
    </main>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

defineProps({ title: { type: String, default: 'GeoLevel' } })

const page   = usePage()
const toasts = ref([])
let   nextId = 0

function addToast(type, message) {
  const id = ++nextId
  toasts.value.push({ id, type, message })
  setTimeout(() => removeToast(id), 4000)
}

function removeToast(id) {
  toasts.value = toasts.value.filter(t => t.id !== id)
}

function toastClass(type) {
  return {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
    error:   'bg-red-50 border-red-200 text-red-800',
    info:    'bg-blue-50 border-blue-200 text-blue-800',
    warning: 'bg-amber-50 border-amber-200 text-amber-800',
  }[type] ?? 'bg-slate-50 border-slate-200 text-slate-800'
}

// Watch flash dari server (setiap navigasi Inertia)
watch(
  () => page.props.flash,
  (flash) => {
    if (!flash) return
    // Format baru: { type, message }
    if (flash.type && flash.message) {
      addToast(flash.type, flash.message)
    }
    // Format lama: { success, error } — fallback
    if (flash.success) addToast('success', flash.success)
    if (flash.error)   addToast('error',   flash.error)
    if (flash.info)    addToast('info',     flash.info)
  },
  { deep: true }
)
</script>
