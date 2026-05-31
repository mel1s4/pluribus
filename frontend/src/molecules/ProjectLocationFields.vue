<script setup>
import PlaceLocationPicker from '../organisms/PlaceLocationPicker.vue'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="project-location-fields">
    <PlaceLocationPicker :model-value="props.modelValue" @update:model-value="emit('update:modelValue', $event)" />
  </div>
</template>

<style lang="scss" scoped>
.project-location-fields {
  margin-top: 0.25rem;
}
</style>
