<template>
  <div class="ui-editor">
    <!-- Toolbar -->
    <div class="toolbar">
      <span class="toolbar-title">UI Layout Editor</span>
      <div class="toolbar-group">
        <button @click="onNew">New</button>
        <button @click="showOpen = true">Open</button>
        <button @click="onSave" :disabled="!layout" :class="{ dirty: isDirty }">
          Save{{ isDirty ? '*' : '' }}
        </button>
      </div>
      <span v-if="layoutName" class="layout-name">{{ layoutName }}</span>
      <div class="toolbar-group right">
        <button @click="undo" :disabled="historyIndex <= 0" title="Undo">&#8617;</button>
        <button @click="redo" :disabled="historyIndex >= historyStack.length - 1" title="Redo">&#8618;</button>
      </div>
    </div>

    <div class="editor-body">
      <!-- Left: Tree View -->
      <div class="panel-left">
        <div class="panel-header">
          <span>Hierarchy</span>
          <div class="add-root-group" v-if="layout">
            <select v-model="addNodeType" class="add-select">
              <option v-for="t in nodeTypes" :key="t" :value="t">{{ t }}</option>
            </select>
            <button class="add-btn" @click="addChildToSelected" title="Add child node">+</button>
          </div>
        </div>
        <div class="tree-container" v-if="layout">
          <TreeNode
            :node="layout"
            :path="[]"
            :selectedPath="selectedPath"
            :depth="0"
            @select="selectNode"
            @dragstart="onTreeDragStart"
            @drop="onTreeDrop"
          />
        </div>
        <div v-else class="empty-hint">No layout loaded</div>
        <div class="tree-actions" v-if="selectedNode && selectedPath.length > 0">
          <button class="action-btn delete" @click="removeSelected">Remove Node</button>
          <button class="action-btn" @click="moveUp" :disabled="!canMoveUp">Move Up</button>
          <button class="action-btn" @click="moveDown" :disabled="!canMoveDown">Move Down</button>
        </div>
      </div>

      <!-- Center: Preview -->
      <div class="panel-center">
        <div class="panel-header">Preview</div>
        <div class="preview-scroll">
          <div class="preview-canvas">
            <PreviewNode v-if="layout" :node="layout" :selectedPath="selectedPath" :currentPath="[]" @select="selectNode" />
          </div>
        </div>
      </div>

      <!-- Right: Property Inspector -->
      <div class="panel-right">
        <div class="panel-header">Properties</div>
        <div class="props-container" v-if="selectedNode">
          <div class="section">
            <div class="section-title">Node Type</div>
            <div class="type-badge">{{ selectedNode.type }}</div>
          </div>

          <!-- Common: type selector (only for non-root or any node) -->
          <div class="section">
            <div class="section-title">Type</div>
            <select :value="selectedNode.type" @change="updateProp('type', $event.target.value)">
              <option v-for="t in nodeTypes" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>

          <!-- Panel props -->
          <template v-if="selectedNode.type === 'panel'">
            <div class="section">
              <div class="section-title">Layout</div>
              <select :value="selectedNode.layout || 'column'" @change="updateProp('layout', $event.target.value)">
                <option value="column">Column</option>
                <option value="row">Row</option>
              </select>
            </div>
            <div class="section">
              <div class="section-title">Sizing</div>
              <div class="row2">
                <label>Width
                  <input type="number" :value="selectedNode.width || ''" placeholder="auto"
                    @input="updateNumPropOrRemove('width', $event.target.value)" />
                </label>
                <label>Height
                  <input type="number" :value="selectedNode.height || ''" placeholder="auto"
                    @input="updateNumPropOrRemove('height', $event.target.value)" />
                </label>
              </div>
              <div class="row2">
                <label>H Sizing
                  <select :value="selectedNode.horizontalSizing || 'fill'" @change="updateProp('horizontalSizing', $event.target.value)">
                    <option value="fill">Fill</option>
                    <option value="fit">Fit</option>
                  </select>
                </label>
                <label>V Sizing
                  <select :value="selectedNode.verticalSizing || 'fit'" @change="updateProp('verticalSizing', $event.target.value)">
                    <option value="fill">Fill</option>
                    <option value="fit">Fit</option>
                  </select>
                </label>
              </div>
            </div>
            <div class="section">
              <div class="section-title">Spacing & Padding</div>
              <label>Padding
                <input type="number" :value="selectedNode.padding ?? ''" placeholder="0"
                  @input="updateNumPropOrRemove('padding', $event.target.value)" />
              </label>
              <label>Spacing
                <input type="number" :value="selectedNode.spacing ?? ''" placeholder="0"
                  @input="updateNumPropOrRemove('spacing', $event.target.value)" />
              </label>
            </div>
            <div class="section">
              <div class="section-title">Background</div>
              <label>Color
                <div class="color-row">
                  <input type="text" :value="selectedNode.backgroundColor || ''" placeholder="#RRGGBB"
                    @input="updatePropOrRemove('backgroundColor', $event.target.value)" />
                  <input type="color" :value="selectedNode.backgroundColor || '#333333'" class="color-picker"
                    @input="updateProp('backgroundColor', $event.target.value)" />
                </div>
              </label>
            </div>
          </template>

          <!-- Label props -->
          <template v-if="selectedNode.type === 'label'">
            <div class="section">
              <div class="section-title">Text</div>
              <label>Text
                <input type="text" :value="selectedNode.text || ''" placeholder="Label text or {binding}"
                  @input="updateProp('text', $event.target.value)" />
              </label>
              <label>Font Size
                <input type="number" :value="selectedNode.fontSize ?? ''" placeholder="14"
                  @input="updateNumPropOrRemove('fontSize', $event.target.value)" />
              </label>
              <label>Color
                <div class="color-row">
                  <input type="text" :value="selectedNode.color || ''" placeholder="#RRGGBB"
                    @input="updatePropOrRemove('color', $event.target.value)" />
                  <input type="color" :value="selectedNode.color || '#eeeeee'" class="color-picker"
                    @input="updateProp('color', $event.target.value)" />
                </div>
              </label>
              <label class="checkbox-label">
                <input type="checkbox" :checked="!!selectedNode.bold"
                  @change="updateProp('bold', $event.target.checked)" />
                Bold
              </label>
            </div>
          </template>

          <!-- Button props -->
          <template v-if="selectedNode.type === 'button'">
            <div class="section">
              <div class="section-title">Button</div>
              <label>Label
                <input type="text" :value="selectedNode.label || ''" placeholder="Button"
                  @input="updateProp('label', $event.target.value)" />
              </label>
              <label>Event
                <input type="text" :value="selectedNode.event || ''" placeholder="ui.action"
                  @input="updatePropOrRemove('event', $event.target.value)" />
              </label>
              <label>Event Data (JSON)
                <input type="text" :value="JSON.stringify(selectedNode.eventData || {})" placeholder="{}"
                  @input="updateJsonProp('eventData', $event.target.value)" />
              </label>
              <label>ID
                <input type="text" :value="selectedNode.id || ''" placeholder="optional"
                  @input="updatePropOrRemove('id', $event.target.value)" />
              </label>
              <label class="checkbox-label">
                <input type="checkbox" :checked="!!selectedNode.fullWidth"
                  @change="updateProp('fullWidth', $event.target.checked)" />
                Full Width
              </label>
              <label>Style
                <select :value="selectedNode.style || 'primary'" @change="updatePropOrRemove('style', $event.target.value === 'primary' ? '' : $event.target.value)">
                  <option value="primary">Primary</option>
                  <option value="secondary">Secondary</option>
                </select>
              </label>
            </div>
          </template>

          <!-- ProgressBar props -->
          <template v-if="selectedNode.type === 'progressbar'">
            <div class="section">
              <div class="section-title">Progress Bar</div>
              <label>Value
                <input type="text" :value="selectedNode.value ?? '0'" placeholder="0.5 or {binding}"
                  @input="updateProp('value', $event.target.value)" />
              </label>
              <label>Color
                <div class="color-row">
                  <input type="text" :value="selectedNode.color || ''" placeholder="#0088ff"
                    @input="updatePropOrRemove('color', $event.target.value)" />
                  <input type="color" :value="selectedNode.color || '#0088ff'" class="color-picker"
                    @input="updateProp('color', $event.target.value)" />
                </div>
              </label>
              <label>Height
                <input type="number" :value="selectedNode.height ?? ''" placeholder="auto"
                  @input="updateNumPropOrRemove('height', $event.target.value)" />
              </label>
            </div>
          </template>

          <!-- Checkbox props -->
          <template v-if="selectedNode.type === 'checkbox'">
            <div class="section">
              <div class="section-title">Checkbox</div>
              <label>Text
                <input type="text" :value="selectedNode.text || ''" placeholder="Checkbox label"
                  @input="updateProp('text', $event.target.value)" />
              </label>
              <label>ID
                <input type="text" :value="selectedNode.id || ''" placeholder="optional"
                  @input="updatePropOrRemove('id', $event.target.value)" />
              </label>
              <label>Event
                <input type="text" :value="selectedNode.event || ''" placeholder="ui.checkbox"
                  @input="updatePropOrRemove('event', $event.target.value)" />
              </label>
              <label>Checked Binding
                <input type="text" :value="selectedNode.checked ?? ''" placeholder="false or {binding}"
                  @input="updatePropOrRemove('checked', $event.target.value)" />
              </label>
            </div>
          </template>

          <!-- Select props -->
          <template v-if="selectedNode.type === 'select'">
            <div class="section">
              <div class="section-title">Select</div>
              <label>Name
                <input type="text" :value="selectedNode.name || ''" placeholder="select_name"
                  @input="updateProp('name', $event.target.value)" />
              </label>
              <label>Event
                <input type="text" :value="selectedNode.event || ''" placeholder="ui.select"
                  @input="updatePropOrRemove('event', $event.target.value)" />
              </label>
              <label>Selected
                <input type="text" :value="selectedNode.selected || ''" placeholder="default value"
                  @input="updatePropOrRemove('selected', $event.target.value)" />
              </label>
              <div class="section-title" style="margin-top: 8px;">Options</div>
              <div v-for="(opt, i) in (selectedNode.options || [])" :key="i" class="option-row">
                <input type="text" :value="opt" @input="updateOption(i, $event.target.value)" />
                <button class="prop-del" @click="removeOption(i)">x</button>
              </div>
              <button class="add-prop" @click="addOption">+ Add option</button>
            </div>
          </template>

          <!-- Image props -->
          <template v-if="selectedNode.type === 'image'">
            <div class="section">
              <div class="section-title">Image</div>
              <div class="row2">
                <label>Width
                  <input type="number" :value="selectedNode.width ?? 64"
                    @input="updateNumProp('width', $event.target.value)" />
                </label>
                <label>Height
                  <input type="number" :value="selectedNode.height ?? 64"
                    @input="updateNumProp('height', $event.target.value)" />
                </label>
              </div>
              <label>Color
                <div class="color-row">
                  <input type="text" :value="selectedNode.color || ''" placeholder="#808080"
                    @input="updatePropOrRemove('color', $event.target.value)" />
                  <input type="color" :value="selectedNode.color || '#808080'" class="color-picker"
                    @input="updateProp('color', $event.target.value)" />
                </div>
              </label>
            </div>
          </template>

          <!-- Space props -->
          <template v-if="selectedNode.type === 'space'">
            <div class="section">
              <div class="section-title">Space</div>
              <div class="row2">
                <label>Width
                  <input type="number" :value="selectedNode.width ?? ''" placeholder="0"
                    @input="updateNumPropOrRemove('width', $event.target.value)" />
                </label>
                <label>Height
                  <input type="number" :value="selectedNode.height ?? ''" placeholder="0"
                    @input="updateNumPropOrRemove('height', $event.target.value)" />
                </label>
              </div>
            </div>
          </template>

          <!-- Raw JSON (always shown) -->
          <div class="section">
            <div class="section-title">JSON (read-only)</div>
            <pre class="json-preview">{{ JSON.stringify(selectedNode, null, 2) }}</pre>
          </div>
        </div>
        <div v-else class="empty-hint">Select a node to inspect</div>
      </div>
    </div>

    <!-- Open Layout Popup -->
    <div v-if="showOpen" class="popup" @click.self="showOpen = false">
      <div class="popup-box">
        <h3>Open UI Layout</h3>
        <div class="new-row">
          <input v-model="newLayoutName" placeholder="New layout name" @keyup.enter="onCreateNew" />
          <button @click="onCreateNew">Create</button>
        </div>
        <div v-if="layoutList.length" class="layout-list">
          <div v-for="item in layoutList" :key="item.name" class="layout-item" @click="onOpenLayout(item.name)">
            <span>{{ item.name }}</span>
            <small v-if="item.modified">{{ new Date(item.modified).toLocaleDateString() }}</small>
          </div>
        </div>
        <p v-else class="empty-msg">No UI layouts found.</p>
        <button class="close-btn" @click="showOpen = false">x</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, h, defineComponent } from 'vue'
import { listUILayouts, getUILayout, saveUILayout } from '../api.js'

// ─── Node Types ──────────────────────────────────────────────────────────────
const nodeTypes = ['panel', 'label', 'button', 'progressbar', 'checkbox', 'select', 'image', 'space']

function makeDefaultNode(type) {
  switch (type) {
    case 'panel': return { type: 'panel', layout: 'column', children: [] }
    case 'label': return { type: 'label', text: 'Label' }
    case 'button': return { type: 'button', label: 'Button', event: 'ui.click' }
    case 'progressbar': return { type: 'progressbar', value: '0.5', color: '#0088ff' }
    case 'checkbox': return { type: 'checkbox', text: 'Checkbox' }
    case 'select': return { type: 'select', name: 'my_select', options: ['Option A', 'Option B'] }
    case 'image': return { type: 'image', width: 64, height: 64 }
    case 'space': return { type: 'space', height: 10 }
    default: return { type }
  }
}

// ─── State ───────────────────────────────────────────────────────────────────
const layout = ref(null)
const layoutName = ref(null)
const isDirty = ref(false)
const selectedPath = ref([])
const showOpen = ref(false)
const newLayoutName = ref('')
const layoutList = ref([])
const addNodeType = ref('panel')

// undo/redo
const historyStack = ref([])
const historyIndex = ref(-1)

// ─── Helpers ─────────────────────────────────────────────────────────────────
function getNodeAt(path) {
  if (!layout.value) return null
  let node = layout.value
  for (const idx of path) {
    if (!node.children || idx >= node.children.length) return null
    node = node.children[idx]
  }
  return node
}

function getParentAndIndex(path) {
  if (!layout.value || path.length === 0) return { parent: null, index: -1 }
  const parentPath = path.slice(0, -1)
  const parent = getNodeAt(parentPath)
  return { parent, index: path[path.length - 1] }
}

const selectedNode = computed(() => getNodeAt(selectedPath.value))

const canMoveUp = computed(() => {
  if (selectedPath.value.length === 0) return false
  return selectedPath.value[selectedPath.value.length - 1] > 0
})

const canMoveDown = computed(() => {
  if (selectedPath.value.length === 0) return false
  const { parent, index } = getParentAndIndex(selectedPath.value)
  if (!parent || !parent.children) return false
  return index < parent.children.length - 1
})

// ─── Snapshot / Undo / Redo ──────────────────────────────────────────────────
function snapshot() {
  if (!layout.value) return
  const snap = JSON.stringify(layout.value)
  historyStack.value = historyStack.value.slice(0, historyIndex.value + 1)
  historyStack.value.push(snap)
  historyIndex.value = historyStack.value.length - 1
  isDirty.value = true
}

function undo() {
  if (historyIndex.value <= 0) return
  historyIndex.value--
  layout.value = JSON.parse(historyStack.value[historyIndex.value])
  isDirty.value = true
}

function redo() {
  if (historyIndex.value >= historyStack.value.length - 1) return
  historyIndex.value++
  layout.value = JSON.parse(historyStack.value[historyIndex.value])
  isDirty.value = true
}

// ─── Property Updates ────────────────────────────────────────────────────────
function updateProp(key, value) {
  const node = selectedNode.value
  if (!node) return
  node[key] = value
  snapshot()
}

function updatePropOrRemove(key, value) {
  const node = selectedNode.value
  if (!node) return
  if (value === '' || value === null || value === undefined) {
    delete node[key]
  } else {
    node[key] = value
  }
  snapshot()
}

function updateNumProp(key, value) {
  const node = selectedNode.value
  if (!node) return
  node[key] = +value
  snapshot()
}

function updateNumPropOrRemove(key, value) {
  const node = selectedNode.value
  if (!node) return
  if (value === '' || value === null || value === undefined) {
    delete node[key]
  } else {
    node[key] = +value
  }
  snapshot()
}

function updateJsonProp(key, value) {
  const node = selectedNode.value
  if (!node) return
  try {
    node[key] = JSON.parse(value)
    snapshot()
  } catch { /* ignore invalid JSON while typing */ }
}

// Select options
function updateOption(index, value) {
  const node = selectedNode.value
  if (!node || !node.options) return
  node.options[index] = value
  snapshot()
}

function removeOption(index) {
  const node = selectedNode.value
  if (!node || !node.options) return
  node.options.splice(index, 1)
  snapshot()
}

function addOption() {
  const node = selectedNode.value
  if (!node) return
  if (!node.options) node.options = []
  node.options.push('New Option')
  snapshot()
}

// ─── Tree Operations ─────────────────────────────────────────────────────────
function selectNode(path) {
  selectedPath.value = [...path]
}

function addChildToSelected() {
  const node = selectedNode.value
  // If selected node is a panel (has children), add as child; otherwise add as sibling
  const newNode = makeDefaultNode(addNodeType.value)

  if (node && (node.type === 'panel' || node.children)) {
    if (!node.children) node.children = []
    node.children.push(newNode)
    selectedPath.value = [...selectedPath.value, node.children.length - 1]
  } else if (selectedPath.value.length === 0 && layout.value) {
    // Adding to root panel
    if (!layout.value.children) layout.value.children = []
    layout.value.children.push(newNode)
    selectedPath.value = [layout.value.children.length - 1]
  } else if (selectedPath.value.length > 0) {
    // Add as sibling after the selected node
    const { parent, index } = getParentAndIndex(selectedPath.value)
    if (parent && parent.children) {
      parent.children.splice(index + 1, 0, newNode)
      const newPath = [...selectedPath.value]
      newPath[newPath.length - 1] = index + 1
      selectedPath.value = newPath
    }
  }
  snapshot()
}

function removeSelected() {
  if (selectedPath.value.length === 0) return
  const { parent, index } = getParentAndIndex(selectedPath.value)
  if (!parent || !parent.children) return
  parent.children.splice(index, 1)
  // Select parent
  selectedPath.value = selectedPath.value.slice(0, -1)
  snapshot()
}

function moveUp() {
  if (!canMoveUp.value) return
  const { parent, index } = getParentAndIndex(selectedPath.value)
  if (!parent || !parent.children) return
  const temp = parent.children[index]
  parent.children[index] = parent.children[index - 1]
  parent.children[index - 1] = temp
  const newPath = [...selectedPath.value]
  newPath[newPath.length - 1] = index - 1
  selectedPath.value = newPath
  snapshot()
}

function moveDown() {
  if (!canMoveDown.value) return
  const { parent, index } = getParentAndIndex(selectedPath.value)
  if (!parent || !parent.children) return
  const temp = parent.children[index]
  parent.children[index] = parent.children[index + 1]
  parent.children[index + 1] = temp
  const newPath = [...selectedPath.value]
  newPath[newPath.length - 1] = index + 1
  selectedPath.value = newPath
  snapshot()
}

// Drag and drop
const dragPath = ref(null)

function onTreeDragStart(path) {
  dragPath.value = path
}

function onTreeDrop(targetPath) {
  if (!dragPath.value || !layout.value) return
  // Don't drop onto self or child of self
  const srcStr = dragPath.value.join(',')
  const tgtStr = targetPath.join(',')
  if (tgtStr.startsWith(srcStr)) return

  // Get source node
  const { parent: srcParent, index: srcIdx } = getParentAndIndex(dragPath.value)
  if (!srcParent || !srcParent.children) return
  const node = srcParent.children[srcIdx]

  // Remove from source
  srcParent.children.splice(srcIdx, 1)

  // Get target - insert as child of target if it's a panel, otherwise as sibling
  const targetNode = getNodeAt(targetPath)
  if (targetNode && (targetNode.type === 'panel' || targetNode.children)) {
    if (!targetNode.children) targetNode.children = []
    targetNode.children.push(node)
    selectedPath.value = [...targetPath, targetNode.children.length - 1]
  } else {
    const { parent: tgtParent, index: tgtIdx } = getParentAndIndex(targetPath)
    if (tgtParent && tgtParent.children) {
      tgtParent.children.splice(tgtIdx + 1, 0, node)
    }
  }

  dragPath.value = null
  snapshot()
}

// ─── File Operations ─────────────────────────────────────────────────────────
function onNew() {
  if (isDirty.value && !confirm('Discard unsaved changes?')) return
  layout.value = makeDefaultNode('panel')
  layout.value.padding = 10
  layoutName.value = 'untitled'
  isDirty.value = true
  selectedPath.value = []
  historyStack.value = [JSON.stringify(layout.value)]
  historyIndex.value = 0
}

async function fetchLayoutList() {
  try {
    layoutList.value = await listUILayouts()
  } catch {
    layoutList.value = []
  }
}

async function onOpenLayout(name) {
  if (isDirty.value && !confirm('Discard unsaved changes?')) return
  try {
    const data = await getUILayout(name)
    layout.value = data
    layoutName.value = name
    isDirty.value = false
    selectedPath.value = []
    historyStack.value = [JSON.stringify(data)]
    historyIndex.value = 0
    showOpen.value = false
  } catch (e) {
    alert('Failed to load layout: ' + e.message)
  }
}

function onCreateNew() {
  const name = newLayoutName.value.trim().toLowerCase().replace(/\s+/g, '_') || 'untitled'
  if (isDirty.value && !confirm('Discard unsaved changes?')) return
  layout.value = makeDefaultNode('panel')
  layout.value.padding = 10
  layoutName.value = name
  isDirty.value = true
  selectedPath.value = []
  historyStack.value = [JSON.stringify(layout.value)]
  historyIndex.value = 0
  showOpen.value = false
  newLayoutName.value = ''
}

async function onSave() {
  if (!layout.value || !layoutName.value) return
  try {
    await saveUILayout(layoutName.value, layout.value)
    isDirty.value = false
  } catch (e) {
    alert('Failed to save: ' + e.message)
  }
}

watch(showOpen, (v) => { if (v) fetchLayoutList() })

// ─── Keyboard Shortcuts ──────────────────────────────────────────────────────
function onKeyDown(e) {
  if (e.ctrlKey || e.metaKey) {
    if (e.key === 'z') { e.preventDefault(); undo() }
    if (e.key === 'y') { e.preventDefault(); redo() }
    if (e.key === 's') { e.preventDefault(); onSave() }
  }
  if (e.key === 'Delete' && selectedPath.value.length > 0) {
    removeSelected()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeyDown)
})

// ─── Sub-components (inline) ─────────────────────────────────────────────────

// TreeNode: recursive tree item
const TreeNode = defineComponent({
  name: 'TreeNode',
  props: {
    node: { type: Object, required: true },
    path: { type: Array, required: true },
    selectedPath: { type: Array, required: true },
    depth: { type: Number, default: 0 },
  },
  emits: ['select', 'dragstart', 'drop'],
  setup(props, { emit }) {
    const collapsed = ref(false)
    const isSelected = computed(() =>
      props.selectedPath.length === props.path.length &&
      props.selectedPath.every((v, i) => v === props.path[i])
    )
    const hasChildren = computed(() =>
      props.node.children && props.node.children.length > 0
    )
    const nodeLabel = computed(() => {
      const n = props.node
      switch (n.type) {
        case 'label': return `label: "${(n.text || '').substring(0, 20)}"`
        case 'button': return `button: "${(n.label || '').substring(0, 20)}"`
        case 'panel': return `panel (${n.layout || 'column'})`
        case 'progressbar': return `progressbar`
        case 'checkbox': return `checkbox: "${(n.text || '').substring(0, 20)}"`
        case 'select': return `select: ${n.name || '?'}`
        case 'image': return `image ${n.width || 64}x${n.height || 64}`
        case 'space': return `space`
        default: return n.type
      }
    })

    return () => {
      const indent = props.depth * 16
      const items = []

      // This node's row
      items.push(
        h('div', {
          class: ['tree-row', { selected: isSelected.value }],
          style: { paddingLeft: indent + 'px' },
          draggable: props.path.length > 0,
          onClick: (e) => { e.stopPropagation(); emit('select', props.path) },
          onDragstart: (e) => { e.stopPropagation(); emit('dragstart', props.path) },
          onDragover: (e) => { e.preventDefault() },
          onDrop: (e) => { e.preventDefault(); e.stopPropagation(); emit('drop', props.path) },
        }, [
          hasChildren.value
            ? h('span', {
                class: ['tree-arrow', { open: !collapsed.value }],
                onClick: (e) => { e.stopPropagation(); collapsed.value = !collapsed.value },
              }, '\u25B6')
            : h('span', { class: 'tree-arrow-placeholder' }),
          h('span', { class: 'tree-icon' }, typeIcon(props.node.type)),
          h('span', { class: 'tree-label' }, nodeLabel.value),
        ])
      )

      // Children
      if (hasChildren.value && !collapsed.value) {
        props.node.children.forEach((child, i) => {
          items.push(
            h(TreeNode, {
              key: i,
              node: child,
              path: [...props.path, i],
              selectedPath: props.selectedPath,
              depth: props.depth + 1,
              onSelect: (p) => emit('select', p),
              onDragstart: (p) => emit('dragstart', p),
              onDrop: (p) => emit('drop', p),
            })
          )
        })
      }

      return h('div', { class: 'tree-node' }, items)
    }
  }
})

function typeIcon(type) {
  switch (type) {
    case 'panel': return '\u25A1'
    case 'label': return 'T'
    case 'button': return '\u25A3'
    case 'progressbar': return '\u2501'
    case 'checkbox': return '\u2611'
    case 'select': return '\u25BE'
    case 'image': return '\u25A8'
    case 'space': return '\u2508'
    default: return '?'
  }
}

// PreviewNode: recursive visual preview
const PreviewNode = defineComponent({
  name: 'PreviewNode',
  props: {
    node: { type: Object, required: true },
    selectedPath: { type: Array, required: true },
    currentPath: { type: Array, required: true },
  },
  emits: ['select'],
  setup(props, { emit }) {
    const isSelected = computed(() =>
      props.selectedPath.length === props.currentPath.length &&
      props.selectedPath.every((v, i) => v === props.currentPath[i])
    )

    return () => {
      const n = props.node
      const selected = isSelected.value
      const baseClick = (e) => { e.stopPropagation(); emit('select', props.currentPath) }

      switch (n.type) {
        case 'panel': {
          const isRow = n.layout === 'row'
          const style = {
            display: 'flex',
            flexDirection: isRow ? 'row' : 'column',
            padding: (n.padding || 0) + 'px',
            gap: (n.spacing || 0) + 'px',
            backgroundColor: n.backgroundColor || 'transparent',
            border: selected ? '2px solid #7b8ff5' : '1px dashed #444',
            borderRadius: '3px',
            minHeight: '24px',
            minWidth: '24px',
          }
          if (n.width) style.width = n.width + 'px'
          if (n.height) style.height = n.height + 'px'
          if (!n.width && (n.horizontalSizing || 'fill') === 'fill') style.width = '100%'

          const children = (n.children || []).map((child, i) =>
            h(PreviewNode, {
              key: i,
              node: child,
              selectedPath: props.selectedPath,
              currentPath: [...props.currentPath, i],
              onSelect: (p) => emit('select', p),
            })
          )

          return h('div', { class: 'pv-panel', style, onClick: baseClick }, children)
        }

        case 'label': {
          const style = {
            fontSize: (n.fontSize || 14) + 'px',
            color: n.color || '#eee',
            fontWeight: n.bold ? 'bold' : 'normal',
            border: selected ? '1px solid #7b8ff5' : '1px solid transparent',
            padding: '2px 4px',
            cursor: 'pointer',
          }
          return h('div', { class: 'pv-label', style, onClick: baseClick }, n.text || 'Label')
        }

        case 'button': {
          const style = {
            background: n.style === 'secondary' ? '#333' : '#4a4aff',
            color: '#fff',
            border: selected ? '2px solid #7b8ff5' : '1px solid #555',
            borderRadius: '4px',
            padding: '6px 14px',
            cursor: 'pointer',
            fontSize: '12px',
            textAlign: 'center',
            width: n.fullWidth ? '100%' : 'auto',
          }
          return h('div', { class: 'pv-button', style, onClick: baseClick }, n.label || 'Button')
        }

        case 'progressbar': {
          const val = parseFloat(n.value) || 0
          const pct = Math.min(100, Math.max(0, val * 100))
          const style = {
            height: (n.height || 18) + 'px',
            background: '#222',
            borderRadius: '3px',
            overflow: 'hidden',
            border: selected ? '2px solid #7b8ff5' : '1px solid #444',
            width: '100%',
            cursor: 'pointer',
          }
          const fillStyle = {
            width: pct + '%',
            height: '100%',
            background: n.color || '#0088ff',
            transition: 'width 0.2s',
          }
          return h('div', { class: 'pv-progressbar', style, onClick: baseClick }, [
            h('div', { style: fillStyle })
          ])
        }

        case 'checkbox': {
          const style = {
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            fontSize: '12px',
            color: '#ddd',
            border: selected ? '1px solid #7b8ff5' : '1px solid transparent',
            padding: '2px 4px',
            cursor: 'pointer',
          }
          return h('div', { class: 'pv-checkbox', style, onClick: baseClick }, [
            h('span', { style: { display: 'inline-block', width: '14px', height: '14px', border: '1px solid #888', borderRadius: '2px', background: '#2a2a3e' } }),
            h('span', {}, n.text || 'Checkbox'),
          ])
        }

        case 'select': {
          const style = {
            background: '#2a2a3e',
            border: selected ? '2px solid #7b8ff5' : '1px solid #555',
            borderRadius: '3px',
            padding: '4px 8px',
            color: '#ddd',
            fontSize: '12px',
            cursor: 'pointer',
            minWidth: '80px',
          }
          const label = n.selected || (n.options && n.options[0]) || n.name || 'Select'
          return h('div', { class: 'pv-select', style, onClick: baseClick }, label + ' \u25BE')
        }

        case 'image': {
          const style = {
            width: (n.width || 64) + 'px',
            height: (n.height || 64) + 'px',
            background: n.color || '#555',
            borderRadius: '4px',
            border: selected ? '2px solid #7b8ff5' : '1px solid #444',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: '#999',
            fontSize: '10px',
            cursor: 'pointer',
          }
          return h('div', { class: 'pv-image', style, onClick: baseClick }, 'IMG')
        }

        case 'space': {
          const style = {
            width: (n.width || 0) + 'px',
            height: (n.height || 0) + 'px',
            minWidth: '4px',
            minHeight: '4px',
            border: selected ? '1px solid #7b8ff5' : '1px dashed #333',
            cursor: 'pointer',
          }
          return h('div', { class: 'pv-space', style, onClick: baseClick })
        }

        default:
          return h('div', { onClick: baseClick }, '[unknown: ' + n.type + ']')
      }
    }
  }
})
</script>

<style scoped>
.ui-editor {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  background: #1a1a2e;
  color: #eee;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 12px;
  overflow: hidden;
}

/* ─── Toolbar ──────────────────────────────────────────────────────────────── */
.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 12px;
  height: 36px;
  background: #0f0f1a;
  border-bottom: 1px solid #333;
  flex-shrink: 0;
}
.toolbar-title { font-weight: bold; color: #7b8ff5; font-size: 12px; }
.toolbar-group { display: flex; gap: 4px; }
.toolbar-group.right { margin-left: auto; }
.layout-name { color: #aaa; font-size: 11px; margin-left: 8px; }
.toolbar button, .tree-actions button, .add-btn {
  background: #2a2a3e;
  border: 1px solid #444;
  color: #ddd;
  padding: 3px 10px;
  cursor: pointer;
  border-radius: 3px;
  font-size: 12px;
}
.toolbar button:hover, .tree-actions button:hover, .add-btn:hover {
  background: #3a3a5e;
}
.toolbar button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.toolbar button.dirty { border-color: #f5a623; }

/* ─── Editor Body (3 columns) ──────────────────────────────────────────────── */
.editor-body {
  flex: 1;
  display: flex;
  overflow: hidden;
}

/* ─── Left Panel: Tree ─────────────────────────────────────────────────────── */
.panel-left {
  width: 240px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #2a2a3e;
  overflow: hidden;
}
.panel-header {
  padding: 6px 8px;
  border-bottom: 1px solid #333;
  font-size: 11px;
  font-weight: bold;
  color: #aaa;
  text-transform: uppercase;
  letter-spacing: .05em;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.add-root-group {
  display: flex;
  gap: 3px;
  align-items: center;
}
.add-select {
  background: #2a2a3e;
  border: 1px solid #444;
  color: #ddd;
  padding: 1px 4px;
  border-radius: 3px;
  font-size: 10px;
  height: 22px;
}
.add-btn {
  padding: 1px 6px !important;
  font-size: 14px !important;
  line-height: 1;
}
.tree-container {
  flex: 1;
  overflow-y: auto;
  padding: 4px 0;
}
.tree-actions {
  padding: 6px 8px;
  border-top: 1px solid #333;
  display: flex;
  gap: 4px;
  flex-shrink: 0;
  flex-wrap: wrap;
}
.tree-actions button { font-size: 10px; padding: 3px 6px; }

.empty-hint {
  padding: 12px 8px;
  color: #555;
  font-size: 11px;
}

/* Tree node styles */
:deep(.tree-node) { user-select: none; }
:deep(.tree-row) {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  cursor: pointer;
  border-radius: 2px;
  font-size: 11px;
  color: #ccc;
  white-space: nowrap;
}
:deep(.tree-row:hover) { background: #2a2a3e; }
:deep(.tree-row.selected) { background: #2a2a5e; color: #fff; }
:deep(.tree-arrow) {
  font-size: 8px;
  width: 12px;
  text-align: center;
  transition: transform 0.15s;
  display: inline-block;
  color: #888;
  flex-shrink: 0;
}
:deep(.tree-arrow.open) { transform: rotate(90deg); }
:deep(.tree-arrow-placeholder) { width: 12px; flex-shrink: 0; }
:deep(.tree-icon) {
  width: 14px;
  text-align: center;
  color: #7b8ff5;
  flex-shrink: 0;
  font-size: 11px;
}
:deep(.tree-label) {
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ─── Center Panel: Preview ────────────────────────────────────────────────── */
.panel-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.preview-scroll {
  flex: 1;
  overflow: auto;
  padding: 16px;
  background: #12121e;
}
.preview-canvas {
  min-width: 200px;
  min-height: 200px;
}

/* ─── Right Panel: Properties ──────────────────────────────────────────────── */
.panel-right {
  width: 260px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  border-left: 1px solid #2a2a3e;
  overflow: hidden;
}
.props-container {
  flex: 1;
  overflow-y: auto;
}

/* Inspector sections */
.section {
  padding: 8px;
  border-bottom: 1px solid #222;
}
.section-title {
  font-size: 10px;
  color: #7b8ff5;
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 6px;
}
.type-badge {
  display: inline-block;
  background: #2a2a5e;
  border: 1px solid #7b8ff5;
  color: #7b8ff5;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: bold;
}

label {
  display: flex;
  flex-direction: column;
  font-size: 11px;
  color: #888;
  margin-bottom: 5px;
  gap: 2px;
}
.checkbox-label {
  flex-direction: row;
  align-items: center;
  gap: 6px;
  color: #ccc;
  cursor: pointer;
}
.checkbox-label input[type="checkbox"] {
  width: auto;
}

input, select {
  background: #2a2a3e;
  border: 1px solid #444;
  color: #eee;
  padding: 3px 6px;
  border-radius: 3px;
  font-size: 12px;
  width: 100%;
  box-sizing: border-box;
}
input:focus, select:focus { outline: 1px solid #4a4aff; }

.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }

.color-row {
  display: flex;
  gap: 4px;
  align-items: center;
}
.color-row input[type="text"] { flex: 1; }
.color-picker {
  width: 28px !important;
  height: 24px;
  padding: 0 !important;
  border: 1px solid #444;
  cursor: pointer;
  background: transparent;
}

.option-row {
  display: flex;
  gap: 4px;
  margin-bottom: 4px;
  align-items: center;
}
.option-row input { flex: 1; }
.prop-del {
  background: transparent;
  border: 1px solid #444;
  color: #f66;
  width: 20px;
  height: 20px;
  cursor: pointer;
  border-radius: 3px;
  font-size: 10px;
  padding: 0;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.prop-del:hover { border-color: #f66; }
.add-prop {
  background: transparent;
  border: 1px dashed #444;
  color: #666;
  padding: 3px 8px;
  cursor: pointer;
  border-radius: 3px;
  font-size: 11px;
  margin-top: 4px;
  width: 100%;
}
.add-prop:hover { border-color: #7b8ff5; color: #7b8ff5; }

.json-preview {
  background: #0f0f1a;
  border: 1px solid #333;
  border-radius: 3px;
  padding: 6px;
  font-size: 10px;
  color: #888;
  overflow-x: auto;
  max-height: 200px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-all;
  font-family: 'SF Mono', 'Fira Code', monospace;
  margin: 0;
}

.action-btn.delete { color: #f66; border-color: #633; }
.action-btn.delete:hover { background: #3a1a1a; border-color: #f66; }

/* ─── Popup ────────────────────────────────────────────────────────────────── */
.popup {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
}
.popup-box {
  background: #1e1e30;
  border: 1px solid #555;
  border-radius: 6px;
  padding: 20px;
  min-width: 320px;
  position: relative;
}
.popup-box h3 { margin: 0 0 12px; font-size: 14px; }
.new-row { display: flex; gap: 6px; margin-bottom: 12px; }
.new-row input {
  flex: 1;
  background: #2a2a3e;
  border: 1px solid #444;
  color: #eee;
  padding: 4px 8px;
  border-radius: 3px;
}
.new-row button {
  background: #2a2a3e;
  border: 1px solid #444;
  color: #ddd;
  padding: 4px 10px;
  cursor: pointer;
  border-radius: 3px;
  font-size: 12px;
}
.new-row button:hover { background: #3a3a5e; }
.layout-list { max-height: 200px; overflow-y: auto; }
.layout-item {
  display: flex;
  justify-content: space-between;
  padding: 6px 8px;
  cursor: pointer;
  border-radius: 3px;
}
.layout-item:hover { background: #2a2a3e; }
.layout-item small { color: #888; }
.empty-msg { color: #666; font-size: 11px; }
.close-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  background: transparent;
  border: none;
  color: #888;
  font-size: 14px;
  cursor: pointer;
}
.close-btn:hover { color: #eee; }
</style>
