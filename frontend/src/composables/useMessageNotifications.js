import { ref } from 'vue'

const permission = ref(typeof Notification === 'undefined' ? 'unsupported' : Notification.permission)
let audioContext = null
let audioUnlocked = false

function unlockAudio() {
  if (audioUnlocked || typeof window === 'undefined') return
  try {
    const Ctor = window.AudioContext || window.webkitAudioContext
    if (!Ctor) {
      audioUnlocked = true
      return
    }
    audioContext = audioContext || new Ctor()
    const source = audioContext.createBufferSource()
    const gain = audioContext.createGain()
    gain.gain.value = 0.0001
    source.connect(gain).connect(audioContext.destination)
    source.start()
    audioUnlocked = true
  } catch {
    audioUnlocked = true
  }
}

if (typeof window !== 'undefined') {
  window.addEventListener('pointerdown', unlockAudio, { once: true })
  window.addEventListener('keydown', unlockAudio, { once: true })
}

function playIncomingSound() {
  try {
    const Ctor = window.AudioContext || window.webkitAudioContext
    if (!Ctor) return
    audioContext = audioContext || new Ctor()
    if (audioContext.state === 'suspended') audioContext.resume()
    const oscillator = audioContext.createOscillator()
    const gain = audioContext.createGain()
    oscillator.type = 'sine'
    oscillator.frequency.value = 880
    gain.gain.value = 0.0001
    oscillator.connect(gain).connect(audioContext.destination)
    const start = audioContext.currentTime
    gain.gain.exponentialRampToValueAtTime(0.08, start + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.18)
    oscillator.start(start)
    oscillator.stop(start + 0.2)
  } catch {
    // Ignore audio failures and keep in-app badges functional.
  }
}

async function requestPermission() {
  if (typeof Notification === 'undefined') return permission.value
  if (Notification.permission === 'granted') {
    permission.value = 'granted'
    return permission.value
  }
  if (Notification.permission === 'denied') {
    permission.value = 'denied'
    return permission.value
  }
  permission.value = await Notification.requestPermission()
  return permission.value
}

function notify({ title, body, tag }) {
  if (typeof Notification === 'undefined') return
  if (document.visibilityState === 'visible') return
  if (Notification.permission !== 'granted') return
  try {
    const notification = new Notification(title, {
      body,
      tag: tag || undefined,
      silent: true,
    })
    notification.onclick = () => window.focus()
  } catch {
    // Ignore Notification API failures.
  }
}

export function useMessageNotifications() {
  return {
    permission,
    requestPermission,
    notify,
    playIncomingSound,
  }
}
