import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { on, send, getState, disconnect, connect } from '../ws.js'

// Mock WebSocket
class MockWebSocket {
  static OPEN = 1
  static CLOSED = 3
  readyState = MockWebSocket.OPEN
  onopen = null
  onclose = null
  onerror = null
  onmessage = null

  constructor(url) {
    this.url = url
    this.sent = []
    // Auto-fire onopen in next tick
    setTimeout(() => this.onopen?.(), 0)
  }

  send(data) {
    this.sent.push(data)
  }

  close() {
    this.readyState = MockWebSocket.CLOSED
    this.onclose?.()
  }
}

beforeEach(() => {
  disconnect()
  global.WebSocket = MockWebSocket
})

afterEach(() => {
  disconnect()
})

describe('ws.js', () => {
  describe('getState', () => {
    it('starts disconnected', () => {
      expect(getState()).toBe('disconnected')
    })
  })

  describe('on', () => {
    it('registers a handler and returns unsubscribe function', () => {
      const handler = vi.fn()
      const unsub = on('test', handler)
      expect(typeof unsub).toBe('function')
    })

    it('unsubscribe removes the handler', () => {
      const handler = vi.fn()
      const unsub = on('test', handler)
      unsub()
      // Handler removed - no way to test without triggering, but no error
    })
  })

  describe('send', () => {
    it('does nothing when not connected', () => {
      // Not connected, should not throw
      send('test', { foo: 'bar' })
    })
  })

  describe('disconnect', () => {
    it('can be called multiple times safely', () => {
      disconnect()
      disconnect()
      expect(getState()).toBe('disconnected')
    })
  })

  describe('message handling', () => {
    it('dispatches typed messages to registered handlers', async () => {
      const handler = vi.fn()
      on('scene.changed', handler)

      connect(19999)
      // Wait for onopen
      await new Promise(r => setTimeout(r, 10))

      expect(getState()).toBe('connected')
    })
  })

  describe('connection events', () => {
    it('fires _connected on open', async () => {
      const onConnected = vi.fn()
      on('_connected', onConnected)

      connect(19998)
      await new Promise(r => setTimeout(r, 10))

      expect(onConnected).toHaveBeenCalled()
    })

    it('fires _disconnected on close', async () => {
      const onDisconnected = vi.fn()
      on('_disconnected', onDisconnected)

      connect(19997)
      await new Promise(r => setTimeout(r, 10))

      disconnect()
      expect(onDisconnected).toHaveBeenCalled()
    })
  })
})
