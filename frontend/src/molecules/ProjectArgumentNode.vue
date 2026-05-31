<script setup>
import { computed, ref } from 'vue'
import ProjectArgumentNode from './ProjectArgumentNode.vue'
import Button from '../atoms/Button.vue'
import { buildChildrenMap, subtreeWeight } from '../utils/projectArgumentTree.js'
import { t } from '../i18n/i18n'

const props = defineProps({
  slug: { type: String, required: true },
  projectId: { type: Number, required: true },
  node: { type: Object, required: true },
  flatArgs: { type: Array, required: true },
  depth: { type: Number, default: 0 },
})

const emit = defineEmits(['add-child', 'edit', 'delete'])

const expanded = ref(props.depth < 2)

const childrenMap = computed(() => buildChildrenMap(props.flatArgs))
const children = computed(() => childrenMap.value.get(props.node.id) || [])

const childWeights = computed(() => {
  const list = children.value
  if (!list.length) return []
  const memo = new Map()
  const weights = list.map((c) => ({
    arg: c,
    w: subtreeWeight(c.id, childrenMap.value, memo),
  }))
  const sum = weights.reduce((a, b) => a + b.w, 0) || 1
  return weights.map((row) => ({
    ...row,
    pct: (100 * row.w) / sum,
  }))
})

const stanceClass = computed(() =>
  props.node.stance === 'con' ? 'project-arg-node--con' : 'project-arg-node--pro',
)

function toggle() {
  expanded.value = !expanded.value
}

function onAdd(stance) {
  emit('add-child', { parentId: props.node.id, stance })
}
</script>

<template>
  <div class="project-arg-node" :class="stanceClass" :style="{ '--depth': String(depth) }">
    <div class="project-arg-node__head">
      <button type="button" class="project-arg-node__toggle" :aria-expanded="expanded" @click="toggle">
        <span class="project-arg-node__stance">{{ node.stance === 'con' ? t('communityProjects.stanceCon') : t('communityProjects.stancePro') }}</span>
        <span class="project-arg-node__title">{{ node.title }}</span>
      </button>
      <span v-if="node.author" class="project-arg-node__author">{{ node.author.name }}</span>
      <div class="project-arg-node__actions">
        <Button type="button" class="project-arg-node__btn" @click="onAdd('pro')">{{ t('communityProjects.addPro') }}</Button>
        <Button type="button" class="project-arg-node__btn" @click="onAdd('con')">{{ t('communityProjects.addCon') }}</Button>
        <Button v-if="node.can_update" type="button" class="project-arg-node__btn" @click="emit('edit', node)">
          {{ t('communityProjects.edit') }}
        </Button>
        <Button v-if="node.can_delete" type="button" class="project-arg-node__btn project-arg-node__btn--danger" @click="emit('delete', node)">
          {{ t('communityProjects.delete') }}
        </Button>
      </div>
    </div>
    <p v-if="node.body" class="project-arg-node__body">{{ node.body }}</p>

    <div v-if="childWeights.length" class="project-arg-node__bar-wrap" role="img" :aria-label="t('communityProjects.branchWeightsAria')">
      <div class="project-arg-node__bar-row">
        <div
          v-for="row in childWeights"
          :key="row.arg.id"
          class="project-arg-node__bar-seg"
          :class="row.arg.stance === 'con' ? 'project-arg-node__bar-seg--con' : 'project-arg-node__bar-seg--pro'"
          :style="{ flexGrow: String(row.pct) }"
          :title="row.arg.title"
        />
      </div>
    </div>

    <div v-show="expanded && children.length" class="project-arg-node__kids">
      <ProjectArgumentNode
        v-for="c in children"
        :key="c.id"
        :slug="slug"
        :project-id="projectId"
        :node="c"
        :flat-args="flatArgs"
        :depth="depth + 1"
        @add-child="(p) => emit('add-child', p)"
        @edit="(n) => emit('edit', n)"
        @delete="(n) => emit('delete', n)"
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.project-arg-node {
  margin-left: calc(var(--depth, 0) * 0.65rem);
  padding: 0.5rem 0 0.35rem;
  border-left: 2px solid color-mix(in srgb, var(--border) 80%, transparent);
  padding-left: 0.55rem;
}
.project-arg-node--pro {
  border-left-color: color-mix(in srgb, #15803d 55%, var(--border));
}
.project-arg-node--con {
  border-left-color: color-mix(in srgb, #b91c1c 55%, var(--border));
}
.project-arg-node__head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.35rem 0.75rem;
}
.project-arg-node__toggle {
  flex: 1 1 12rem;
  text-align: left;
  border: none;
  background: transparent;
  font: inherit;
  cursor: pointer;
  padding: 0;
  color: inherit;
}
.project-arg-node__stance {
  display: inline-block;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-right: 0.35rem;
  opacity: 0.85;
}
.project-arg-node__title {
  font-weight: 600;
}
.project-arg-node__author {
  font-size: 0.85rem;
  opacity: 0.75;
  white-space: nowrap;
}
.project-arg-node__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}
.project-arg-node__btn {
  font-size: 0.78rem;
  padding: 0.2rem 0.45rem;
}
.project-arg-node__btn--danger {
  color: #b91c1c;
}
.project-arg-node__body {
  margin: 0.35rem 0 0;
  font-size: 0.9rem;
  line-height: 1.45;
  opacity: 0.9;
}
.project-arg-node__bar-wrap {
  margin-top: 0.45rem;
}
.project-arg-node__bar-row {
  display: flex;
  height: 0.45rem;
  border-radius: 0.25rem;
  overflow: hidden;
  background: color-mix(in srgb, var(--border) 40%, transparent);
}
.project-arg-node__bar-seg {
  min-width: 2px;
  transition: flex-grow 0.2s ease;
}
.project-arg-node__bar-seg--pro {
  background: color-mix(in srgb, #15803d 75%, #fff);
}
.project-arg-node__bar-seg--con {
  background: color-mix(in srgb, #b91c1c 75%, #fff);
}
.project-arg-node__kids {
  margin-top: 0.25rem;
}
</style>
