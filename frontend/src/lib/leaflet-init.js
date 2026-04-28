/**
 * Leaflet initialization
 */
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Make Leaflet globally available for plugins
if (typeof window !== 'undefined') {
  window.L = L
}

// Fix default icon paths for webpack/vite
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
})

export default L
