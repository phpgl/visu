<template>
  <div class="inspector-panel">
    <div class="panel-header">Inspector</div>

    <div v-if="!store.world" class="empty">No world open</div>

    <template v-else-if="store.selectedEntity">
      <div class="section">
        <div class="section-title">Entity</div>
        <label>Name
          <input :value="store.selectedEntity.name"
            @input="store.updateSelectedEntity({ name: $event.target.value })" />
        </label>
        <label>Type
          <input :value="store.selectedEntity.type"
            @input="store.updateSelectedEntity({ type: $event.target.value })" />
        </label>
        <div class="entity-id">ID: {{ store.selectedEntity.id }}</div>
      </div>

      <div class="section">
        <div class="section-title">Position</div>
        <div class="row2">
          <label>X
            <input type="number" step="1"
              :value="store.selectedEntity.position.x"
              @input="updatePos('x', $event)" />
          </label>
          <label>Y
            <input type="number" step="1"
              :value="store.selectedEntity.position.y"
              @input="updatePos('y', $event)" />
          </label>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Transform</div>
        <label>Rotation (deg)
          <input type="number" step="1"
            :value="store.selectedEntity.rotation"
            @input="store.updateSelectedEntity({ rotation: +$event.target.value })" />
        </label>
        <div class="row2">
          <label>Scale X
            <input type="number" step="0.1" min="0.01"
              :value="store.selectedEntity.scale.x"
              @input="updateScale('x', $event)" />
          </label>
          <label>Scale Y
            <input type="number" step="0.1" min="0.01"
              :value="store.selectedEntity.scale.y"
              @input="updateScale('y', $event)" />
          </label>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Properties</div>
        <div v-for="(val, key) in store.selectedEntity.properties" :key="key" class="prop-row">
          <span class="prop-key">{{ key }}</span>
          <input class="prop-val"
            :value="val"
            @input="updateProp(key, $event.target.value)" />
          <button class="prop-del" @click="removeProp(key)" title="Remove property">x</button>
        </div>
        <button class="add-prop" @click="addProperty">+ Add property</button>
      </div>

      <div class="section actions">
        <button class="action-btn duplicate" @click="store.duplicateEntity">Duplicate (Ctrl+D)</button>
        <button class="action-btn delete" @click="onDelete">Delete (Del)</button>
      </div>
    </template>

    <template v-else-if="store.world">
      <div class="section">
        <div class="section-title">World</div>
        <label>Name
          <input :value="store.world.meta.name"
            @input="store.world.meta.name = $event.target.value; store.isDirty = true" />
        </label>
        <label>Type
          <select :value="store.world.meta.type"
            @change="store.world.meta.type = $event.target.value; store.isDirty = true">
            <option value="2d_topdown">2D Top-down</option>
            <option value="2d_platformer">2D Platformer</option>
            <option value="3d">3D</option>
          </select>
        </label>
        <label>Tile Size
          <input type="number" min="1"
            :value="store.world.meta.tileSize"
            @input="store.world.meta.tileSize = +$event.target.value; store.isDirty = true" />
        </label>
      </div>

      <div class="section">
        <div class="section-title">Camera</div>
        <div class="row2">
          <label>X
            <input type="number" :value="store.world.camera.position.x"
              @input="store.world.camera.position.x = +$event.target.value; store.isDirty = true" />
          </label>
          <label>Y
            <input type="number" :value="store.world.camera.position.y"
              @input="store.world.camera.position.y = +$event.target.value; store.isDirty = true" />
          </label>
        </div>
        <label>Zoom
          <input type="number" step="0.1" min="0.1"
            :value="store.world.camera.zoom"
            @input="store.world.camera.zoom = +$event.target.value; store.isDirty = true" />
        </label>
      </div>
    </template>

    <div v-else class="empty">Select an entity to inspect</div>
  </div>
</template>

<script setup>
import { useWorldStore } from '../stores/world.js'

const store = useWorldStore()

function updatePos(axis, e) {
  const pos = { ...store.selectedEntity.position, [axis]: +e.target.value }
  store.updateSelectedEntity({ position: pos })
}

function updateScale(axis, e) {
  const scale = { ...store.selectedEntity.scale, [axis]: +e.target.value }
  store.updateSelectedEntity({ scale })
}

function updateProp(key, value) {
  const props = { ...store.selectedEntity.properties, [key]: value }
  store.updateSelectedEntity({ properties: props })
}

function addProperty() {
  const key = prompt('Property name:')
  if (!key) return
  const props = { ...store.selectedEntity.properties, [key]: '' }
  store.updateSelectedEntity({ properties: props })
}

function removeProp(key) {
  const props = { ...store.selectedEntity.properties }
  delete props[key]
  store.updateSelectedEntity({ properties: props })
}

function onDelete() {
  if (confirm('Delete this entity?')) {
    store.deleteSelectedEntity()
  }
}
</script>

<style scoped>
.inspector-panel { display: flex; flex-direction: column; height: 100%; overflow-y: auto; }
.panel-header {
  padding: 6px 8px; border-bottom: 1px solid #333;
  font-size: 11px; font-weight: bold; color: #aaa;
  text-transform: uppercase; letter-spacing: .05em; flex-shrink: 0;
}
.empty { padding: 12px 8px; color: #555; font-size: 11px; }
.section { padding: 8px; border-bottom: 1px solid #222; }
.section-title { font-size: 10px; color: #7b8ff5; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
label {
  display: flex; flex-direction: column; font-size: 11px; color: #888;
  margin-bottom: 5px; gap: 2px;
}
input, select {
  background: #2a2a3e; border: 1px solid #444; color: #eee;
  padding: 3px 6px; border-radius: 3px; font-size: 12px; width: 100%;
}
input:focus, select:focus { outline: 1px solid #4a4aff; }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.entity-id { font-size: 10px; color: #555; margin-top: 4px; }
.prop-row { display: flex; gap: 4px; margin-bottom: 4px; align-items: center; }
.prop-key { font-size: 11px; color: #888; width: 70px; flex-shrink: 0; overflow: hidden; text-overflow: ellipsis; }
.prop-val { flex: 1; }
.prop-del {
  background: transparent; border: 1px solid #444; color: #f66;
  width: 20px; height: 20px; cursor: pointer; border-radius: 3px;
  font-size: 10px; padding: 0; flex-shrink: 0; display: flex;
  align-items: center; justify-content: center;
}
.prop-del:hover { border-color: #f66; }
.add-prop {
  background: transparent; border: 1px dashed #444; color: #666;
  padding: 3px 8px; cursor: pointer; border-radius: 3px; font-size: 11px;
  margin-top: 4px; width: 100%;
}
.add-prop:hover { border-color: #7b8ff5; color: #7b8ff5; }
.actions { display: flex; flex-direction: column; gap: 4px; }
.action-btn {
  background: #2a2a3e; border: 1px solid #444; color: #ccc;
  padding: 5px 8px; cursor: pointer; border-radius: 3px; font-size: 11px;
  width: 100%; text-align: center;
}
.action-btn:hover { background: #3a3a5e; }
.action-btn.delete { color: #f66; border-color: #633; }
.action-btn.delete:hover { background: #3a1a1a; border-color: #f66; }
.action-btn.duplicate { color: #7b8ff5; border-color: #446; }
.action-btn.duplicate:hover { background: #1a1a3a; border-color: #7b8ff5; }
</style>
