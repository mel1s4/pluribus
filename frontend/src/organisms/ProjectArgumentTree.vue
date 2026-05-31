<script setup>
import { computed } from 'vue'
import Button from '../atoms/Button.vue'
import ProjectArgumentNode from '../molecules/ProjectArgumentNode.vue'
import { buildChildrenMap, subtreeWeight } from '../utils/projectArgumentTree.js'
import { t } from '../i18n/i18n'

const props = defineProps({
  slug: { type: String, required: true },
  projectId: { type: Number, required: true },
  thesisTitle: { type: String, required: true },
  thesisBody: { type: String, default: '' },
  argumentsList: { type: Array, required: true },
})

const emit = defineEmits(['add-child', 'edit', 'delete'])

const childrenMap = computed(() => buildChildrenMap(props.argumentsList))
const roots = computed(() => childrenMap.value.get('__root') || [])

const rootWeights = computed(() => {
  const list = roots.value
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

function addRoot(stance) {
  emit('add-child', { parentId: null, stance })
}
</script>

<template>
  <div class="project-arg-tree">
    <section class="project-arg-tree__thesis" aria-labelledby="project-thesis-heading">
      <h2 id="project-thesis-heading" class="project-arg-tree__thesis-title">{{ thesisTitle }}</h2>
      <p v-if="thesisBody" class="project-arg-tree__thesis-body">{{ thesisBody }}</p>
    </section>

    <div class="project-arg-tree__toolbar">
      <Button type="button" @click="addRoot('pro')">{{ t('communityProjects.addTopPro') }}</Button>
      <Button type="button" @click="addRoot('con')">{{ t('communityProjects.addTopCon') }}</Button>
    </div>

    <div v-if="rootWeights.length" class="project-arg-tree__bar-wrap" role="img" :aria-label="t('communityProjects.topBranchWeightsAria')">
      <div class="project-arg-tree__bar-row">
        <div
          v-for="row in rootWeights"
          :key="row.arg.id"
          class="project-arg-tree__bar-seg"
          :class="row.arg.stance === 'con' ? 'project-arg-tree__bar-seg--con' : 'project-arg-tree__bar-seg--pro'"
          :style="{ flexGrow: String(row.pct) }"
          :title="row.arg.title"
        />
      </div>
    </div>

    <div class="project-arg-tree__roots">
      <ProjectArgumentNode
        v-for="c in roots"
        :key="c.id"
        :slug="slug"
        :project-id="projectId"
        :node="c"
        :flat-args="argumentsList"
        :depth="0"
        @add-child="(p) => emit('add-child', p)"
        @edit="(n) => emit('edit', n)"
        @delete="(n) => emit('delete', n)"
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.project-arg-tree__thesis {
  margin-bottom: 1rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: color-mix(in srgb, var(--bg) 92%, #1d4ed8);
}
.project-arg-tree__thesis-title {
  margin: 0 0 0.35rem;
  font-size: 1.1rem;
}
.project-arg-tree__thesis-body {
  margin: 0;
  line-height: 1.5;
  font-size: 0.95rem;
}
.project-arg-tree__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}
.project-arg-tree__bar-wrap {
  margin-bottom: 0.75rem;
}
.project-arg-tree__bar-row {
  display: flex;
  height: 0.55rem;
  border-radius: 0.3rem;
  overflow: hidden;
  background: color-mix(in srgb, var(--border) 40%, transparent);
}
.project-arg-tree__bar-seg {
  min-width: 2px;
}
.project-arg-tree__bar-seg--pro {
  background: color-mix(in srgb, #15803d 75%, #fff);
}
.project-arg-tree__bar-seg--con {
  background: color-mix(in srgb, #b91c1c 75%, #fff);
}
.project-arg-tree__roots {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
</style>
