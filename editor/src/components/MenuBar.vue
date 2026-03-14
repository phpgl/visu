<template>
  <div class="menu-bar">
    <span class="logo">VISU World Editor</span>

    <div class="menu-group">
      <button @click="onNew">New</button>
      <button @click="showOpen = !showOpen">Open</button>
      <button @click="onSave" :disabled="!store.world" :class="{ dirty: store.isDirty }">
        Save{{ store.isDirty ? '*' : '' }}
      </button>
    </div>

    <div class="menu-group tools">
      <button v-for="t in tools" :key="t.id"
        :class="{ active: store.selectedTool === t.id }"
        :title="t.label"
        @click="store.setActiveTool(t.id)">
        {{ t.icon }}
      </button>
    </div>

    <div class="menu-group history">
      <button @click="store.undo" title="Undo (Ctrl+Z)">↩</button>
      <button @click="store.redo" title="Redo (Ctrl+Y)">↪</button>
    </div>

    <div v-if="store.world" class="world-name">
      {{ store.world.meta.name }}
    </div>

    <!-- Open world popup -->
    <div v-if="showOpen" class="popup" @click.self="showOpen = false">
      <div class="popup-box">
        <h3>Open World</h3>
        <div class="new-world-row">
          <input v-model="newName" placeholder="New world name" @keyup.enter="onCreateNew" />
          <button @click="onCreateNew">Create</button>
        </div>
        <div v-if="store.worldList.length" class="world-list">
          <div v-for="w in store.worldList" :key="w.name" class="world-item"
            @click="onOpen(w.name)">
            <span>{{ w.name }}</span>
            <small>{{ new Date(w.modified).toLocaleDateString() }}</small>
          </div>
        </div>
        <p v-else class="empty">No saved worlds found.</p>
        <button class="close-btn" @click="showOpen = false">✕</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useWorldStore } from '../stores/world.js'

const store = useWorldStore()
const showOpen = ref(false)
const newName = ref('')

const tools = [
  { id: 'select',       icon: '↖', label: 'Select' },
  { id: 'place_tile',   icon: '▣', label: 'Place Tile' },
  { id: 'place_entity', icon: '⊕', label: 'Place Entity' },
  { id: 'erase',        icon: '✕', label: 'Erase' },
]

onMounted(() => store.fetchWorldList())

function onNew() {
  if (store.isDirty && !confirm('Discard unsaved changes?')) return
  store.newWorld('Untitled')
}

async function onOpen(name) {
  if (store.isDirty && !confirm('Discard unsaved changes?')) return
  await store.loadWorld(name)
  showOpen.value = false
}

async function onCreateNew() {
  const name = newName.value.trim() || 'Untitled'
  if (store.isDirty && !confirm('Discard unsaved changes?')) return
  store.newWorld(name)
  showOpen.value = false
  newName.value = ''
}

async function onSave() {
  await store.saveCurrentWorld()
}
</script>

<style scoped>
.menu-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 12px;
  height: 36px;
  background: #0f0f1a;
  border-bottom: 1px solid #333;
  flex-shrink: 0;
  position: relative;
}
.logo { font-weight: bold; color: #7b8ff5; margin-right: 8px; }
.menu-group { display: flex; gap: 4px; }
button {
  background: #2a2a3e; border: 1px solid #444; color: #ddd;
  padding: 3px 10px; cursor: pointer; border-radius: 3px; font-size: 12px;
}
button:hover { background: #3a3a5e; }
button.active { background: #4a4aff; border-color: #7b8ff5; color: #fff; }
button.dirty { border-color: #f5a623; }
button:disabled { opacity: 0.4; cursor: not-allowed; }
.world-name { margin-left: auto; color: #aaa; font-size: 11px; }
.popup {
  position: fixed; inset: 0; background: rgba(0,0,0,.6);
  display: flex; align-items: center; justify-content: center; z-index: 999;
}
.popup-box {
  background: #1e1e30; border: 1px solid #555; border-radius: 6px;
  padding: 20px; min-width: 320px; position: relative;
}
.popup-box h3 { margin-bottom: 12px; }
.new-world-row { display: flex; gap: 6px; margin-bottom: 12px; }
.new-world-row input {
  flex: 1; background: #2a2a3e; border: 1px solid #444; color: #eee;
  padding: 4px 8px; border-radius: 3px;
}
.world-list { max-height: 200px; overflow-y: auto; }
.world-item {
  display: flex; justify-content: space-between; padding: 6px 8px;
  cursor: pointer; border-radius: 3px;
}
.world-item:hover { background: #2a2a3e; }
.world-item small { color: #888; }
.empty { color: #666; font-size: 11px; }
.close-btn {
  position: absolute; top: 8px; right: 8px;
  background: transparent; border: none; color: #888; font-size: 14px; cursor: pointer;
}
</style>
