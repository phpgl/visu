import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useWorldStore } from '../stores/world.js'

// Mock api.js
vi.mock('../api.js', () => ({
  getConfig: vi.fn().mockResolvedValue({ tileSize: 32, gridWidth: 32, gridHeight: 32 }),
  listWorlds: vi.fn().mockResolvedValue([]),
  getWorld: vi.fn().mockResolvedValue({
    version: '1.0',
    meta: { name: 'Test', type: '2d_topdown', tileSize: 32 },
    camera: { position: { x: 0, y: 0 }, zoom: 1.0 },
    layers: [
      { id: 'bg', name: 'Background', type: 'tile', visible: true, locked: false, tiles: {} },
      { id: 'entities', name: 'Entities', type: 'entity', visible: true, locked: false, entities: [] },
    ],
    lights: [],
    tilesets: [],
  }),
  saveWorld: vi.fn().mockResolvedValue({ ok: true }),
  browseAssets: vi.fn().mockResolvedValue({ path: '', entries: [] }),
  listScenes: vi.fn().mockResolvedValue([]),
}))

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('useWorldStore', () => {
  // ── Initial state ────────────────────────────────────────────────────

  describe('initial state', () => {
    it('starts with no world loaded', () => {
      const store = useWorldStore()
      expect(store.world).toBeNull()
      expect(store.worldName).toBeNull()
      expect(store.isDirty).toBe(false)
    })

    it('has default config', () => {
      const store = useWorldStore()
      expect(store.config.tileSize).toBe(32)
    })

    it('has select as default tool', () => {
      const store = useWorldStore()
      expect(store.selectedTool).toBe('select')
    })

    it('has no selected entity', () => {
      const store = useWorldStore()
      expect(store.selectedEntityId).toBeNull()
      expect(store.selectedEntity).toBeNull()
    })
  })

  // ── newWorld ──────────────────────────────────────────────────────────

  describe('newWorld', () => {
    it('creates a default world with two layers', () => {
      const store = useWorldStore()
      store.newWorld('Test Level')

      expect(store.world).not.toBeNull()
      expect(store.world.meta.name).toBe('Test Level')
      expect(store.world.layers).toHaveLength(2)
      expect(store.world.layers[0].type).toBe('tile')
      expect(store.world.layers[1].type).toBe('entity')
    })

    it('sets worldName as slug', () => {
      const store = useWorldStore()
      store.newWorld('My World')
      expect(store.worldName).toBe('my_world')
    })

    it('marks as dirty', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      expect(store.isDirty).toBe(true)
    })

    it('initializes history with one snapshot', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      // History has initial state
      expect(store.world).not.toBeNull()
    })
  })

  // ── loadWorld ─────────────────────────────────────────────────────────

  describe('loadWorld', () => {
    it('loads world from API', async () => {
      const store = useWorldStore()
      await store.loadWorld('test')
      expect(store.world).not.toBeNull()
      expect(store.world.meta.name).toBe('Test')
      expect(store.worldName).toBe('test')
      expect(store.isDirty).toBe(false)
    })

    it('sets active layer to first layer', async () => {
      const store = useWorldStore()
      await store.loadWorld('test')
      expect(store.activeLayerId).toBe('bg')
    })
  })

  // ── Layer operations ──────────────────────────────────────────────────

  describe('layer operations', () => {
    it('addLayer adds a tile layer', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.addLayer('tile')
      expect(store.world.layers).toHaveLength(3)
      expect(store.world.layers[2].type).toBe('tile')
    })

    it('addLayer adds an entity layer', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.addLayer('entity')
      expect(store.world.layers).toHaveLength(3)
      expect(store.world.layers[2].type).toBe('entity')
    })

    it('removeLayer removes a layer', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      const id = store.world.layers[0].id
      store.removeLayer(id)
      expect(store.world.layers).toHaveLength(1)
    })

    it('renameLayer changes layer name', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.renameLayer('bg', 'My Background')
      expect(store.world.layers[0].name).toBe('My Background')
    })

    it('toggleLayerVisibility toggles visible flag', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      expect(store.world.layers[0].visible).toBe(true)
      store.toggleLayerVisibility('bg')
      expect(store.world.layers[0].visible).toBe(false)
    })

    it('toggleLayerLock toggles locked flag', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      expect(store.world.layers[0].locked).toBe(false)
      store.toggleLayerLock('bg')
      expect(store.world.layers[0].locked).toBe(true)
    })

    it('setActiveLayer changes active layer', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      expect(store.activeLayerId).toBe('entities')
    })
  })

  // ── Entity operations ─────────────────────────────────────────────────

  describe('entity operations', () => {
    it('placeEntity adds an entity to active layer', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'player_spawn'
      store.placeEntity(100, 200)

      const layer = store.world.layers.find(l => l.id === 'entities')
      expect(layer.entities).toHaveLength(1)
      expect(layer.entities[0].position).toEqual({ x: 100, y: 200 })
      expect(layer.entities[0].type).toBe('player_spawn')
    })

    it('placeEntity does nothing on locked layer', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.world.layers[1].locked = true
      store.selectedEntityType = 'player_spawn'
      store.placeEntity(100, 200)

      expect(store.world.layers[1].entities).toHaveLength(0)
    })

    it('selectEntityAt selects entity within radius', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'npc'
      store.placeEntity(100, 100)

      store.selectEntityAt(105, 105) // within default 16px radius
      expect(store.selectedEntityId).not.toBeNull()
    })

    it('selectEntityAt deselects when no hit', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'npc'
      store.placeEntity(100, 100)

      store.selectEntityAt(500, 500) // far away
      expect(store.selectedEntityId).toBeNull()
    })

    it('deleteSelectedEntity removes the entity', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'enemy_spawn'
      store.placeEntity(50, 50)

      const entityId = store.selectedEntityId
      expect(entityId).not.toBeNull()

      store.deleteSelectedEntity()
      expect(store.selectedEntityId).toBeNull()
      expect(store.world.layers[1].entities).toHaveLength(0)
    })

    it('duplicateEntity creates a copy offset by 32px', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'item'
      store.placeEntity(100, 100)

      store.duplicateEntity()
      expect(store.world.layers[1].entities).toHaveLength(2)
      const copy = store.world.layers[1].entities[1]
      expect(copy.position.x).toBe(132)
      expect(copy.position.y).toBe(132)
      expect(copy.name).toContain('(copy)')
    })

    it('moveEntityTo updates position', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'prop'
      store.placeEntity(10, 20)

      const eid = store.world.layers[1].entities[0].id
      store.moveEntityTo(eid, 300, 400)
      expect(store.world.layers[1].entities[0].position).toEqual({ x: 300, y: 400 })
    })

    it('updateSelectedEntity patches properties', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'npc'
      store.placeEntity(0, 0)

      store.updateSelectedEntity({ name: 'Guard', rotation: 45 })
      expect(store.selectedEntity.name).toBe('Guard')
      expect(store.selectedEntity.rotation).toBe(45)
    })
  })

  // ── Tile operations ───────────────────────────────────────────────────

  describe('tile operations', () => {
    it('placeTile sets tile at grid position', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('bg')
      store.selectedTile = { tilesetId: 'ts1', tx: 0, ty: 0 }
      store.placeTile(3, 5)

      expect(store.world.layers[0].tiles['3,5']).toBeDefined()
      expect(store.world.layers[0].tiles['3,5'].tilesetId).toBe('ts1')
    })

    it('eraseTile removes tile at grid position', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('bg')
      store.selectedTile = { tilesetId: 'ts1', tx: 0, ty: 0 }
      store.placeTile(3, 5)
      store.eraseTile(3, 5)

      expect(store.world.layers[0].tiles['3,5']).toBeUndefined()
    })
  })

  // ── Undo/Redo ─────────────────────────────────────────────────────────

  describe('undo/redo', () => {
    it('undo reverts to previous state', () => {
      const store = useWorldStore()
      store.newWorld('Test')

      store.setActiveLayer('entities')
      store.selectedEntityType = 'npc'
      store.placeEntity(100, 100) // This calls snapshot()

      expect(store.world.layers[1].entities).toHaveLength(1)

      store.undo()
      expect(store.world.layers[1].entities).toHaveLength(0)
    })

    it('redo re-applies undone state', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'npc'
      store.placeEntity(100, 100)

      store.undo()
      expect(store.world.layers[1].entities).toHaveLength(0)

      store.redo()
      expect(store.world.layers[1].entities).toHaveLength(1)
    })

    it('undo at beginning does nothing', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      const before = JSON.stringify(store.world)
      store.undo()
      expect(JSON.stringify(store.world)).toBe(before)
    })
  })

  // ── Tool switching ────────────────────────────────────────────────────

  describe('tool switching', () => {
    it('setActiveTool changes tool and deselects', () => {
      const store = useWorldStore()
      store.newWorld('Test')
      store.setActiveLayer('entities')
      store.selectedEntityType = 'npc'
      store.placeEntity(0, 0)

      store.setActiveTool('erase')
      expect(store.selectedTool).toBe('erase')
      expect(store.selectedEntityId).toBeNull()
    })
  })

  // ── Asset browser ─────────────────────────────────────────────────────

  describe('asset browser', () => {
    it('browseAssets updates state', async () => {
      const store = useWorldStore()
      await store.browseAssets('shaders')
      expect(store.assetPath).toBe('')
      expect(store.assetEntries).toEqual([])
      expect(store.assetLoading).toBe(false)
    })
  })
})
