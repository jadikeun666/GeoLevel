<template>
  <button
    @click="$emit('click')"
    class="px-4 py-3 text-xs font-semibold uppercase tracking-wider border-b-2 transition-colors"
    :class="active
      ? 'border-stone-800 text-stone-800'
      : 'border-transparent text-stone-400 hover:text-stone-600 hover:border-stone-300'"
  >
    <slot />
  </button>
</template>

<script setup>
defineProps({ active: { type: Boolean, default: false } })
defineEmits(['click'])
</script>