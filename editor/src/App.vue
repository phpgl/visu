<template>
  <div class="app">
    <MenuBar />

    <!-- Tab bar -->
    <div class="tab-bar">
      <button
        class="tab"
        :class="{ active: activeTab === 'world' }"
        @click="activeTab = 'world'"
      >World Editor</button>
      <button
        class="tab"
        :class="{ active: activeTab === 'ui' }"
        @click="activeTab = 'ui'"
      >UI Layout Editor</button>
      <span class="ws-indicator" :class="wsState" :title="'WebSocket: ' + wsState">
        {{ wsState === 'connected' ? 'WS' : wsState === 'connecting' ? '...' : '--' }}
      </span>
    </div>

    <!-- World Editor Tab -->
    <div class="main" v-show="activeTab === 'world'">
      <!-- Left panel: Layers + Entity/Tileset palette -->
      <div class="sidebar-left">
        <div class="panel layers-panel">
          <LayerPanel />
        </div>
        <div class="panel bottom-palette">
          <EntityPalette v-if="isEntityLayerActive" />
          <TilesetPanel v-else />
        </div>
      </div>

      <!-- Center: Canvas -->
      <EditorCanvas />

      <!-- Right panel: Inspector + Asset Browser -->
      <div class="sidebar-right">
        <div class="panel inspector-section">
          <InspectorPanel />
        </div>
        <div class="panel asset-section">
          <AssetBrowser />
        </div>
      </div>
    </div>

    <!-- UI Layout Editor Tab -->
    <div class="main" v-show="activeTab === 'ui'">
      <UILayoutEditor />
    </div>

    <div class="status-bar">
      <span v-if="activeTab === 'world' && store.world">
        {{ store.world.meta.name }}
        &nbsp;·&nbsp;
        {{ store.world.layers.length }} layers
        &nbsp;·&nbsp;
        Tile: {{ store.world.meta.tileSize }}px
        &nbsp;·&nbsp;
        <span v-if="store.isDirty" class="dirty">Unsaved changes</span>
        <span v-else class="saved">Saved</span>
      </span>
      <span v-else-if="activeTab === 'world'" class="no-world">No world open</span>
      <span v-else class="no-world">UI Layout Editor</span>
      <span class="shortcuts">V Select · B Tile · E Entity · X Erase · Del Delete · Ctrl+D Duplicate</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useWorldStore } from './stores/world.js'
import * as ws from './ws.js'
import MenuBar from './components/MenuBar.vue'
import LayerPanel from './components/LayerPanel.vue'
import EntityPalette from './components/EntityPalette.vue'
import TilesetPanel from './components/TilesetPanel.vue'
import EditorCanvas from './components/EditorCanvas.vue'
import InspectorPanel from './components/InspectorPanel.vue'
import AssetBrowser from './components/AssetBrowser.vue'
import UILayoutEditor from './components/UILayoutEditor.vue'

const store = useWorldStore()
const activeTab = ref('world')
const wsState = ref('disconnected')

const isEntityLayerActive = computed(() =>
  store.activeLayer?.type === 'entity'
)

onMounted(() => {
  store.fetchConfig()

  // Connect WebSocket for live-preview
  ws.connect()
  ws.on('_connected', () => { wsState.value = 'connected' })
  ws.on('_disconnected', () => { wsState.value = 'disconnected' })
})

onUnmounted(() => {
  ws.disconnect()
})
</script>

<style scoped>
.app {
  display: flex; flex-direction: column; width: 100%; height: 100%;
  overflow: hidden;
}
.tab-bar {
  display: flex; align-items: center; gap: 0;
  background: #0f0f1a; border-bottom: 1px solid #333;
  flex-shrink: 0; padding: 0 8px;
}
.tab {
  padding: 5px 14px; background: transparent; border: none;
  color: #666; cursor: pointer; font-size: 11px;
  border-bottom: 2px solid transparent;
}
.tab:hover { color: #aaa; }
.tab.active { color: #7b8ff5; border-bottom-color: #7b8ff5; }
.ws-indicator {
  margin-left: auto; font-size: 9px; padding: 2px 6px;
  border-radius: 3px; font-weight: bold;
}
.ws-indicator.connected { color: #4CAF50; }
.ws-indicator.connecting { color: #f5a623; }
.ws-indicator.disconnected { color: #555; }
.main {
  flex: 1; display: flex; overflow: hidden;
}
.sidebar-left {
  width: 200px; flex-shrink: 0;
  display: flex; flex-direction: column;
  border-right: 1px solid #2a2a3e;
  overflow: hidden;
}
.sidebar-right {
  width: 240px; flex-shrink: 0;
  display: flex; flex-direction: column;
  border-left: 1px solid #2a2a3e;
  overflow: hidden;
}
.panel { border-bottom: 1px solid #2a2a3e; overflow: hidden; }
.layers-panel { height: 45%; }
.bottom-palette { flex: 1; overflow: auto; }
.inspector-section { height: 55%; overflow: auto; }
.asset-section { flex: 1; overflow: hidden; }
.status-bar {
  height: 22px; background: #0f0f1a; border-top: 1px solid #222;
  display: flex; align-items: center; padding: 0 12px;
  font-size: 11px; color: #666; flex-shrink: 0;
  justify-content: space-between;
}
.dirty { color: #f5a623; }
.saved { color: #4CAF50; }
.no-world { color: #444; }
.shortcuts { color: #444; font-size: 10px; }
</style>
