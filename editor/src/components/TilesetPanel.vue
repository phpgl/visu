<template>
  <div class="tileset-panel">
    <div class="panel-header">Tilesets</div>

    <div v-if="!store.world" class="empty">No world open</div>
    <template v-else>
      <div v-if="!store.world.tilesets.length" class="empty">
        No tilesets — add one to the world JSON.
      </div>
      <div v-else>
        <select v-model="activeTilesetId" class="tileset-select">
          <option v-for="ts in store.world.tilesets" :key="ts.id" :value="ts.id">
            {{ ts.id }}
          </option>
        </select>

        <!-- Tileset grid (requires image to be accessible from dist/) -->
        <div v-if="activeTileset" class="tileset-canvas-wrapper">
          <canvas
            ref="canvasRef"
            :width="canvasW"
            :height="canvasH"
            @click="onCanvasClick"
          />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useWorldStore } from '../stores/world.js'

const store = useWorldStore()
const activeTilesetId = ref(null)
const canvasRef = ref(null)

const activeTileset = computed(() =>
  store.world?.tilesets.find(ts => ts.id === activeTilesetId.value) ?? null
)

const tileW = computed(() => activeTileset.value?.tileWidth ?? 32)
const tileH = computed(() => activeTileset.value?.tileHeight ?? 32)
const img = ref(null)
const cols = ref(1)
const rows = ref(1)
const canvasW = computed(() => cols.value * tileW.value)
const canvasH = computed(() => rows.value * tileH.value)

watch(activeTileset, async (ts) => {
  if (!ts) return
  await nextTick()
  const image = new Image()
  image.onload = () => {
    img.value = image
    cols.value = Math.floor(image.width / tileW.value)
    rows.value = Math.floor(image.height / tileH.value)
    drawTileset()
  }
  image.src = '/' + ts.path
})

watch(store, () => {
  if (!activeTilesetId.value && store.world?.tilesets.length) {
    activeTilesetId.value = store.world.tilesets[0].id
  }
})

function drawTileset() {
  const canvas = canvasRef.value
  if (!canvas || !img.value) return
  const ctx = canvas.getContext('2d')
  ctx.drawImage(img.value, 0, 0)

  // Grid
  ctx.strokeStyle = 'rgba(0,0,0,0.4)'
  ctx.lineWidth = 0.5
  for (let c = 0; c <= cols.value; c++) {
    ctx.beginPath(); ctx.moveTo(c * tileW.value, 0); ctx.lineTo(c * tileW.value, canvasH.value); ctx.stroke()
  }
  for (let r = 0; r <= rows.value; r++) {
    ctx.beginPath(); ctx.moveTo(0, r * tileH.value); ctx.lineTo(canvasW.value, r * tileH.value); ctx.stroke()
  }

  // Selected tile highlight
  if (store.selectedTile?.tilesetId === activeTilesetId.value) {
    const { tx, ty } = store.selectedTile
    ctx.strokeStyle = '#4a4aff'
    ctx.lineWidth = 2
    ctx.strokeRect(tx * tileW.value + 1, ty * tileH.value + 1, tileW.value - 2, tileH.value - 2)
  }
}

function onCanvasClick(e) {
  const rect = canvasRef.value.getBoundingClientRect()
  const scaleX = canvasW.value / rect.width
  const scaleY = canvasH.value / rect.height
  const tx = Math.floor((e.clientX - rect.left) * scaleX / tileW.value)
  const ty = Math.floor((e.clientY - rect.top) * scaleY / tileH.value)
  store.selectedTile = { tilesetId: activeTilesetId.value, tx, ty }
  store.setActiveTool('place_tile')
  drawTileset()
}
</script>

<style scoped>
.tileset-panel { display: flex; flex-direction: column; height: 100%; }
.panel-header {
  padding: 6px 8px; border-bottom: 1px solid #333;
  font-size: 11px; font-weight: bold; color: #aaa;
  text-transform: uppercase; letter-spacing: .05em;
}
.empty { padding: 12px 8px; color: #555; font-size: 11px; }
.tileset-select {
  width: 100%; background: #2a2a3e; border: 1px solid #444; color: #eee;
  padding: 4px 8px; margin: 6px 0; font-size: 12px;
}
.tileset-canvas-wrapper { overflow: auto; padding: 4px; }
canvas { image-rendering: pixelated; max-width: 100%; cursor: crosshair; display: block; }
</style>
