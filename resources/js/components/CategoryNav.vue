<template>
  <nav :class="scroll ? 'ncr-rail-scroll' : 'ncr-rail'">
    <button type="button" class="ncr-rail-item" :class="{ 'is-active': active === null }" @click="$emit('select', null)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 shrink-0 opacity-70">
        <rect x="3" y="3" width="7" height="7" rx="1" />
        <rect x="14" y="3" width="7" height="7" rx="1" />
        <rect x="3" y="14" width="7" height="7" rx="1" />
        <rect x="14" y="14" width="7" height="7" rx="1" />
      </svg>
      <span class="label">{{ __('All commands') }}</span>
      <span class="ncr-rail-count">{{ total }}</span>
    </button>

    <button
      v-for="category in categories"
      :key="category.name"
      type="button"
      class="ncr-rail-item"
      :class="{ 'is-active': active === category.name }"
      @click="$emit('select', category.name)"
    >
      <span class="ncr-rail-dot" :style="{ backgroundColor: categoryColor(category.name) }"></span>
      <span class="label">{{ category.name }}</span>
      <span class="ncr-rail-count">{{ category.count }}</span>
    </button>
  </nav>
</template>

<script setup>
import { categoryColor } from '../util/colors'
import { __ } from '../util/translate'

defineProps({
  categories: { type: Array, default: () => [] },
  active: { type: [String, null], default: null },
  total: { type: Number, default: 0 },
  scroll: { type: Boolean, default: false },
})

defineEmits(['select'])
</script>
