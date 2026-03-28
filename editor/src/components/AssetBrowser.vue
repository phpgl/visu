<template>
  <div class="asset-browser">
    <div class="panel-header">Assets</div>

    <div class="breadcrumb">
      <span class="crumb" @click="navigate('')">resources</span>
      <template v-for="(seg, i) in pathSegments" :key="i">
        <span class="sep">/</span>
        <span class="crumb" @click="navigate(pathSegments.slice(0, i + 1).join('/'))">{{ seg }}</span>
      </template>
    </div>

    <div v-if="store.assetLoading" class="loading">Loading...</div>

    <div v-else class="file-list">
      <div v-if="store.assetPath" class="file-item dir" @click="navigateUp">
        <span class="file-icon">&#x2190;</span>
        <span class="file-name">..</span>
      </div>
      <div
        v-for="entry in store.assetEntries"
        :key="entry.path"
        class="file-item"
        :class="{ dir: entry.type === 'directory', selected: selectedAsset === entry.path }"
        @click="onEntryClick(entry)"
        @dblclick="onEntryDblClick(entry)"
      >
        <span class="file-icon">{{ iconFor(entry.type) }}</span>
        <span class="file-name">{{ entry.name }}</span>
        <span v-if="entry.size" class="file-size">{{ formatSize(entry.size) }}</span>
      </div>

      <div v-if="!store.assetEntries.length && !store.assetLoading" class="empty">
        Empty directory
      </div>
    </div>

    <div v-if="selectedAsset" class="preview-bar">
      <span class="preview-path">{{ selectedAsset }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useWorldStore } from '../stores/world.js'

const store = useWorldStore()
const selectedAsset = ref(null)

const pathSegments = computed(() =>
  store.assetPath ? store.assetPath.split('/').filter(Boolean) : []
)

onMounted(() => store.browseAssets(''))

function navigate(dir) {
  selectedAsset.value = null
  store.browseAssets(dir)
}

function navigateUp() {
  const parts = store.assetPath.split('/').filter(Boolean)
  parts.pop()
  navigate(parts.join('/'))
}

function onEntryClick(entry) {
  if (entry.type === 'directory') {
    navigate(entry.path)
  } else {
    selectedAsset.value = entry.path
  }
}

function onEntryDblClick(entry) {
  if (entry.type === 'directory') return
  // Could emit an event or copy path to clipboard
  navigator.clipboard?.writeText(entry.path)
}

function iconFor(type) {
  switch (type) {
    case 'directory': return '\u{1F4C1}'
    case 'image': return '\u{1F5BC}'
    case 'shader': return '\u{2728}'
    case 'model': return '\u{1F4E6}'
    case 'audio': return '\u{1F50A}'
    case 'font': return 'Aa'
    case 'json': return '{}'
    default: return '\u{1F4C4}'
  }
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

<style scoped>
.asset-browser { display: flex; flex-direction: column; height: 100%; }
.panel-header {
  padding: 6px 8px; border-bottom: 1px solid #333;
  font-size: 11px; font-weight: bold; color: #aaa;
  text-transform: uppercase; letter-spacing: .05em; flex-shrink: 0;
}
.breadcrumb {
  padding: 4px 8px; font-size: 10px; color: #666;
  border-bottom: 1px solid #222; flex-shrink: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.crumb { cursor: pointer; color: #7b8ff5; }
.crumb:hover { text-decoration: underline; }
.sep { margin: 0 2px; color: #444; }
.loading { padding: 12px 8px; color: #555; font-size: 11px; }
.file-list { flex: 1; overflow-y: auto; }
.file-item {
  display: flex; align-items: center; gap: 6px;
  padding: 4px 8px; cursor: pointer; border-bottom: 1px solid #1a1a2a;
  font-size: 12px;
}
.file-item:hover { background: #1e1e30; }
.file-item.selected { background: #2a2a4a; }
.file-item.dir { color: #7b8ff5; }
.file-icon { width: 18px; text-align: center; flex-shrink: 0; font-size: 11px; }
.file-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-size { color: #555; font-size: 10px; flex-shrink: 0; }
.empty { padding: 12px 8px; color: #444; font-size: 11px; }
.preview-bar {
  padding: 4px 8px; border-top: 1px solid #333;
  font-size: 10px; color: #888; flex-shrink: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.preview-path { color: #7b8ff5; }
</style>
