import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import * as api from '../api.js'

function makeDefaultWorld(name = 'Untitled') {
  const now = new Date().toISOString()
  return {
    version: '1.0',
    meta: { name, type: '2d_topdown', tileSize: 32, created: now, modified: now },
    camera: { position: { x: 0, y: 0 }, zoom: 1.0 },
    layers: [
      { id: 'bg', name: 'Background', type: 'tile', visible: true, locked: false, tiles: {} },
      { id: 'entities', name: 'Entities', type: 'entity', visible: true, locked: false, entities: [] },
    ],
    lights: [],
    tilesets: [],
  }
}

export const useWorldStore = defineStore('world', () => {
  // ─── State ────────────────────────────────────────────────────────────────
  const world = ref(null)              // current WorldFile data
  const worldName = ref(null)          // filename slug (no extension)
  const isDirty = ref(false)
  const activeLayerId = ref(null)
  const selectedTool = ref('select')   // 'select' | 'place_tile' | 'place_entity' | 'erase'
  const selectedEntityType = ref(null)
  const selectedTile = ref(null)       // { tilesetId, tx, ty }
  const selectedEntityId = ref(null)   // id within entity layer
  const worldList = ref([])
  const config = ref({ tileSize: 32, gridWidth: 32, gridHeight: 32 })

  // undo/redo
  const history = ref([])
  const historyIndex = ref(-1)

  // asset browser
  const assetPath = ref('')
  const assetEntries = ref([])
  const assetLoading = ref(false)

  // scene list
  const sceneList = ref([])

  // ─── Derived ──────────────────────────────────────────────────────────────
  const activeLayer = computed(() =>
    world.value?.layers.find(l => l.id === activeLayerId.value) ?? null
  )

  const selectedEntity = computed(() => {
    if (!world.value) return null
    for (const layer of world.value.layers) {
      if (layer.type !== 'entity') continue
      const e = layer.entities?.find(e => e.id === selectedEntityId.value)
      if (e) return e
    }
    return null
  })

  // ─── Actions ──────────────────────────────────────────────────────────────
  async function fetchConfig() {
    config.value = await api.getConfig()
  }

  async function fetchWorldList() {
    worldList.value = await api.listWorlds()
  }

  async function loadWorld(name) {
    const data = await api.getWorld(name)
    world.value = data
    worldName.value = name
    activeLayerId.value = data.layers?.[0]?.id ?? null
    isDirty.value = false
    history.value = [JSON.stringify(data)]
    historyIndex.value = 0
  }

  function newWorld(name = 'Untitled') {
    const data = makeDefaultWorld(name)
    world.value = data
    worldName.value = name.toLowerCase().replace(/\s+/g, '_')
    activeLayerId.value = data.layers[0].id
    isDirty.value = true
    history.value = [JSON.stringify(data)]
    historyIndex.value = 0
  }

  async function saveCurrentWorld() {
    if (!world.value || !worldName.value) return
    world.value.meta.modified = new Date().toISOString()
    await api.saveWorld(worldName.value, world.value)
    isDirty.value = false
    await fetchWorldList()
  }

  function snapshot() {
    if (!world.value) return
    const snap = JSON.stringify(world.value)
    // truncate future history
    history.value = history.value.slice(0, historyIndex.value + 1)
    history.value.push(snap)
    historyIndex.value = history.value.length - 1
    isDirty.value = true
  }

  function undo() {
    if (historyIndex.value <= 0) return
    historyIndex.value--
    world.value = JSON.parse(history.value[historyIndex.value])
    isDirty.value = true
  }

  function redo() {
    if (historyIndex.value >= history.value.length - 1) return
    historyIndex.value++
    world.value = JSON.parse(history.value[historyIndex.value])
    isDirty.value = true
  }

  function setActiveTool(tool) {
    selectedTool.value = tool
    selectedEntityId.value = null
  }

  function setActiveLayer(id) {
    activeLayerId.value = id
    selectedEntityId.value = null
  }

  function toggleLayerVisibility(id) {
    const layer = world.value?.layers.find(l => l.id === id)
    if (layer) { layer.visible = !layer.visible; isDirty.value = true }
  }

  function toggleLayerLock(id) {
    const layer = world.value?.layers.find(l => l.id === id)
    if (layer) { layer.locked = !layer.locked; isDirty.value = true }
  }

  function addLayer(type = 'tile') {
    if (!world.value) return
    const id = 'layer_' + Date.now()
    const layer = type === 'tile'
      ? { id, name: 'New Layer', type: 'tile', visible: true, locked: false, tiles: {} }
      : { id, name: 'New Layer', type: 'entity', visible: true, locked: false, entities: [] }
    world.value.layers.push(layer)
    activeLayerId.value = id
    snapshot()
  }

  function removeLayer(id) {
    if (!world.value) return
    world.value.layers = world.value.layers.filter(l => l.id !== id)
    if (activeLayerId.value === id) {
      activeLayerId.value = world.value.layers[0]?.id ?? null
    }
    snapshot()
  }

  function renameLayer(id, name) {
    const layer = world.value?.layers.find(l => l.id === id)
    if (layer) {
      layer.name = name
      isDirty.value = true
    }
  }

  function moveLayerUp(id) {
    if (!world.value) return
    const idx = world.value.layers.findIndex(l => l.id === id)
    if (idx < world.value.layers.length - 1) {
      const temp = world.value.layers[idx]
      world.value.layers[idx] = world.value.layers[idx + 1]
      world.value.layers[idx + 1] = temp
      snapshot()
    }
  }

  function moveLayerDown(id) {
    if (!world.value) return
    const idx = world.value.layers.findIndex(l => l.id === id)
    if (idx > 0) {
      const temp = world.value.layers[idx]
      world.value.layers[idx] = world.value.layers[idx - 1]
      world.value.layers[idx - 1] = temp
      snapshot()
    }
  }

  function placeTile(gridX, gridY) {
    const layer = activeLayer.value
    if (!layer || layer.type !== 'tile' || layer.locked) return
    if (!selectedTile.value) return
    if (!layer.tiles) layer.tiles = {}
    layer.tiles[`${gridX},${gridY}`] = { ...selectedTile.value }
    isDirty.value = true
  }

  function eraseTile(gridX, gridY) {
    const layer = activeLayer.value
    if (!layer || layer.type !== 'tile' || layer.locked) return
    if (layer.tiles) delete layer.tiles[`${gridX},${gridY}`]
    isDirty.value = true
  }

  function placeEntity(worldX, worldY) {
    const layer = activeLayer.value
    if (!layer || layer.type !== 'entity' || layer.locked) return
    if (!selectedEntityType.value) return
    const id = Date.now()
    if (!layer.entities) layer.entities = []
    layer.entities.push({
      id,
      name: selectedEntityType.value,
      type: selectedEntityType.value,
      position: { x: worldX, y: worldY },
      rotation: 0.0,
      scale: { x: 1.0, y: 1.0 },
      properties: {},
    })
    selectedEntityId.value = id
    snapshot()
  }

  function eraseEntityAt(worldX, worldY, radius = 16) {
    const layer = activeLayer.value
    if (!layer || layer.type !== 'entity' || layer.locked) return
    if (!layer.entities) return
    layer.entities = layer.entities.filter(e => {
      const dx = e.position.x - worldX
      const dy = e.position.y - worldY
      return Math.sqrt(dx * dx + dy * dy) > radius
    })
    snapshot()
  }

  function updateSelectedEntity(patch) {
    if (!selectedEntity.value) return
    Object.assign(selectedEntity.value, patch)
    isDirty.value = true
  }

  function selectEntityAt(worldX, worldY, radius = 16) {
    if (!world.value) return
    // Search all visible entity layers, not just active
    for (const layer of world.value.layers) {
      if (layer.type !== 'entity' || !layer.visible) continue
      const hit = layer.entities?.find(e => {
        const dx = e.position.x - worldX
        const dy = e.position.y - worldY
        return Math.sqrt(dx * dx + dy * dy) <= radius
      })
      if (hit) {
        activeLayerId.value = layer.id
        selectedEntityId.value = hit.id
        return
      }
    }
    selectedEntityId.value = null
  }

  function moveEntityTo(entityId, x, y) {
    if (!world.value) return
    for (const layer of world.value.layers) {
      if (layer.type !== 'entity') continue
      const entity = layer.entities?.find(e => e.id === entityId)
      if (entity) {
        entity.position.x = x
        entity.position.y = y
        isDirty.value = true
        return
      }
    }
  }

  function deleteSelectedEntity() {
    if (!selectedEntity.value || !world.value) return
    const eid = selectedEntityId.value
    for (const layer of world.value.layers) {
      if (layer.type !== 'entity' || !layer.entities) continue
      const idx = layer.entities.findIndex(e => e.id === eid)
      if (idx !== -1) {
        layer.entities.splice(idx, 1)
        selectedEntityId.value = null
        snapshot()
        return
      }
    }
  }

  function duplicateEntity() {
    if (!selectedEntity.value || !world.value) return
    const src = selectedEntity.value
    // Find which layer it belongs to
    for (const layer of world.value.layers) {
      if (layer.type !== 'entity' || !layer.entities) continue
      if (!layer.entities.find(e => e.id === src.id)) continue
      const copy = JSON.parse(JSON.stringify(src))
      copy.id = Date.now()
      copy.name = src.name + ' (copy)'
      copy.position.x += 32
      copy.position.y += 32
      layer.entities.push(copy)
      selectedEntityId.value = copy.id
      snapshot()
      return
    }
  }

  // ─── Asset Browser ──────────────────────────────────────────────────────
  async function browseAssets(dir = '') {
    assetLoading.value = true
    try {
      const result = await api.browseAssets(dir)
      assetPath.value = result.path ?? ''
      assetEntries.value = result.entries ?? []
    } finally {
      assetLoading.value = false
    }
  }

  // ─── Scene List ─────────────────────────────────────────────────────────
  async function fetchScenes() {
    sceneList.value = await api.listScenes()
  }

  return {
    world, worldName, isDirty, activeLayerId, activeLayer,
    selectedTool, selectedEntityType, selectedTile,
    selectedEntityId, selectedEntity,
    worldList, config,
    assetPath, assetEntries, assetLoading,
    sceneList,
    fetchConfig, fetchWorldList,
    loadWorld, newWorld, saveCurrentWorld,
    snapshot, undo, redo,
    setActiveTool, setActiveLayer,
    toggleLayerVisibility, toggleLayerLock,
    addLayer, removeLayer, renameLayer, moveLayerUp, moveLayerDown,
    placeTile, eraseTile, placeEntity, eraseEntityAt,
    updateSelectedEntity, selectEntityAt,
    moveEntityTo, deleteSelectedEntity, duplicateEntity,
    browseAssets, fetchScenes,
  }
})
