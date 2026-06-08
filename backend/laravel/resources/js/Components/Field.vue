<template>
  <div>
    <label class="block text-xs font-semibold text-stone-500 uppercase tracking-wider mb-1">
      {{ label }}<span v-if="required" class="text-red-400 ml-0.5">*</span>
    </label>
    <slot />
  </div>
</template>

<script setup>
defineProps({
  label:    { type: String, required: true },
  required: { type: Boolean, default: false },
})
</script>