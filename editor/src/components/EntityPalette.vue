<template>
  <div class="entity-palette">
    <div class="panel-header">Entity Types</div>
    <div class="entity-list">
      <div
        v-for="type in entityTypes"
        :key="type.id"
        class="entity-item"
        :class="{ selected: store.selectedEntityType === type.id }"
        @click="selectType(type.id)"
      >
        <span class="icon">{{ type.icon }}</span>
        <span class="label">{{ type.label }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useWorldStore } from '../stores/world.js'

const store = useWorldStore()

const entityTypes = [
  { id: 'player_spawn',  icon: '★', label: 'Player Spawn' },
  { id: 'enemy_spawn',   icon: '☠', label: 'Enemy Spawn' },
  { id: 'item',          icon: '◈', label: 'Item' },
  { id: 'trigger',       icon: '⚡', label: 'Trigger Zone' },
  { id: 'light_point',   icon: '☀', label: 'Point Light' },
  { id: 'camera_hint',   icon: '⊡', label: 'Camera Hint' },
  { id: 'npc',           icon: '☻', label: 'NPC' },
  { id: 'prop',          icon: '◧', label: 'Prop' },
]

function selectType(id) {
  store.selectedEntityType = store.selectedEntityType === id ? null : id
  if (store.selectedEntityType) {
    store.setActiveTool('place_entity')
  }
}
</script>

<style scoped>
.entity-palette { display: flex; flex-direction: column; height: 100%; }
.panel-header {
  padding: 6px 8px; border-bottom: 1px solid #333;
  font-size: 11px; font-weight: bold; color: #aaa;
  text-transform: uppercase; letter-spacing: .05em;
}
.entity-list { flex: 1; overflow-y: auto; padding: 4px; }
.entity-item {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 8px; cursor: pointer; border-radius: 4px;
}
.entity-item:hover { background: #1e1e30; }
.entity-item.selected { background: #2a2a4a; outline: 1px solid #4a4aff; }
.icon { font-size: 14px; width: 20px; text-align: center; }
.label { font-size: 12px; }
</style>
