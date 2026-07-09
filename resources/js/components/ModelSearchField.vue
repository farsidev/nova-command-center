<template>
  <div class="ncr-model-search">
    <input
      :id="fieldId"
      ref="inputRef"
      v-model="query"
      type="text"
      autocomplete="off"
      class="w-full form-control form-input form-input-bordered"
      :class="{ 'ncr-field-invalid': error }"
      :aria-invalid="!!error"
      :aria-expanded="open"
      role="combobox"
      aria-autocomplete="list"
      :placeholder="placeholder || __('Type to search…')"
      @focus="onFocus"
      @input="onType"
      @keydown="onKeydown"
      @blur="onBlur"
    />

    <svg v-if="loading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ncr-model-search-icon ncr-spin">
      <path d="M21 12a9 9 0 1 1-6.219-8.56" />
    </svg>
    <button v-else-if="modelValue" type="button" class="ncr-model-search-icon ncr-model-search-clear" :aria-label="__('Clear')" @mousedown.prevent="clear">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
      </svg>
    </button>

    <ul v-if="open" class="ncr-model-search-results" :style="listStyle">
      <li v-if="loading" class="ncr-model-search-empty">{{ __('Searching…') }}</li>
      <li v-else-if="results.length === 0" class="ncr-model-search-empty">{{ __('No matches') }}</li>
      <li
        v-for="(result, index) in results"
        v-else
        :key="result.value"
        class="ncr-model-search-option"
        :class="{ 'ncr-model-search-option-active': index === activeIndex }"
        @mousedown.prevent="select(result)"
      >
        {{ result.label }}
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import api from '../util/api'
import { __ } from '../util/translate'

const props = defineProps({
  fieldId: { type: String, required: true },
  commandId: { type: String, required: true },
  variableName: { type: String, required: true },
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const inputRef = ref(null)
const query = ref(props.modelValue || '')
const results = ref([])
const loading = ref(false)
const open = ref(false)
const activeIndex = ref(-1)
const listStyle = ref({})

let debounceTimer = null
let requestToken = 0

// The results list is position:fixed (see tool.css) so the modal body's
// overflow can't clip it — measure the input and anchor the list to it,
// re-measuring on any scroll (capture phase catches the modal body's own
// scrolling) and on resize. Flips above the input when the viewport space
// below is too tight for the list's max-height.
const LIST_MAX_HEIGHT = 224 // must track max-height in .ncr-model-search-results
const LIST_GAP = 4

function positionList() {
  const rect = inputRef.value?.getBoundingClientRect()
  if (!rect) return

  const spaceBelow = window.innerHeight - rect.bottom
  const style = { left: `${rect.left}px`, width: `${rect.width}px` }

  if (spaceBelow < LIST_MAX_HEIGHT + LIST_GAP && rect.top > spaceBelow) {
    style.bottom = `${window.innerHeight - rect.top + LIST_GAP}px`
  } else {
    style.top = `${rect.bottom + LIST_GAP}px`
  }

  listStyle.value = style
}

watch(open, (isOpen) => {
  if (isOpen) {
    positionList()
    window.addEventListener('scroll', positionList, true)
    window.addEventListener('resize', positionList)
  } else {
    window.removeEventListener('scroll', positionList, true)
    window.removeEventListener('resize', positionList)
  }
})

watch(
  () => props.modelValue,
  (value) => {
    if (!value) query.value = ''
  },
)

// A pre-filled default (a stored value, not something the operator just
// picked from the results list) has no label to show yet — resolve it once
// on mount so the field shows a friendly name instead of the raw id.
onMounted(() => {
  if (props.modelValue) resolveLabel(props.modelValue)
})

async function resolveLabel(value) {
  loading.value = true

  try {
    const response = await api.searchVariable(props.commandId, props.variableName, '', { value })
    const results = Array.isArray(response.data.results) ? response.data.results : []
    query.value = results.length ? results[0].label : value
  } catch {
    query.value = value
  } finally {
    loading.value = false
  }
}

function onFocus() {
  open.value = true
  search(query.value)
}

function onType() {
  if (props.modelValue) emit('update:modelValue', '')
  open.value = true
  activeIndex.value = -1
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => search(query.value), 300)
}

async function search(term) {
  loading.value = true
  const token = ++requestToken

  try {
    const response = await api.searchVariable(props.commandId, props.variableName, term)
    if (token !== requestToken) return
    results.value = Array.isArray(response.data.results) ? response.data.results : []
  } catch {
    if (token !== requestToken) return
    results.value = []
  } finally {
    if (token === requestToken) loading.value = false
  }
}

function select(result) {
  query.value = result.label
  results.value = []
  open.value = false
  emit('update:modelValue', result.value)
}

function clear() {
  query.value = ''
  results.value = []
  emit('update:modelValue', '')
  inputRef.value?.focus()
}

function onKeydown(event) {
  if (!open.value) return

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, results.value.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, -1)
  } else if (event.key === 'Enter' && activeIndex.value >= 0 && results.value[activeIndex.value]) {
    event.preventDefault()
    select(results.value[activeIndex.value])
  } else if (event.key === 'Escape') {
    open.value = false
  }
}

function onBlur() {
  // Delayed so a mousedown on an option registers before the list closes.
  setTimeout(() => (open.value = false), 150)
}

onBeforeUnmount(() => {
  clearTimeout(debounceTimer)
  window.removeEventListener('scroll', positionList, true)
  window.removeEventListener('resize', positionList)
})
</script>
