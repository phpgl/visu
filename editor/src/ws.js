/**
 * WebSocket client for VISU World Editor live-preview communication.
 *
 * Connects to the WebSocket server started by `bin/visu world-editor`.
 * Handles automatic reconnection and message routing.
 */

let ws = null
let reconnectTimer = null
const handlers = {}
let connectionState = 'disconnected' // 'connecting' | 'connected' | 'disconnected'

/**
 * Connect to the WebSocket server.
 * @param {number} [port=8766] WebSocket server port
 */
export function connect(port = 8766) {
  if (ws && ws.readyState === WebSocket.OPEN) return

  connectionState = 'connecting'
  const url = `ws://${location.hostname}:${port}`

  try {
    ws = new WebSocket(url)
  } catch (e) {
    connectionState = 'disconnected'
    scheduleReconnect(port)
    return
  }

  ws.onopen = () => {
    connectionState = 'connected'
    if (reconnectTimer) {
      clearTimeout(reconnectTimer)
      reconnectTimer = null
    }
    dispatch('_connected', {})
  }

  ws.onclose = () => {
    connectionState = 'disconnected'
    ws = null
    dispatch('_disconnected', {})
    scheduleReconnect(port)
  }

  ws.onerror = () => {
    // onclose will fire after this
  }

  ws.onmessage = (event) => {
    try {
      const msg = JSON.parse(event.data)
      if (msg.type) {
        dispatch(msg.type, msg.data || {})
      }
    } catch (e) {
      // Ignore malformed messages
    }
  }
}

/**
 * Send a message to the WebSocket server.
 * @param {string} type Message type
 * @param {object} [data={}] Message data
 */
export function send(type, data = {}) {
  if (!ws || ws.readyState !== WebSocket.OPEN) return
  ws.send(JSON.stringify({ type, data }))
}

/**
 * Register a handler for a message type.
 * @param {string} type Message type
 * @param {function} callback Handler function(data)
 * @returns {function} Unsubscribe function
 */
export function on(type, callback) {
  if (!handlers[type]) handlers[type] = []
  handlers[type].push(callback)
  return () => {
    handlers[type] = handlers[type].filter(h => h !== callback)
  }
}

/**
 * Get current connection state.
 * @returns {'connecting'|'connected'|'disconnected'}
 */
export function getState() {
  return connectionState
}

/**
 * Disconnect from the WebSocket server.
 */
export function disconnect() {
  if (reconnectTimer) {
    clearTimeout(reconnectTimer)
    reconnectTimer = null
  }
  if (ws) {
    ws.close()
    ws = null
  }
  connectionState = 'disconnected'
}

function dispatch(type, data) {
  const typeHandlers = handlers[type] || []
  for (const handler of typeHandlers) {
    try {
      handler(data)
    } catch (e) {
      console.error(`[WS] Handler error for "${type}":`, e)
    }
  }
}

function scheduleReconnect(port) {
  if (reconnectTimer) return
  reconnectTimer = setTimeout(() => {
    reconnectTimer = null
    connect(port)
  }, 3000)
}
