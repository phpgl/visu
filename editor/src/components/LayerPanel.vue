<template>
  <div class="layer-panel">
    <div class="panel-header">
      <span>Layers</span>
      <div class="header-btns">
        <button title="Add tile layer" @click="store.addLayer('tile')">+T</button>
        <button title="Add entity layer" @click="store.addLayer('entity')">+E</button>
      </div>
    </div>

    <div v-if="!store.world" class="empty">No world open</div>

    <div v-else class="layer-list">
      <div
        v-for="layer in [...store.world.layers].reverse()"
        :key="layer.id"
        class="layer-item"
        :class="{ active: store.activeLayerId === layer.id, locked: layer.locked }"
        @click="store.setActiveLayer(layer.id)"
      >
        <span class="layer-type" :class="layer.type">{{ layer.type === 'tile' ? 'T' : 'E' }}</span>
        <input v-if="editingLayerId === layer.id"
          class="layer-name-input"
          :value="layer.name"
          @blur="finishRename(layer.id, $event.target.value)"
          @keyup.enter="finishRename(layer.id, $event.target.value)"
          @keyup.escape="editingLayerId = null"
          ref="renameInput"
          @click.stop
        />
        <span v-else class="layer-name" @dblclick.stop="startRename(layer.id)">{{ layer.name }}</span>
        <div class="layer-actions">
          <button
            title="Move up"
            @click.stop="store.moveLayerUp(layer.id)"
          >^</button>
          <button
            title="Move down"
            @click.stop="store.moveLayerDown(layer.id)"
          >v</button>
          <button
            :title="layer.visible ? 'Hide' : 'Show'"
            :class="{ dim: !layer.visible }"
            @click.stop="store.toggleLayerVisibility(layer.id)"
          >E</button>
          <button
            :title="layer.locked ? 'Unlock' : 'Lock'"
            :class="{ dim: !layer.locked }"
            @click.stop="store.toggleLayerLock(layer.id)"
          >L</button>
          <button
            title="Delete layer"
            class="del"
            @click.stop="onDelete(layer.id)"
          >x</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { useWorldStore } from '../stores/world.js'

const store = useWorldStore()
const editingLayerId = ref(null)
const renameInput = ref(null)

function startRename(id) {
  editingLayerId.value = id
  nextTick(() => {
    const input = document.querySelector('.layer-name-input')
    if (input) { input.focus(); input.select() }
  })
}

function finishRename(id, name) {
  if (name.trim()) {
    store.renameLayer(id, name.trim())
  }
  editingLayerId.value = null
}

function onDelete(id) {
  if (confirm('Delete this layer?')) store.removeLayer(id)
}
</script>

<style scoped>
.layer-panel { display: flex; flex-direction: column; height: 100%; }
.panel-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 6px 8px; border-bottom: 1px solid #333; font-size: 11px;
  font-weight: bold; color: #aaa; text-transform: uppercase; letter-spacing: .05em;
}
.header-btns { display: flex; gap: 3px; }
.header-btns button {
  background: #2a2a3e; border: 1px solid #444; color: #ccc;
  padding: 2px 6px; cursor: pointer; border-radius: 3px; font-size: 10px;
}
.header-btns button:hover { background: #3a3a5e; }
.layer-list { flex: 1; overflow-y: auto; }
.empty { padding: 12px 8px; color: #555; font-size: 11px; }
.layer-item {
  display: flex; align-items: center; gap: 6px;
  padding: 5px 8px; cursor: pointer; border-bottom: 1px solid #222;
}
.layer-item:hover { background: #1e1e30; }
.layer-item.active { background: #2a2a4a; }
.layer-item.locked { opacity: 0.6; }
.layer-type {
  font-size: 9px; font-weight: bold; padding: 1px 4px; border-radius: 2px;
  flex-shrink: 0;
}
.layer-type.tile { background: #2a4a2a; color: #6f6; }
.layer-type.entity { background: #2a2a4a; color: #88f; }
.layer-name { flex: 1; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.layer-name-input {
  flex: 1; font-size: 12px; background: #2a2a3e; border: 1px solid #4a4aff;
  color: #eee; padding: 1px 4px; border-radius: 2px; outline: none;
}
.layer-actions { display: flex; gap: 2px; }
.layer-actions button {
  background: transparent; border: none; cursor: pointer;
  font-size: 10px; padding: 1px 3px; opacity: 0.6; color: #ccc;
}
.layer-actions button:hover { opacity: 1; }
.layer-actions button.dim { opacity: 0.3; }
.layer-actions button.del { color: #f66; }
</style>
