import { describe, it, expect, vi, beforeEach } from 'vitest'

// Mock fetch globally
const mockFetch = vi.fn()
global.fetch = mockFetch

import {
  listWorlds, getWorld, saveWorld, deleteWorld, getConfig,
  patchEntity, removeEntity, browseAssets,
  listScenes, getScene, saveScene,
  listUILayouts, getUILayout, saveUILayout,
  transpileAll,
} from '../api.js'

function mockJsonResponse(data, ok = true) {
  return { ok, json: () => Promise.resolve(data) }
}

beforeEach(() => {
  mockFetch.mockReset()
})

describe('api.js', () => {
  // ── Worlds ────────────────────────────────────────────────────────────

  describe('listWorlds', () => {
    it('fetches GET /api/worlds', async () => {
      const worlds = [{ name: 'test', modified: '2026-01-01', size: 100 }]
      mockFetch.mockResolvedValue(mockJsonResponse(worlds))

      const result = await listWorlds()
      expect(mockFetch).toHaveBeenCalledWith('/api/worlds')
      expect(result).toEqual(worlds)
    })
  })

  describe('getWorld', () => {
    it('fetches GET /api/worlds/{name}', async () => {
      const world = { version: '1.0', meta: { name: 'Test' } }
      mockFetch.mockResolvedValue(mockJsonResponse(world))

      const result = await getWorld('test')
      expect(mockFetch).toHaveBeenCalledWith('/api/worlds/test')
      expect(result).toEqual(world)
    })

    it('throws on 404', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({}, false))
      await expect(getWorld('missing')).rejects.toThrow('not found')
    })
  })

  describe('saveWorld', () => {
    it('sends POST with JSON body', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ ok: true }))
      const data = { version: '1.0', layers: [] }

      await saveWorld('myworld', data)
      expect(mockFetch).toHaveBeenCalledWith('/api/worlds/myworld', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      })
    })

    it('throws on failure', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({}, false))
      await expect(saveWorld('x', {})).rejects.toThrow('Failed to save')
    })
  })

  describe('deleteWorld', () => {
    it('sends DELETE', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ ok: true }))
      await deleteWorld('old')
      expect(mockFetch).toHaveBeenCalledWith('/api/worlds/old', { method: 'DELETE' })
    })
  })

  describe('getConfig', () => {
    it('fetches GET /api/config', async () => {
      const config = { tileSize: 32, gridWidth: 32 }
      mockFetch.mockResolvedValue(mockJsonResponse(config))
      const result = await getConfig()
      expect(result).toEqual(config)
    })
  })

  // ── Entity operations ─────────────────────────────────────────────────

  describe('patchEntity', () => {
    it('sends PATCH with entity path', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ ok: true }))
      const patch = { name: 'Updated' }

      await patchEntity('world1', 'entities', 42, patch)
      expect(mockFetch).toHaveBeenCalledWith(
        '/api/worlds/world1/entities/entities/42',
        expect.objectContaining({ method: 'PATCH', body: JSON.stringify(patch) })
      )
    })
  })

  describe('removeEntity', () => {
    it('sends DELETE with entity path', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ ok: true }))
      await removeEntity('world1', 'entities', 42)
      expect(mockFetch).toHaveBeenCalledWith(
        '/api/worlds/world1/entities/entities/42',
        { method: 'DELETE' }
      )
    })
  })

  // ── Assets ────────────────────────────────────────────────────────────

  describe('browseAssets', () => {
    it('fetches root without query param', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ path: '', entries: [] }))
      await browseAssets()
      expect(mockFetch).toHaveBeenCalledWith('/api/assets/browse')
    })

    it('passes dir as query param', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ path: 'shaders', entries: [] }))
      await browseAssets('shaders')
      expect(mockFetch).toHaveBeenCalledWith('/api/assets/browse?dir=shaders')
    })
  })

  // ── Scenes ────────────────────────────────────────────────────────────

  describe('listScenes', () => {
    it('fetches GET /api/scenes', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse([]))
      await listScenes()
      expect(mockFetch).toHaveBeenCalledWith('/api/scenes')
    })
  })

  describe('getScene', () => {
    it('fetches scene by name', async () => {
      const scene = { entities: [] }
      mockFetch.mockResolvedValue(mockJsonResponse(scene))
      const result = await getScene('level1')
      expect(result).toEqual(scene)
    })
  })

  describe('saveScene', () => {
    it('sends POST with scene data', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ ok: true }))
      await saveScene('level1', { entities: [] })
      expect(mockFetch).toHaveBeenCalledWith('/api/scenes/level1', expect.objectContaining({ method: 'POST' }))
    })
  })

  // ── UI Layouts ────────────────────────────────────────────────────────

  describe('listUILayouts', () => {
    it('fetches GET /api/ui', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse([]))
      await listUILayouts()
      expect(mockFetch).toHaveBeenCalledWith('/api/ui')
    })
  })

  describe('getUILayout', () => {
    it('fetches layout by name', async () => {
      const layout = { type: 'panel', children: [] }
      mockFetch.mockResolvedValue(mockJsonResponse(layout))
      const result = await getUILayout('hud')
      expect(result).toEqual(layout)
    })

    it('throws on 404', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({}, false))
      await expect(getUILayout('missing')).rejects.toThrow('not found')
    })
  })

  describe('saveUILayout', () => {
    it('sends POST with layout data', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ ok: true }))
      await saveUILayout('hud', { type: 'panel' })
      expect(mockFetch).toHaveBeenCalledWith('/api/ui/hud', expect.objectContaining({ method: 'POST' }))
    })
  })

  // ── Transpile ─────────────────────────────────────────────────────────

  describe('transpileAll', () => {
    it('sends POST /api/transpile', async () => {
      mockFetch.mockResolvedValue(mockJsonResponse({ ok: true, results: {} }))
      const result = await transpileAll()
      expect(mockFetch).toHaveBeenCalledWith('/api/transpile', { method: 'POST' })
      expect(result.ok).toBe(true)
    })
  })
})
