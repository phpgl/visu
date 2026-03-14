const BASE = '/api'

export async function listWorlds() {
  const r = await fetch(`${BASE}/worlds`)
  return r.json()
}

export async function getWorld(name) {
  const r = await fetch(`${BASE}/worlds/${name}`)
  if (!r.ok) throw new Error(`World "${name}" not found`)
  return r.json()
}

export async function saveWorld(name, data) {
  const r = await fetch(`${BASE}/worlds/${name}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  if (!r.ok) throw new Error('Failed to save world')
  return r.json()
}

export async function deleteWorld(name) {
  const r = await fetch(`${BASE}/worlds/${name}`, { method: 'DELETE' })
  if (!r.ok) throw new Error('Failed to delete world')
  return r.json()
}

export async function getConfig() {
  const r = await fetch(`${BASE}/config`)
  return r.json()
}

// Entity PATCH
export async function patchEntity(worldName, layerId, entityId, patch) {
  const r = await fetch(`${BASE}/worlds/${worldName}/entities/${layerId}/${entityId}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(patch),
  })
  if (!r.ok) throw new Error('Failed to patch entity')
  return r.json()
}

// Entity DELETE
export async function removeEntity(worldName, layerId, entityId) {
  const r = await fetch(`${BASE}/worlds/${worldName}/entities/${layerId}/${entityId}`, {
    method: 'DELETE',
  })
  if (!r.ok) throw new Error('Failed to delete entity')
  return r.json()
}

// Asset browser
export async function browseAssets(dir = '') {
  const params = dir ? `?dir=${encodeURIComponent(dir)}` : ''
  const r = await fetch(`${BASE}/assets/browse${params}`)
  return r.json()
}

// Scene API
export async function listScenes() {
  const r = await fetch(`${BASE}/scenes`)
  return r.json()
}

export async function getScene(name) {
  const r = await fetch(`${BASE}/scenes/${name}`)
  if (!r.ok) throw new Error(`Scene "${name}" not found`)
  return r.json()
}

export async function saveScene(name, data) {
  const r = await fetch(`${BASE}/scenes/${name}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  if (!r.ok) throw new Error('Failed to save scene')
  return r.json()
}

// Transpile
export async function transpileAll() {
  const r = await fetch(`${BASE}/transpile`, { method: 'POST' })
  return r.json()
}

// UI Layout API
export async function listUILayouts() {
  const r = await fetch(`${BASE}/ui`)
  return r.json()
}

export async function getUILayout(name) {
  const r = await fetch(`${BASE}/ui/${name}`)
  if (!r.ok) throw new Error(`UI layout "${name}" not found`)
  return r.json()
}

export async function saveUILayout(name, data) {
  const r = await fetch(`${BASE}/ui/${name}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
  if (!r.ok) throw new Error('Failed to save UI layout')
  return r.json()
}
