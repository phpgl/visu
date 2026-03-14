<template>
  <div class="canvas-wrapper" ref="wrapperRef">
    <canvas
      ref="canvasRef"
      @mousedown="onMouseDown"
      @mousemove="onMouseMove"
      @mouseup="onMouseUp"
      @wheel="onWheel"
      @contextmenu.prevent
    />
    <div class="coords" v-if="hoverWorld">
      {{ Math.round(hoverWorld.x) }}, {{ Math.round(hoverWorld.y) }}
      &nbsp;|&nbsp; grid {{ hoverGrid.x }}, {{ hoverGrid.y }}
    </div>
    <div v-if="!store.world" class="placeholder">
      Open or create a world to start editing
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, reactive } from 'vue'
import { useWorldStore } from '../stores/world.js'

const store = useWorldStore()
const wrapperRef = ref(null)
const canvasRef = ref(null)

// ─── Camera state ─────────────────────────────────────────────────────────────
const cam = reactive({ x: 0, y: 0, zoom: 1.0 })

// ─── Interaction state ────────────────────────────────────────────────────────
let isPanning = false
let panStart = null
let isDrawing = false
let isDragging = false
let dragEntityId = null
let dragOffset = { x: 0, y: 0 }
let dragStartPos = null
const hoverWorld = ref(null)
const hoverGrid = ref({ x: 0, y: 0 })

// Tileset image cache
const tilesetImages = {}

// ─── Resize handling ──────────────────────────────────────────────────────────
let ro = null
onMounted(() => {
  ro = new ResizeObserver(resize)
  ro.observe(wrapperRef.value)
  resize()
  window.addEventListener('keydown', onKeyDown)
})
onUnmounted(() => {
  ro?.disconnect()
  window.removeEventListener('keydown', onKeyDown)
})

function resize() {
  const canvas = canvasRef.value
  const wrapper = wrapperRef.value
  if (!canvas || !wrapper) return
  canvas.width = wrapper.clientWidth
  canvas.height = wrapper.clientHeight
  draw()
}

// ─── Coordinate transforms ────────────────────────────────────────────────────
function screenToWorld(sx, sy) {
  const canvas = canvasRef.value
  return {
    x: (sx - canvas.width / 2) / cam.zoom + cam.x,
    y: (sy - canvas.height / 2) / cam.zoom + cam.y,
  }
}

function worldToScreen(wx, wy) {
  const canvas = canvasRef.value
  return {
    x: (wx - cam.x) * cam.zoom + canvas.width / 2,
    y: (wy - cam.y) * cam.zoom + canvas.height / 2,
  }
}

function worldToGrid(wx, wy) {
  const ts = store.world?.meta.tileSize ?? 32
  return { x: Math.floor(wx / ts), y: Math.floor(wy / ts) }
}

// ─── Drawing ─────────────────────────────────────────────────────────────────
watch(
  () => [store.world, store.activeLayerId, store.selectedEntityId, store.selectedTool],
  draw,
  { deep: true }
)

function draw() {
  const canvas = canvasRef.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  const W = canvas.width
  const H = canvas.height

  ctx.clearRect(0, 0, W, H)

  if (!store.world) return

  ctx.save()
  ctx.translate(W / 2, H / 2)
  ctx.scale(cam.zoom, cam.zoom)
  ctx.translate(-cam.x, -cam.y)

  const tileSize = store.world.meta.tileSize ?? 32
  const gridW = store.config.gridWidth ?? 32
  const gridH = store.config.gridHeight ?? 32

  // ── Draw grid ──────────────────────────────────────────────────────────────
  ctx.strokeStyle = 'rgba(255,255,255,0.06)'
  ctx.lineWidth = 0.5 / cam.zoom
  for (let gx = 0; gx <= gridW; gx++) {
    ctx.beginPath()
    ctx.moveTo(gx * tileSize, 0)
    ctx.lineTo(gx * tileSize, gridH * tileSize)
    ctx.stroke()
  }
  for (let gy = 0; gy <= gridH; gy++) {
    ctx.beginPath()
    ctx.moveTo(0, gy * tileSize)
    ctx.lineTo(gridW * tileSize, gy * tileSize)
    ctx.stroke()
  }

  // World border
  ctx.strokeStyle = 'rgba(120,120,255,0.3)'
  ctx.lineWidth = 1 / cam.zoom
  ctx.strokeRect(0, 0, gridW * tileSize, gridH * tileSize)

  // ── Draw layers ────────────────────────────────────────────────────────────
  for (const layer of store.world.layers) {
    if (!layer.visible) continue

    if (layer.type === 'tile') {
      drawTileLayer(ctx, layer, tileSize)
    } else if (layer.type === 'entity') {
      drawEntityLayer(ctx, layer, tileSize)
    }
  }

  // ── Hover highlight ────────────────────────────────────────────────────────
  if (hoverWorld.value && !isDragging) {
    const hw = hoverWorld.value
    const activeLayer = store.activeLayer
    if (activeLayer?.type === 'tile' && store.selectedTool === 'place_tile') {
      const gx = hoverGrid.value.x
      const gy = hoverGrid.value.y
      ctx.fillStyle = 'rgba(120,120,255,0.25)'
      ctx.fillRect(gx * tileSize, gy * tileSize, tileSize, tileSize)
    } else if (activeLayer?.type === 'entity' && store.selectedTool === 'place_entity') {
      ctx.strokeStyle = '#4a4aff'
      ctx.lineWidth = 1.5 / cam.zoom
      ctx.beginPath()
      ctx.arc(hw.x, hw.y, tileSize / 2 - 2, 0, Math.PI * 2)
      ctx.stroke()
    }
  }

  ctx.restore()
}

function drawTileLayer(ctx, layer, tileSize) {
  if (!layer.tiles) return
  for (const [key, tile] of Object.entries(layer.tiles)) {
    const [gx, gy] = key.split(',').map(Number)
    const ts = store.world.tilesets.find(t => t.id === tile.tilesetId)
    if (ts) {
      let img = tilesetImages[ts.path]
      if (!img) {
        img = new Image()
        img.src = '/' + ts.path
        img.onload = () => { tilesetImages[ts.path] = img; draw() }
        tilesetImages[ts.path] = img
        continue
      }
      if (!img.complete) continue
      ctx.drawImage(
        img,
        tile.tx * (ts.tileWidth ?? tileSize),
        tile.ty * (ts.tileHeight ?? tileSize),
        ts.tileWidth ?? tileSize,
        ts.tileHeight ?? tileSize,
        gx * tileSize, gy * tileSize, tileSize, tileSize
      )
    } else {
      // Fallback: colored square
      ctx.fillStyle = '#445566'
      ctx.fillRect(gx * tileSize + 1, gy * tileSize + 1, tileSize - 2, tileSize - 2)
    }
  }
}

function drawEntityLayer(ctx, layer, tileSize) {
  if (!layer.entities) return
  const r = tileSize * 0.4

  for (const entity of layer.entities) {
    const { x, y } = entity.position
    const isSelected = entity.id === store.selectedEntityId

    ctx.save()
    ctx.translate(x, y)
    ctx.rotate((entity.rotation ?? 0) * Math.PI / 180)

    // Body
    ctx.beginPath()
    ctx.arc(0, 0, r, 0, Math.PI * 2)
    ctx.fillStyle = isSelected ? '#4a4aff' : '#336'
    ctx.fill()
    ctx.strokeStyle = isSelected ? '#aaf' : '#88f'
    ctx.lineWidth = 1.5 / cam.zoom
    ctx.stroke()

    // Direction indicator
    ctx.beginPath()
    ctx.moveTo(0, 0)
    ctx.lineTo(r, 0)
    ctx.strokeStyle = isSelected ? '#fff' : '#aaa'
    ctx.lineWidth = 1 / cam.zoom
    ctx.stroke()

    // Selection handles
    if (isSelected) {
      ctx.strokeStyle = '#7b8ff5'
      ctx.lineWidth = 1 / cam.zoom
      ctx.setLineDash([3 / cam.zoom, 3 / cam.zoom])
      ctx.strokeRect(-r - 4 / cam.zoom, -r - 4 / cam.zoom, (r + 4 / cam.zoom) * 2, (r + 4 / cam.zoom) * 2)
      ctx.setLineDash([])
    }

    // Label
    ctx.rotate(-(entity.rotation ?? 0) * Math.PI / 180)
    ctx.fillStyle = '#eee'
    ctx.font = `${11 / cam.zoom}px system-ui`
    ctx.textAlign = 'center'
    ctx.fillText(entity.name || entity.type, 0, r + 12 / cam.zoom)

    ctx.restore()
  }
}

// ─── Mouse events ─────────────────────────────────────────────────────────────
function findEntityAt(wPos, radius = 16) {
  if (!store.world) return null
  for (let i = store.world.layers.length - 1; i >= 0; i--) {
    const layer = store.world.layers[i]
    if (layer.type !== 'entity' || !layer.visible || !layer.entities) continue
    for (let j = layer.entities.length - 1; j >= 0; j--) {
      const e = layer.entities[j]
      const dx = e.position.x - wPos.x
      const dy = e.position.y - wPos.y
      if (Math.sqrt(dx * dx + dy * dy) <= radius) return e
    }
  }
  return null
}

function onMouseDown(e) {
  const wPos = screenToWorld(e.offsetX, e.offsetY)
  const gPos = worldToGrid(wPos.x, wPos.y)

  if (e.button === 1 || (e.button === 0 && e.altKey)) {
    // Middle mouse or alt+left = pan
    isPanning = true
    panStart = { mx: e.clientX, my: e.clientY, cx: cam.x, cy: cam.y }
    return
  }

  if (e.button === 0 && store.selectedTool === 'select') {
    const hit = findEntityAt(wPos)
    if (hit) {
      store.selectEntityAt(wPos.x, wPos.y)
      // Start drag
      isDragging = true
      dragEntityId = hit.id
      dragOffset = { x: hit.position.x - wPos.x, y: hit.position.y - wPos.y }
      dragStartPos = { x: hit.position.x, y: hit.position.y }
      return
    }
    // Clicked empty space — deselect
    store.selectEntityAt(wPos.x, wPos.y)
    draw()
    return
  }

  if (e.button === 0) {
    isDrawing = true
    applyTool(wPos, gPos)
  }
}

function onMouseMove(e) {
  const wPos = screenToWorld(e.offsetX, e.offsetY)
  hoverWorld.value = wPos
  hoverGrid.value = worldToGrid(wPos.x, wPos.y)

  if (isPanning && panStart) {
    const dx = (e.clientX - panStart.mx) / cam.zoom
    const dy = (e.clientY - panStart.my) / cam.zoom
    cam.x = panStart.cx - dx
    cam.y = panStart.cy - dy
    draw()
    return
  }

  if (isDragging && dragEntityId != null) {
    store.moveEntityTo(dragEntityId, wPos.x + dragOffset.x, wPos.y + dragOffset.y)
    draw()
    return
  }

  if (isDrawing) {
    applyTool(wPos, hoverGrid.value)
  }

  draw()
}

function onMouseUp(e) {
  if (isPanning) { isPanning = false; panStart = null; return }
  if (isDragging) {
    // Snapshot only if position actually changed
    if (dragStartPos) {
      const entity = findEntityAt({ x: 0, y: 0 }, Infinity) // dummy — find by id instead
      const moved = store.world?.layers.some(l =>
        l.entities?.some(e => e.id === dragEntityId &&
          (e.position.x !== dragStartPos.x || e.position.y !== dragStartPos.y))
      )
      if (moved) store.snapshot()
    }
    isDragging = false
    dragEntityId = null
    dragStartPos = null
    return
  }
  if (isDrawing) {
    isDrawing = false
    if (['place_tile', 'erase'].includes(store.selectedTool)) {
      store.snapshot()
    }
  }
}

function onWheel(e) {
  e.preventDefault()
  const factor = e.deltaY < 0 ? 1.1 : 0.9
  cam.zoom = Math.min(8, Math.max(0.1, cam.zoom * factor))
  draw()
}

function applyTool(wPos, gPos) {
  const tool = store.selectedTool
  if (tool === 'place_tile') {
    store.placeTile(gPos.x, gPos.y)
    draw()
  } else if (tool === 'erase') {
    const active = store.activeLayer
    if (active?.type === 'tile') store.eraseTile(gPos.x, gPos.y)
    else if (active?.type === 'entity') store.eraseEntityAt(wPos.x, wPos.y)
    draw()
  } else if (tool === 'place_entity') {
    store.placeEntity(wPos.x, wPos.y)
    draw()
  }
}

// ─── Keyboard shortcuts ────────────────────────────────────────────────────────
function onKeyDown(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); store.undo() }
  if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) { e.preventDefault(); store.redo() }
  if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); store.saveCurrentWorld() }
  if ((e.ctrlKey || e.metaKey) && e.key === 'd') { e.preventDefault(); store.duplicateEntity() }
  if (e.key === 'Delete' || e.key === 'Backspace') {
    // Only delete if not focused on an input
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return
    if (store.selectedEntityId != null) {
      e.preventDefault()
      store.deleteSelectedEntity()
      draw()
    }
  }
  // Tool shortcuts
  if (!e.ctrlKey && !e.metaKey && !e.altKey) {
    if (e.key === 'v' || e.key === 'V') store.setActiveTool('select')
    if (e.key === 'b' || e.key === 'B') store.setActiveTool('place_tile')
    if (e.key === 'e' || e.key === 'E') store.setActiveTool('place_entity')
    if (e.key === 'x' || e.key === 'X') store.setActiveTool('erase')
  }
}
</script>

<style scoped>
.canvas-wrapper {
  flex: 1; position: relative; overflow: hidden; background: #12121e;
}
canvas { display: block; width: 100%; height: 100%; cursor: crosshair; }
.coords {
  position: absolute; bottom: 8px; left: 8px;
  background: rgba(0,0,0,.5); color: #aaa; font-size: 10px;
  padding: 2px 8px; border-radius: 3px; pointer-events: none;
}
.placeholder {
  position: absolute; inset: 0; display: flex; align-items: center;
  justify-content: center; color: #333; font-size: 16px; pointer-events: none;
}
</style>
