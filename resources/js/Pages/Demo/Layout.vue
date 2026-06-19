<template>
  <div :class="['demo-root', isDark ? 'demo-dark' : 'demo-light']" id="demo-root">

    <!-- LOADING SCREEN -->
    <transition name="fade-load">
      <div v-if="loading" class="loading-screen">
        <div class="loading-logo">Y·200</div>
        <div class="loading-text">Cargando Zona Comercial...</div>
        <div class="loading-bar"><div class="loading-fill" :style="{ width: progress + '%' }"></div></div>
        <div class="loading-pct">{{ Math.round(progress) }}%</div>
      </div>
    </transition>

    <!-- NAVBAR (desktop / landscape) -->
    <header v-if="!isMobilePortrait" class="demo-nav">
      <div class="nav-left">
        <a href="/" class="btn-volver">← Volver</a>
        <div class="logo-y200">Y·200</div>
        <span class="nav-title">ZONA COMERCIAL — Layout Interactivo</span>
      </div>
      <div class="nav-right">
        <div class="nav-avail">
          <span class="nav-avail-num">{{ disponibles }}</span>
          <span class="nav-avail-sep">de {{ SPACES.length }}</span>
          <span class="nav-avail-lbl">disponibles</span>
        </div>
        <div class="nav-avail-track"><div class="nav-avail-fill" :style="{ width: (disponibles/SPACES.length*100)+'%' }"></div></div>
        <button class="theme-toggle" @click="toggleTheme" :title="isDark ? 'Modo claro' : 'Modo oscuro'">{{ isDark ? '☀️' : '🌙' }}</button>
      </div>
    </header>

    <!-- MAIN LAYOUT (flex column) -->
    <div class="demo-main" :class="{ 'has-nav': !isMobilePortrait }">

      <!-- CANVAS WRAPPER -->
      <div id="three-canvas-wrapper" class="canvas-wrapper" :class="{ 'portrait-canvas': isMobilePortrait }">
        <canvas ref="canvasRef" class="three-canvas"></canvas>

        <!-- FALLBACK 2D -->
        <div v-if="webglFailed" class="fallback-2d">
          <div class="fallback-scroll">
            <svg :viewBox="`0 0 ${fallbackW} ${fallbackH}`" class="fallback-svg"
                 @mousemove="onFallbackHover" @click="onFallbackClick" @mouseleave="hoveredSpace = null">
              <rect x="0" y="0" :width="fallbackW" height="58" fill="#111"/>
              <text x="20" y="37" fill="white" font-size="14" font-weight="bold" font-family="Figtree,sans-serif">PISTA</text>
              <rect x="0" y="63" :width="fallbackW*0.18" height="44" fill="#7f1d1d" rx="3"/>
              <text :x="fallbackW*0.09" y="89" fill="white" font-size="9" text-anchor="middle" font-family="Figtree,sans-serif">Gradas</text>
              <rect :x="fallbackW*0.19" y="63" :width="fallbackW*0.13" height="44" fill="#d97706" rx="3"/>
              <text :x="fallbackW*0.255" y="89" fill="white" font-size="9" text-anchor="middle" font-family="Figtree,sans-serif">VIP</text>
              <rect :x="fallbackW*0.33" y="63" :width="fallbackW*0.2" height="44" fill="#1e3a5f" rx="3"/>
              <text :x="fallbackW*0.43" y="89" fill="white" font-size="9" text-anchor="middle" font-family="Figtree,sans-serif">Principal</text>
              <rect :x="fallbackW*0.54" y="63" :width="fallbackW*0.13" height="44" fill="#d97706" rx="3"/>
              <text :x="fallbackW*0.605" y="89" fill="white" font-size="9" text-anchor="middle" font-family="Figtree,sans-serif">VIP</text>
              <rect :x="fallbackW*0.68" y="63" :width="fallbackW*0.32" height="44" fill="#7f1d1d" rx="3"/>
              <text :x="fallbackW*0.84" y="89" fill="white" font-size="9" text-anchor="middle" font-family="Figtree,sans-serif">Gradas</text>
              <rect x="0" y="112" :width="fallbackW" height="22" fill="#1a1a1a"/>
              <text :x="fallbackW/2" y="127" fill="#9ca3af" font-size="9" text-anchor="middle" font-family="Figtree,sans-serif">PASO DE GENTE</text>
              <template v-for="sp in fallbackRects" :key="sp.id">
                <rect :x="sp.x" :y="sp.y" :width="sp.w" :height="sp.h"
                  :fill="VENDIDOS.includes(sp.id) ? '#374151' : (hoveredSpace===sp.id ? lightenHex(typeColor(sp.type),0.3) : typeColor(sp.type))"
                  rx="2" :stroke="selectedSpace?.id===sp.id ? '#f59e0b' : '#0a0a0a'" stroke-width="2"
                  :opacity="VENDIDOS.includes(sp.id) ? 0.5 : 1" style="cursor:pointer"/>
                <text :x="sp.x+sp.w/2" :y="sp.y+sp.h/2+4" fill="white" :font-size="sp.w<30?7:9" text-anchor="middle" font-weight="bold" font-family="Figtree,sans-serif">{{ sp.id }}</text>
              </template>
            </svg>
          </div>
        </div>

        <!-- TOOLTIP -->
        <div v-if="tooltip.visible && !panelOpen" class="tooltip"
             :style="{ left: tooltip.x+'px', top: tooltip.y+'px', borderColor: typeColor(tooltip.type) }">
          <strong>{{ tooltip.id }}</strong> — {{ tooltip.category }}
          <br>${{ formatPrice(tooltip.precio) }} MXN
          <span v-if="VENDIDOS.includes(tooltip.id)" class="sold-tag">NO DISPONIBLE</span>
          <span v-else class="avail-tag">Toca para info</span>
        </div>

        <!-- LEYENDA DESKTOP -->
        <div class="legend" v-if="!isMobilePortrait">
          <div class="legend-item"><span class="ldot" style="background:#7c3aed"></span>Esp. L (9×4 m)</div>
          <div class="legend-item"><span class="ldot" style="background:#dc2626"></span>Esp. M (6×4 m)</div>
          <div class="legend-item"><span class="ldot" style="background:#16a34a"></span>Comida C</div>
          <div class="legend-item"><span class="ldot" style="background:#0891b2"></span>Bebidas B</div>
          <div class="legend-item"><span class="ldot" style="background:#4b5563"></span>Vendido</div>
        </div>

        <!-- AVAILABILITY BAR DESKTOP -->
        <div class="avail-bar-wrap" v-if="!isMobilePortrait">
          <div class="avail-bar-lbl">{{ disponibles }} de {{ SPACES.length }} espacios disponibles</div>
          <div class="avail-track"><div class="avail-fill" :style="{ width: (disponibles/SPACES.length*100)+'%' }"></div></div>
        </div>

        <!-- ZOOM BUTTONS -->
        <div class="cam-btns" :class="{ 'cam-btns-portrait': isMobilePortrait }">
          <button class="cam-btn" @click="zoomIn">+</button>
          <button class="cam-btn" @click="zoomOut">−</button>
          <button class="cam-btn cam-reset-btn" @click="resetCamera">↺</button>
        </div>

        <!-- LEGEND TOGGLE MOBILE -->
        <button v-if="isMobilePortrait" class="legend-toggle-btn" @click="showLegendModal = true">ℹ️</button>

        <!-- THEME TOGGLE MOBILE (dentro del canvas) -->
        <button v-if="isMobilePortrait" class="theme-toggle-mobile" @click="toggleTheme">
          {{ isDark ? '☀️' : '🌙' }}
        </button>

        <!-- PINCH HINT -->
        <transition name="fade-load">
          <div v-if="isMobilePortrait && showPinchHint" class="pinch-hint">Pellizca para zoom</div>
        </transition>
      </div>

      <!-- MOBILE EVENT HEADER -->
      <div v-if="isMobilePortrait" class="mobile-event-header">
        <div class="meh-brand">
          <div class="meh-logo">Y·200</div>
          <div class="meh-title">Zona Comercial</div>
        </div>
        <div class="meh-avail">
          <span class="meh-num">{{ disponibles }}</span>/<span>{{ SPACES.length }}</span>
          <span class="meh-lbl">disponibles</span>
        </div>
        <button class="meh-theme-btn" @click="toggleTheme">{{ isDark ? '☀️' : '🌙' }}</button>
      </div>

      <!-- BOTTOM INFO PANEL (MOBILE PORTRAIT) -->
      <div v-if="isMobilePortrait" class="bottom-panel"
           :style="selectedSpace && panelOpen ? { borderTopColor: typeColor(selectedSpace.type) } : {}">
        <template v-if="selectedSpace && panelOpen">
          <div class="bp-header">
            <div>
              <div class="bp-id">ESPACIO {{ selectedSpace.label }}</div>
              <div class="bp-cat" :style="{ color: typeColor(selectedSpace.type) }">{{ selectedSpace.category }}</div>
              <div class="bp-size">📐 {{ selectedSpace.size }} · 📍 {{ bloque(selectedSpace.id) }}</div>
            </div>
            <div class="bp-price-wrap">
              <div class="bp-price">${{ formatPrice(selectedSpace.precio) }}</div>
              <div class="bp-price-mxn">MXN</div>
            </div>
          </div>
          <div class="bp-actions">
            <a :href="`/demo-prueba/paid?espacio=${selectedSpace.id}&precio=${selectedSpace.precio}&tipo=${encodeURIComponent(selectedSpace.category)}`"
               class="btn-reservar-bp">RESERVAR ESPACIO</a>
            <button class="bp-close-btn" @click="closePanel">✕ Cerrar</button>
          </div>
        </template>
        <template v-else>
          <div class="bp-empty">
            <span class="bp-empty-icon">👆</span>
            <span>Toca un espacio en el mapa para ver información y precio</span>
          </div>
          <div class="bp-legend-row">
            <span v-for="t in legendItems" :key="t.l" class="bp-leg-item">
              <span class="bp-ldot" :style="{ background: t.c }"></span>{{ t.l }}
            </span>
          </div>
        </template>
      </div>
    </div><!-- /demo-main -->

    <!-- SIDE PANEL (DESKTOP) -->
    <transition name="slide-panel">
      <div v-if="panelOpen && selectedSpace && !isMobilePortrait" class="side-panel"
           :style="{ borderLeftColor: typeColor(selectedSpace.type) }">
        <button class="panel-close" @click="closePanel">✕</button>
        <div class="panel-badge" :style="{ background: typeColor(selectedSpace.type) }">
          {{ selectedSpace.type==='C' ? 'COMIDA' : selectedSpace.type==='B' ? 'BEBIDAS' : 'COMERCIAL' }}
        </div>
        <div class="panel-status">DISPONIBLE</div>
        <div class="panel-divider"></div>
        <div class="panel-name">ESPACIO {{ selectedSpace.label }}</div>
        <div class="panel-sub">Zona Comercial Yucatán 200</div>
        <div class="panel-details">
          <div class="panel-drow"><span>📐 Dimensiones</span><span>{{ selectedSpace.size }}</span></div>
          <div class="panel-drow"><span>🏷 Categoría</span><span>{{ selectedSpace.category }}</span></div>
          <div class="panel-drow"><span>📍 Ubicación</span><span>{{ bloque(selectedSpace.id) }}</span></div>
          <div class="panel-drow"><span>👁 Vista</span><span>Frente a Gradas</span></div>
        </div>
        <div class="panel-section-title">── INCLUYE ──</div>
        <ul class="panel-includes">
          <li>✓ Espacio delimitado con señalética</li>
          <li>✓ 1 mesa y 2 sillas</li>
          <li>✓ Conexión eléctrica 110v</li>
          <li>✓ Acceso peatonal desde ambos lados</li>
          <li>✓ Visibilidad directa a la pista</li>
        </ul>
        <div class="panel-section-title">── PRECIO ──</div>
        <div class="panel-price">${{ formatPrice(selectedSpace.precio) }} <span>MXN</span></div>
        <div class="panel-price-note">Precio por día de evento</div>
        <a :href="`/demo-prueba/paid?espacio=${selectedSpace.id}&precio=${selectedSpace.precio}&tipo=${encodeURIComponent(selectedSpace.category)}`"
           class="btn-reservar">RESERVAR ESPACIO</a>
        <div class="panel-redirect-note">Serás dirigido al formulario de pago</div>
      </div>
    </transition>

    <!-- LEGEND MODAL (mobile) -->
    <transition name="fade-load">
      <div v-if="showLegendModal" class="legend-modal-overlay" @click.self="showLegendModal = false">
        <div class="legend-modal">
          <div class="lm-title">Leyenda de Espacios</div>
          <div v-for="t in legendItemsFull" :key="t.l" class="lm-item">
            <span class="lm-dot" :style="{ background: t.c }"></span>{{ t.l }}
          </div>
          <button class="lm-close" @click="showLegendModal = false">Cerrar</button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

// ─── DATOS ───────────────────────────────────────────────────────────────────
const SPACES = [
  { id:'L1',  type:'L', label:'L1',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L2',  type:'L', label:'L2',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L3',  type:'L', label:'L3',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L4',  type:'L', label:'L4',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'M1',  type:'M', label:'M1',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M2',  type:'M', label:'M2',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M3',  type:'M', label:'M3',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'C1',  type:'C', label:'C1',  size:'9×4 m', category:'Zona de Comida',    precio:9500 },
  { id:'B1',  type:'B', label:'B1',  size:'9×4 m', category:'Zona de Bebidas',   precio:9500 },
  { id:'M4',  type:'M', label:'M4',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M5',  type:'M', label:'M5',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M6',  type:'M', label:'M6',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'L5',  type:'L', label:'L5',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L6',  type:'L', label:'L6',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L7',  type:'L', label:'L7',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'C2',  type:'C', label:'C2',  size:'9×4 m', category:'Zona de Comida',    precio:9500 },
  { id:'B2',  type:'B', label:'B2',  size:'9×4 m', category:'Zona de Bebidas',   precio:9500 },
  { id:'L8',  type:'L', label:'L8',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L9',  type:'L', label:'L9',  size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L10', type:'L', label:'L10', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L11', type:'L', label:'L11', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'M7',  type:'M', label:'M7',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M8',  type:'M', label:'M8',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M9',  type:'M', label:'M9',  size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'C3',  type:'C', label:'C3',  size:'9×4 m', category:'Zona de Comida',    precio:9500 },
  { id:'B3',  type:'B', label:'B3',  size:'9×4 m', category:'Zona de Bebidas',   precio:9500 },
  { id:'M10', type:'M', label:'M10', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M11', type:'M', label:'M11', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M12', type:'M', label:'M12', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'L12', type:'L', label:'L12', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L13', type:'L', label:'L13', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L14', type:'L', label:'L14', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'C5',  type:'C', label:'C5',  size:'9×4 m', category:'Zona de Comida',    precio:9500 },
  { id:'B5',  type:'B', label:'B5',  size:'9×4 m', category:'Zona de Bebidas',   precio:9500 },
  { id:'L15', type:'L', label:'L15', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L16', type:'L', label:'L16', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L17', type:'L', label:'L17', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L18', type:'L', label:'L18', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'M13', type:'M', label:'M13', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M14', type:'M', label:'M14', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M15', type:'M', label:'M15', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'C6',  type:'C', label:'C6',  size:'9×4 m', category:'Zona de Comida',    precio:9500 },
  { id:'B6',  type:'B', label:'B6',  size:'9×4 m', category:'Zona de Bebidas',   precio:9500 },
  { id:'M16', type:'M', label:'M16', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M17', type:'M', label:'M17', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M18', type:'M', label:'M18', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'L19', type:'L', label:'L19', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L20', type:'L', label:'L20', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L21', type:'L', label:'L21', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'C7',  type:'C', label:'C7',  size:'9×4 m', category:'Zona de Comida',    precio:9500 },
  { id:'B7',  type:'B', label:'B7',  size:'9×4 m', category:'Zona de Bebidas',   precio:9500 },
  { id:'L22', type:'L', label:'L22', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L23', type:'L', label:'L23', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L24', type:'L', label:'L24', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L25', type:'L', label:'L25', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'M19', type:'M', label:'M19', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M20', type:'M', label:'M20', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M21', type:'M', label:'M21', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'C8',  type:'C', label:'C8',  size:'9×4 m', category:'Zona de Comida',    precio:9500 },
  { id:'B8',  type:'B', label:'B8',  size:'9×4 m', category:'Zona de Bebidas',   precio:9500 },
  { id:'M22', type:'M', label:'M22', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M23', type:'M', label:'M23', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'M24', type:'M', label:'M24', size:'6×4 m', category:'Espacio Comercial', precio:5500 },
  { id:'L26', type:'L', label:'L26', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L27', type:'L', label:'L27', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
  { id:'L28', type:'L', label:'L28', size:'9×4 m', category:'Espacio Comercial', precio:8500 },
]

const VENDIDOS = ['L1','M3','C1','B2','L8','M9','L15','C6','M16','L22','B7','M22']

const BLOCK_RANGES = [
  { name:'Bloque 1', ids:['L1','L2','L3','L4','M1','M2','M3','C1','B1'] },
  { name:'Bloque 2', ids:['M4','M5','M6','L5','L6','L7','C2','B2'] },
  { name:'Bloque 3', ids:['L8','L9','L10','L11','M7','M8','M9','C3','B3'] },
  { name:'Bloque 4', ids:['M10','M11','M12','L12','L13','L14','C5','B5'] },
  { name:'Bloque 5', ids:['L15','L16','L17','L18','M13','M14','M15','C6','B6'] },
  { name:'Bloque 6', ids:['M16','M17','M18','L19','L20','L21','C7','B7'] },
  { name:'Bloque 7', ids:['L22','L23','L24','L25','M19','M20','M21','C8','B8'] },
  { name:'Bloque 8', ids:['M22','M23','M24','L26','L27','L28'] },
]

// ─── VUE STATE ───────────────────────────────────────────────────────────────
const canvasRef       = ref(null)
const loading         = ref(true)
const progress        = ref(0)
const webglFailed     = ref(false)
const selectedSpace   = ref(null)
const hoveredSpace    = ref(null)
const panelOpen       = ref(false)
const showLegendModal = ref(false)
const showPinchHint   = ref(false)
const isDark          = ref(localStorage.getItem('demoTheme') !== 'light')
const windowW         = ref(window.innerWidth)
const windowH         = ref(window.innerHeight)
const tooltip         = ref({ visible:false, x:0, y:0, id:'', category:'', precio:0, type:'' })

const isMobilePortrait = computed(() => windowW.value <= 768 && windowH.value > windowW.value)
const disponibles      = computed(() => SPACES.filter(s => !VENDIDOS.includes(s.id)).length)

const legendItems = [
  { c:'#7c3aed', l:'L' }, { c:'#dc2626', l:'M' }, { c:'#16a34a', l:'C' },
  { c:'#0891b2', l:'B' }, { c:'#4b5563', l:'Vendido' }
]
const legendItemsFull = [
  { c:'#7c3aed', l:'Espacio L — 9×4 m — $8,500 MXN' },
  { c:'#dc2626', l:'Espacio M — 6×4 m — $5,500 MXN' },
  { c:'#16a34a', l:'Comida C — 9×4 m — $9,500 MXN'  },
  { c:'#0891b2', l:'Bebidas B — 9×4 m — $9,500 MXN' },
  { c:'#4b5563', l:'Vendido — No disponible'          },
]

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function typeColor(type) { return { L:'#7c3aed', M:'#dc2626', C:'#16a34a', B:'#0891b2' }[type] ?? '#4b5563' }
function formatPrice(p)  { return Number(p).toLocaleString('es-MX') }
function bloque(id)      { return BLOCK_RANGES.find(b => b.ids.includes(id))?.name ?? 'Zona Central' }
function lightenHex(hex, amt) {
  const c = parseInt(hex.slice(1), 16)
  const r = Math.min(255, (c>>16) + Math.round(amt*255))
  const g = Math.min(255, ((c>>8)&0xff) + Math.round(amt*255))
  const b = Math.min(255, (c&0xff) + Math.round(amt*255))
  return `rgb(${r},${g},${b})`
}
function closePanel() { panelOpen.value = false; selectedSpace.value = null }
function loadScript(src) {
  return new Promise((res, rej) => {
    const s = document.createElement('script')
    s.src = src; s.onload = res; s.onerror = rej
    document.head.appendChild(s)
  })
}

// ─── FALLBACK 2D ─────────────────────────────────────────────────────────────
const fallbackW = 1200, fallbackH = 420
const fallbackRects = computed(() => {
  const rects = []; let x = 5
  SPACES.forEach(sp => {
    const w = (sp.type==='L'||sp.type==='C'||sp.type==='B') ? 27 : 18
    rects.push({ ...sp, x, y:140, w, h:52 })
    x += w + 3
  })
  return rects
})
function onFallbackHover(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const mx = (e.clientX-rect.left) * (fallbackW/rect.width)
  const my = (e.clientY-rect.top)  * (fallbackH/rect.height)
  const hit = fallbackRects.value.find(r => mx>=r.x && mx<=r.x+r.w && my>=r.y && my<=r.y+r.h)
  hoveredSpace.value = hit ? hit.id : null
  if (hit) tooltip.value = { visible:true, x:e.clientX+12, y:e.clientY-40, id:hit.id, category:hit.category, precio:hit.precio, type:hit.type }
  else tooltip.value.visible = false
}
function onFallbackClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const mx = (e.clientX-rect.left) * (fallbackW/rect.width)
  const my = (e.clientY-rect.top)  * (fallbackH/rect.height)
  const hit = fallbackRects.value.find(r => mx>=r.x && mx<=r.x+r.w && my>=r.y && my<=r.y+r.h)
  if (hit && !VENDIDOS.includes(hit.id)) { selectedSpace.value = hit; panelOpen.value = true }
}

// ─── THREE.JS STATE ──────────────────────────────────────────────────────────
let THREE, GSAP
let scene, camera, renderer, rafId, threeClk
let meshGroupMap = new Map()
let ambientLight, dirLight, accentLights = []
let groundMesh, groundMat
let peatones = []
let gradaFigures = []
let camOrbit = { theta: 0, phi: 1.05, radius: 38 }
let camTarget = { theta: 0, phi: 1.05, radius: 38 }
let portraitCamZ = ref(16)
let isDragging = false, isRightDrag = false
let lastMouse = { x:0, y:0 }
let lastPinchDist = 0
let panOffset = { x:0, z:0 }
let selectedWireframe = null
let raycasterObj = null
let needsRaycast = false, lastMouseEvent = null
let rayThrottle = 0
let isLowEnd = false

// ─── INIT SCENE ──────────────────────────────────────────────────────────────
function initScene() {
  const canvas = canvasRef.value
  if (!canvas) return
  isLowEnd = (navigator.hardwareConcurrency || 4) <= 2

  try {
    renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: false })
  } catch(e) {
    webglFailed.value = true; return
  }

  renderer.setPixelRatio(isLowEnd ? 1 : Math.min(window.devicePixelRatio, 2))
  renderer.setSize(canvas.clientWidth, canvas.clientHeight)
  renderer.shadowMap.enabled = !isLowEnd
  renderer.shadowMap.type = THREE.PCFSoftShadowMap
  renderer.toneMapping = THREE.ACESFilmicToneMapping
  renderer.toneMappingExposure = 1.2

  scene = new THREE.Scene()
  threeClk = new THREE.Clock()

  const isMob = isMobilePortrait.value
  camera = new THREE.PerspectiveCamera(isMob ? 55 : 45, canvas.clientWidth / canvas.clientHeight, 0.1, 300)
  setDefaultCamPos()

  buildLights()
  buildScene()
  buildPeatones()
  setupCameraControls()
  setupInteraction()
  applyTheme(isDark.value, true)

  window.addEventListener('resize', onResize)
  window.addEventListener('orientationchange', () => setTimeout(onResize, 300))

  animate()
}

function setDefaultCamPos() {
  if (isMobilePortrait.value) {
    camera.position.set(0, 28, portraitCamZ.value)
    camera.lookAt(0, 0, 0)
    camera.fov = 55
    camera.updateProjectionMatrix()
  } else {
    const { theta, phi, radius } = camOrbit
    camera.position.set(
      Math.sin(theta) * radius * Math.sin(phi),
      radius * Math.cos(phi),
      Math.cos(theta) * radius * Math.sin(phi)
    )
    camera.lookAt(0, 0, 0)
    camera.fov = 45
    camera.updateProjectionMatrix()
  }
}

// ─── LIGHTS ──────────────────────────────────────────────────────────────────
function buildLights() {
  ambientLight = new THREE.AmbientLight(0xffffff, 0.25)
  scene.add(ambientLight)

  dirLight = new THREE.DirectionalLight(0xfff5e0, 1.4)
  dirLight.position.set(12, 20, 10)
  dirLight.castShadow = !isLowEnd
  if (!isLowEnd) {
    dirLight.shadow.mapSize.set(isLowEnd ? 512 : 2048, isLowEnd ? 512 : 2048)
    dirLight.shadow.camera.near = 0.5
    dirLight.shadow.camera.far  = 100
    dirLight.shadow.camera.left = -60
    dirLight.shadow.camera.right = 60
    dirLight.shadow.camera.top  = 30
    dirLight.shadow.camera.bottom = -30
    dirLight.shadow.bias = -0.001
  }
  scene.add(dirLight)

  const acc1 = new THREE.PointLight(0xff3333, 0.6, 60)
  acc1.position.set(20, 4, -10)
  scene.add(acc1)
  accentLights.push(acc1)

  const acc2 = new THREE.PointLight(0x334466, 0.4, 60)
  acc2.position.set(-20, 8, 5)
  scene.add(acc2)
  accentLights.push(acc2)
}

// ─── APPLY THEME ─────────────────────────────────────────────────────────────
function applyTheme(dark, immediate = false) {
  if (!renderer) return
  const bg    = dark ? 0x0a0a0a : 0xf4f4f4
  const fogC  = dark ? 0x0a0a0a : 0xe8e8e8
  const fogD  = dark ? 0.012    : 0.007
  renderer.setClearColor(bg, 1)
  scene.fog = new THREE.FogExp2(fogC, fogD)

  if (groundMat) {
    const gc = dark ? 0x111111 : 0xd4d4d4
    groundMat.color.set(gc)
  }

  if (immediate || !GSAP) {
    if (ambientLight) ambientLight.intensity = dark ? 0.25 : 0.7
    if (dirLight)     dirLight.intensity     = dark ? 1.4  : 1.2
    accentLights.forEach(l => { l.intensity = dark ? 0.5 : 0 })
  } else {
    GSAP.to(ambientLight, { intensity: dark ? 0.25 : 0.7, duration: 0.6, ease:'power2.inOut' })
    GSAP.to(dirLight,     { intensity: dark ? 1.4  : 1.2, duration: 0.6, ease:'power2.inOut' })
    accentLights.forEach(l => GSAP.to(l, { intensity: dark ? 0.5 : 0, duration: 0.6, ease:'power2.inOut' }))
  }
}

function toggleTheme() {
  isDark.value = !isDark.value
  localStorage.setItem('demoTheme', isDark.value ? 'dark' : 'light')
  applyTheme(isDark.value)
}

// ─── BUILD SCENE ─────────────────────────────────────────────────────────────
function buildScene() {
  // Ground
  const groundGeo = new THREE.PlaneGeometry(160, 60)
  groundMat = new THREE.MeshStandardMaterial({ color: 0x111111, roughness: 0.9 })
  groundMesh = new THREE.Mesh(groundGeo, groundMat)
  groundMesh.rotation.x = -Math.PI / 2
  groundMesh.receiveShadow = true
  scene.add(groundMesh)

  const grid = new THREE.GridHelper(160, 40, 0x282828, 0x1e1e1e)
  grid.position.y = 0.01
  scene.add(grid)

  buildTrack()
  buildGrandstands()
  buildPedestrianZone()
  buildCommercialStands()
}

// ─── TRACK ───────────────────────────────────────────────────────────────────
function buildTrack() {
  const trackGeo = new THREE.PlaneGeometry(160, 10)
  const trackMat = new THREE.MeshStandardMaterial({ color: 0x111111, roughness: 1 })
  const track = new THREE.Mesh(trackGeo, trackMat)
  track.rotation.x = -Math.PI / 2
  track.position.set(0, 0.02, -20)
  track.receiveShadow = true
  scene.add(track)

  // Lane lines
  for (let i = -7; i <= 7; i++) {
    const lineGeo = new THREE.PlaneGeometry(0.25, 8)
    const lineMat = new THREE.MeshBasicMaterial({ color: 0xffffff, opacity: 0.25, transparent: true })
    const line = new THREE.Mesh(lineGeo, lineMat)
    line.rotation.x = -Math.PI / 2
    line.position.set(i * 10, 0.03, -20)
    scene.add(line)
  }

  // Meta line
  const metaGeo = new THREE.PlaneGeometry(0.5, 10)
  const metaMat = new THREE.MeshStandardMaterial({ color: 0xf59e0b })
  const meta = new THREE.Mesh(metaGeo, metaMat)
  meta.rotation.x = -Math.PI / 2
  meta.position.set(0, 0.04, -20)
  scene.add(meta)

  // Checkered flag
  const flagCanvas = document.createElement('canvas')
  flagCanvas.width = 128; flagCanvas.height = 128
  const fctx = flagCanvas.getContext('2d')
  const sq = 16
  for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
    fctx.fillStyle = (r+c)%2===0 ? '#ffffff' : '#000000'
    fctx.fillRect(c*sq, r*sq, sq, sq)
  }
  const flagTex = new THREE.CanvasTexture(flagCanvas)
  const flagGeo = new THREE.PlaneGeometry(3, 2)
  const flagMesh = new THREE.Mesh(flagGeo, new THREE.MeshBasicMaterial({ map: flagTex, side: THREE.DoubleSide }))
  flagMesh.rotation.x = -Math.PI / 2
  flagMesh.position.set(72, 0.05, -20)
  scene.add(flagMesh)

  // Barriers
  for (let i = -75; i <= 75; i += 2.5) {
    const bGeo = new THREE.BoxGeometry(2, 0.6, 0.4)
    const bCol = Math.abs(i % 5) < 2.5 ? 0xee1111 : 0xeeeeee
    const bMat = new THREE.MeshStandardMaterial({ color: bCol })
    const b = new THREE.Mesh(bGeo, bMat)
    b.position.set(i, 0.3, -15.5)
    b.castShadow = true
    scene.add(b)
  }

  addSprite('PISTA', 0, 1.5, -20, '#ffffff', 8, 1.5)
}

// ─── GRANDSTANDS ─────────────────────────────────────────────────────────────
function buildGrandstands() {
  const sections = [
    { label:'GRADAS\nGENERALES', color:0x7f1d1d, x:-45, w:18 },
    { label:'VIP',               color:0x92400e, x:-23, w:12 },
    { label:'GRADA\nPRINCIPAL',  color:0x1e3a5f, x: -5, w:20 },
    { label:'VIP',               color:0x92400e, x: 18, w:12 },
    { label:'GRADAS\nGENERALES', color:0x7f1d1d, x: 37, w:18 },
  ]

  sections.forEach(sec => {
    for (let step = 0; step < 4; step++) {
      const stGeo = new THREE.BoxGeometry(sec.w, 0.35, 0.7)
      const stMat = new THREE.MeshStandardMaterial({ color: sec.color, roughness: 0.8 })
      const st = new THREE.Mesh(stGeo, stMat)
      st.position.set(sec.x, 0.175 + step * 0.35, -9.5 - step * 0.6)
      st.receiveShadow = true
      scene.add(st)

      // Crowd figures on each step
      const density = Math.floor(sec.w / 0.4)
      const crowdColors = [0xea580c, 0xdc2626, 0xffffff, 0xeab308, 0x3b82f6]
      for (let c = 0; c < density; c++) {
        const figGeo = new THREE.CylinderGeometry(0.05, 0.05, 0.22, 6)
        const figCol = crowdColors[Math.floor(Math.random() * crowdColors.length)]
        const figMat = new THREE.MeshStandardMaterial({ color: figCol })
        const fig = new THREE.Mesh(figGeo, figMat)
        const px = sec.x - sec.w/2 + 0.3 + c * ((sec.w - 0.3) / density)
        const py = 0.35 + step * 0.35 + 0.11
        const pz = -9.5 - step * 0.6
        fig.position.set(px + (Math.random()-0.5)*0.15, py, pz + (Math.random()-0.5)*0.2)
        fig.castShadow = false
        const phase = Math.random() * Math.PI * 2
        gradaFigures.push({ mesh: fig, baseY: py, phase })
        scene.add(fig)
      }
    }
    addSprite(sec.label, sec.x, 2.4, -9.5, '#ffffff', 3, 0.9)
  })
}

// ─── PEDESTRIAN ZONE ─────────────────────────────────────────────────────────
function buildPedestrianZone() {
  const pathGeo = new THREE.PlaneGeometry(160, 3.5)
  const pathMat = new THREE.MeshStandardMaterial({ color: 0x2a2a2a, roughness: 0.95 })
  const path = new THREE.Mesh(pathGeo, pathMat)
  path.rotation.x = -Math.PI / 2
  path.position.set(0, 0.02, -4)
  path.receiveShadow = true
  scene.add(path)

  addSprite('PASO DE GENTE', 0, 0.5, -4, '#9ca3af', 6, 0.6)

  // Flow arrows
  for (let i = -5; i <= 5; i++) {
    const arrowGeo = new THREE.ConeGeometry(0.18, 0.5, 3)
    const arrowMat = new THREE.MeshBasicMaterial({ color: 0xf59e0b, transparent: true, opacity: 0.7 })
    const arrow = new THREE.Mesh(arrowGeo, arrowMat)
    arrow.rotation.z = -Math.PI / 2
    arrow.position.set(i * 10, 0.5, -4)
    arrow.userData.isArrow = true
    arrow.userData.arrowPhase = i * 0.5
    scene.add(arrow)
  }
}

// ─── COMMERCIAL STANDS ───────────────────────────────────────────────────────
function buildCommercialStands() {
  const gap = 0.12
  let totalW = 0
  SPACES.forEach(sp => { totalW += standW(sp.type) + gap })
  totalW -= gap
  let curX = -totalW / 2

  SPACES.forEach(sp => {
    const w  = standW(sp.type)
    const hw = w / 2
    const d  = 0.85
    const hd = d / 2
    const hWall = sp.type === 'M' ? 0.75 : 0.88
    const sold = VENDIDOS.includes(sp.id)
    const col = sold ? 0x4b5563 : parseInt(typeColor(sp.type).slice(1), 16)

    const group = new THREE.Group()
    group.userData = { sp, sold }
    group.position.set(curX + hw, 0, 1.5)

    // Base floor
    const baseMesh = new THREE.Mesh(
      new THREE.BoxGeometry(w, 0.04, d),
      new THREE.MeshStandardMaterial({ color: Math.max(0, col - 0x101010), roughness: 0.9 })
    )
    baseMesh.position.y = 0.02
    baseMesh.receiveShadow = true
    group.add(baseMesh)

    // Back wall
    const wallMat = new THREE.MeshStandardMaterial({ color: col, roughness: 0.6, metalness: 0.08, opacity: sold ? 0.55 : 1, transparent: sold })
    const backWall = new THREE.Mesh(new THREE.BoxGeometry(w, hWall, 0.06), wallMat)
    backWall.position.set(0, hWall/2, -hd)
    backWall.castShadow = true
    backWall.receiveShadow = true
    group.add(backWall)

    // Left wall
    const lWall = new THREE.Mesh(new THREE.BoxGeometry(0.06, hWall, d), wallMat.clone())
    lWall.position.set(-hw, hWall/2, 0)
    lWall.castShadow = true
    group.add(lWall)

    // Right wall
    const rWall = new THREE.Mesh(new THREE.BoxGeometry(0.06, hWall, d), wallMat.clone())
    rWall.position.set(hw, hWall/2, 0)
    rWall.castShadow = true
    group.add(rWall)

    // Tent roof (4-sided pyramid cone)
    const tentCol = sold ? 0x5a6470 : Math.min(0xffffff, col + 0x202020)
    const tentR = Math.max(hw, hd) * 1.05
    const tentGeo = new THREE.ConeGeometry(tentR, 0.42, 4)
    tentGeo.rotateY(Math.PI / 4)
    const tentMat = new THREE.MeshStandardMaterial({ color: tentCol, roughness: 0.85, opacity: sold ? 0.5 : 1, transparent: sold })
    const tent = new THREE.Mesh(tentGeo, tentMat)
    tent.position.y = hWall + 0.21
    tent.castShadow = true
    group.userData.tent = tent
    group.add(tent)

    // Front sign with CanvasTexture label
    const signTex = makeStandLabel(sp.id, typeColor(sp.type))
    const signGeo = new THREE.PlaneGeometry(w - 0.08, 0.26)
    const signMat = new THREE.MeshBasicMaterial({ map: signTex, side: THREE.FrontSide })
    const sign = new THREE.Mesh(signGeo, signMat)
    sign.position.set(0, hWall * 0.75, hd + 0.01)
    group.add(sign)

    // Hover point light (intensity starts at 0)
    if (!sold) {
      const hoverPL = new THREE.PointLight(col, 0, 3.5)
      hoverPL.position.set(0, hWall * 0.5, 0)
      group.userData.hoverLight = hoverPL
      group.add(hoverPL)
    }

    group.userData.baseY = 0
    group.userData.currentY = -1.5
    group.userData.buildDelay = SPACES.indexOf(sp) * 25
    group.userData.buildStarted = null

    scene.add(group)
    meshGroupMap.set(sp.id, group)
    curX += w + gap
  })
}

function standW(type) { return type === 'M' ? 1.1 : 1.7 }

function makeStandLabel(id, color) {
  const c = document.createElement('canvas')
  c.width = 128; c.height = 48
  const ctx = c.getContext('2d')
  ctx.fillStyle = '#111111'
  ctx.fillRect(0, 0, 128, 48)
  ctx.fillStyle = '#ffffff'
  ctx.font = 'bold 26px Arial'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillText(id, 64, 24)
  const t = new THREE.CanvasTexture(c)
  return t
}

function addSprite(text, x, y, z, colorHex, w, h) {
  const c = document.createElement('canvas')
  c.width = 256; c.height = 128
  const ctx = c.getContext('2d')
  ctx.fillStyle = 'rgba(0,0,0,0)'
  ctx.clearRect(0, 0, 256, 128)
  ctx.fillStyle = colorHex
  ctx.font = 'bold 26px Arial'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  const lines = text.split('\n')
  const lh = 32
  lines.forEach((ln, i) => ctx.fillText(ln, 128, 64 - (lines.length-1)*lh/2 + i*lh))
  const t = new THREE.CanvasTexture(c)
  const m = new THREE.Mesh(
    new THREE.PlaneGeometry(w, h),
    new THREE.MeshBasicMaterial({ map: t, transparent: true, depthWrite: false, side: THREE.DoubleSide })
  )
  m.position.set(x, y, z)
  scene.add(m)
}

// ─── WALKING PEOPLE ──────────────────────────────────────────────────────────
const peatonPalette = ['#e74c3c','#3498db','#f39c12','#2ecc71','#9b59b6','#1abc9c','#e67e22','#ecf0f1','#f8b500','#c0392b']
function rCol() { return new THREE.Color(peatonPalette[Math.floor(Math.random()*peatonPalette.length)]) }

function createPeaton() {
  const group = new THREE.Group()
  const skinMat = new THREE.MeshStandardMaterial({ color: 0xffd1a4 })
  const torsCol = rCol()
  const legCol  = rCol()

  // Head
  const head = new THREE.Mesh(new THREE.SphereGeometry(0.07, 8, 8), skinMat.clone())
  head.position.y = 0.41
  head.castShadow = true
  group.add(head)

  // Torso
  const torso = new THREE.Mesh(
    new THREE.CylinderGeometry(0.06, 0.07, 0.26, 8),
    new THREE.MeshStandardMaterial({ color: torsCol, roughness: 0.8 })
  )
  torso.position.y = 0.21
  torso.castShadow = true
  group.add(torso)

  // Legs
  const legGeo = new THREE.CylinderGeometry(0.03, 0.03, 0.21, 6)
  const legMat = new THREE.MeshStandardMaterial({ color: legCol, roughness: 0.8 })

  const legL = new THREE.Mesh(legGeo, legMat)
  legL.position.set(-0.045, 0.03, 0)
  legL.castShadow = false
  group.add(legL)

  const legR = new THREE.Mesh(legGeo, legMat.clone())
  legR.position.set(0.045, 0.03, 0)
  legR.castShadow = false
  group.add(legR)

  // Arms
  const armGeo = new THREE.CylinderGeometry(0.025, 0.025, 0.19, 6)
  const armMat = new THREE.MeshStandardMaterial({ color: torsCol, roughness: 0.8 })

  const armL = new THREE.Mesh(armGeo, armMat)
  armL.position.set(-0.1, 0.22, 0)
  armL.castShadow = false
  group.add(armL)

  const armR = new THREE.Mesh(armGeo, armMat.clone())
  armR.position.set(0.1, 0.22, 0)
  armR.castShadow = false
  group.add(armR)

  return { group, legL, legR, armL, armR }
}

function buildPeatones() {
  const N = isLowEnd ? 8 : 16
  for (let i = 0; i < N; i++) {
    const p = createPeaton()
    const startX = (Math.random() - 0.5) * 110
    const startZ = -4 + (Math.random() - 0.5) * 1.4
    p.group.position.set(startX, 0.01, startZ)
    p.baseY   = 0.01
    p.speed   = 0.015 + Math.random() * 0.025
    p.dir     = Math.random() > 0.5 ? 1 : -1
    p.phase   = Math.random() * Math.PI * 2
    p.group.rotation.y = p.dir > 0 ? 0 : Math.PI
    scene.add(p.group)
    peatones.push(p)
  }
}

// ─── CAMERA CONTROLS ─────────────────────────────────────────────────────────
function setupCameraControls() {
  const canvas = canvasRef.value
  canvas.addEventListener('mousedown',  onMouseDown)
  canvas.addEventListener('mousemove',  onMouseMoveRaw)
  canvas.addEventListener('mouseup',    () => { isDragging = false; isRightDrag = false })
  canvas.addEventListener('wheel',      onWheel, { passive: false })
  canvas.addEventListener('contextmenu', e => e.preventDefault())
  canvas.addEventListener('touchstart', onTouchStart, { passive: false })
  canvas.addEventListener('touchmove',  onTouchMove,  { passive: false })
  canvas.addEventListener('touchend',   onTouchEnd)
}

function onMouseDown(e) {
  isDragging = true; isRightDrag = e.button === 2
  lastMouse = { x: e.clientX, y: e.clientY }
}
function onMouseMoveRaw(e) {
  if (!isDragging) {
    lastMouseEvent = e; needsRaycast = true; return
  }
  if (isMobilePortrait.value) return
  const dx = e.clientX - lastMouse.x
  const dy = e.clientY - lastMouse.y
  if (isRightDrag) {
    panOffset.x -= dx * 0.04
    panOffset.z -= dy * 0.04
  } else {
    camTarget.theta -= dx * 0.005
    camTarget.phi = Math.max(0.25, Math.min(Math.PI/2.2, camTarget.phi - dy * 0.005))
  }
  lastMouse = { x: e.clientX, y: e.clientY }
}
function onWheel(e) {
  e.preventDefault()
  if (isMobilePortrait.value) {
    portraitCamZ.value = Math.max(9, Math.min(30, portraitCamZ.value + e.deltaY * 0.04))
  } else {
    camTarget.radius = Math.max(14, Math.min(80, camTarget.radius + e.deltaY * 0.05))
  }
}
function onTouchStart(e) {
  if (e.touches.length === 2) {
    lastPinchDist = Math.hypot(
      e.touches[0].clientX - e.touches[1].clientX,
      e.touches[0].clientY - e.touches[1].clientY
    )
    if (isMobilePortrait.value && !showPinchHint.value) {
      showPinchHint.value = true
      setTimeout(() => { showPinchHint.value = false }, 2500)
    }
  } else if (e.touches.length === 1 && !isMobilePortrait.value) {
    isDragging = true
    lastMouse = { x: e.touches[0].clientX, y: e.touches[0].clientY }
  }
}
function onTouchMove(e) {
  e.preventDefault()
  if (e.touches.length === 2) {
    const dist = Math.hypot(
      e.touches[0].clientX - e.touches[1].clientX,
      e.touches[0].clientY - e.touches[1].clientY
    )
    const delta = lastPinchDist - dist
    if (isMobilePortrait.value) {
      portraitCamZ.value = Math.max(9, Math.min(30, portraitCamZ.value + delta * 0.06))
    } else {
      camTarget.radius = Math.max(14, Math.min(80, camTarget.radius + delta * 0.05))
    }
    lastPinchDist = dist
  } else if (e.touches.length === 1 && isDragging && !isMobilePortrait.value) {
    const dx = e.touches[0].clientX - lastMouse.x
    const dy = e.touches[0].clientY - lastMouse.y
    camTarget.theta -= dx * 0.005
    camTarget.phi = Math.max(0.25, Math.min(Math.PI/2.2, camTarget.phi - dy * 0.005))
    lastMouse = { x: e.touches[0].clientX, y: e.touches[0].clientY }
  }
}
function onTouchEnd(e) {
  isDragging = false
  if (e.changedTouches.length === 1 && e.touches.length === 0) {
    const t = e.changedTouches[0]
    const canvas = canvasRef.value
    const rect = canvas.getBoundingClientRect()
    const ndc = {
      x:  ((t.clientX - rect.left) / rect.width)  * 2 - 1,
      y: -((t.clientY - rect.top)  / rect.height) * 2 + 1,
    }
    doRaycastClick(ndc)
  }
}

function zoomIn()  {
  if (isMobilePortrait.value) portraitCamZ.value = Math.max(9, portraitCamZ.value - 3)
  else camTarget.radius = Math.max(14, camTarget.radius - 4)
}
function zoomOut() {
  if (isMobilePortrait.value) portraitCamZ.value = Math.min(30, portraitCamZ.value + 3)
  else camTarget.radius = Math.min(80, camTarget.radius + 4)
}
function resetCamera() {
  camOrbit = { theta: 0, phi: 1.05, radius: 38 }
  camTarget = { ...camOrbit }
  panOffset = { x: 0, z: 0 }
  portraitCamZ.value = 16
}

// ─── RAYCASTING ──────────────────────────────────────────────────────────────
function setupInteraction() {
  canvasRef.value.addEventListener('click', onCanvasClick)
}

function findStandGroup(obj) {
  let cur = obj
  while (cur) { if (cur.userData && cur.userData.sp) return cur; cur = cur.parent }
  return null
}

function doRaycast(ndc, forClick = false) {
  if (!THREE || !scene || !camera) return null
  if (!raycasterObj) raycasterObj = new THREE.Raycaster()
  raycasterObj.setFromCamera(ndc, camera)
  const targets = [...meshGroupMap.values()].flatMap(g => g.children.filter(c => c.isMesh))
  const hits = raycasterObj.intersectObjects(targets)
  if (hits.length > 0) {
    const grp = findStandGroup(hits[0].object)
    if (grp) return grp
  }
  return null
}

function updateHover() {
  if (!lastMouseEvent) return
  const canvas = canvasRef.value
  const rect = canvas.getBoundingClientRect()
  const ndc = {
    x:  ((lastMouseEvent.clientX - rect.left) / rect.width)  * 2 - 1,
    y: -((lastMouseEvent.clientY - rect.top)  / rect.height) * 2 + 1,
  }
  const grp = doRaycast(ndc)
  if (grp) {
    const sp = grp.userData.sp
    hoveredSpace.value = sp.id
    canvasRef.value.style.cursor = VENDIDOS.includes(sp.id) ? 'not-allowed' : 'pointer'
    tooltip.value = { visible: true, x: lastMouseEvent.clientX+14, y: lastMouseEvent.clientY-50, id: sp.id, category: sp.category, precio: sp.precio, type: sp.type }
  } else {
    hoveredSpace.value = null
    canvasRef.value.style.cursor = 'default'
    tooltip.value.visible = false
  }
  needsRaycast = false
}

function onCanvasClick(e) {
  if (!THREE || !scene || !camera) return
  const canvas = canvasRef.value
  const rect = canvas.getBoundingClientRect()
  const ndc = {
    x:  ((e.clientX - rect.left) / rect.width)  * 2 - 1,
    y: -((e.clientY - rect.top)  / rect.height) * 2 + 1,
  }
  doRaycastClick(ndc)
}

function doRaycastClick(ndc) {
  const grp = doRaycast(ndc)
  if (grp) {
    const sp = grp.userData.sp
    if (!VENDIDOS.includes(sp.id)) {
      selectedSpace.value = sp
      panelOpen.value = true
    }
  } else {
    if (!isMobilePortrait.value) closePanel()
  }
}

// ─── WIREFRAME SELECTION ─────────────────────────────────────────────────────
function setWireframe(grp) {
  if (selectedWireframe) {
    if (selectedWireframe.parent) selectedWireframe.parent.remove(selectedWireframe)
    selectedWireframe = null
  }
  if (!grp) return
  const walls = grp.children.filter(c => c.isMesh && c.geometry.type === 'BoxGeometry')
  if (walls.length === 0) return
  const edges = new THREE.EdgesGeometry(walls[0].geometry)
  selectedWireframe = new THREE.LineSegments(edges, new THREE.LineBasicMaterial({ color: 0xf59e0b }))
  selectedWireframe.position.copy(walls[0].position)
  selectedWireframe.scale.set(1.02, 1.1, 1.02)
  grp.add(selectedWireframe)
}

// ─── ANIMATION LOOP ──────────────────────────────────────────────────────────
function animate() {
  rafId = requestAnimationFrame(animate)
  const delta   = threeClk.getDelta()
  const elapsed = threeClk.getElapsedTime()

  // Camera update
  if (isMobilePortrait.value) {
    camera.position.set(0, 28, portraitCamZ.value)
    camera.lookAt(0, 0, 0)
  } else {
    camOrbit.theta  += (camTarget.theta  - camOrbit.theta)  * 0.06
    camOrbit.phi    += (camTarget.phi    - camOrbit.phi)    * 0.06
    camOrbit.radius += (camTarget.radius - camOrbit.radius) * 0.06
    const { theta, phi, radius } = camOrbit
    camera.position.set(
      Math.sin(theta) * radius * Math.sin(phi) + panOffset.x,
      radius * Math.cos(phi),
      Math.cos(theta) * radius * Math.sin(phi) + panOffset.z
    )
    camera.lookAt(panOffset.x, 0, panOffset.z)
  }

  // Stand build-in animation + hover/select FX
  const now = Date.now()
  let selGrp = null
  meshGroupMap.forEach((grp, id) => {
    const ud = grp.userData
    if (!ud.buildStarted) ud.buildStarted = now
    const elapsed2 = now - ud.buildStarted - ud.buildDelay
    if (elapsed2 > 0) {
      const t = Math.min(1, elapsed2 / 700)
      const ease = 1 - Math.pow(1 - t, 3)
      ud.currentY += (ud.baseY - ud.currentY) * 0.12
      grp.position.y = (ud.currentY - 1.5) * (1 - ease) + ud.currentY * ease
    }

    const isHov = hoveredSpace.value === id && !ud.sold
    const isSel = selectedSpace.value?.id === id && !ud.sold
    if (isSel) selGrp = grp

    // Hover glow
    if (ud.hoverLight) {
      const targetI = isHov || isSel ? 0.8 : 0
      ud.hoverLight.intensity += (targetI - ud.hoverLight.intensity) * 0.15
    }

    // Hover emissive
    grp.children.forEach(c => {
      if (c.isMesh && c.material && c.material.emissive) {
        c.material.emissiveIntensity += ((isHov || isSel ? 0.25 : 0) - c.material.emissiveIntensity) * 0.12
      }
    })

    // Selected tent pulse
    if (isSel && ud.tent) {
      ud.tent.scale.y = 1 + Math.sin(elapsed * 2.5) * 0.025
    }
  })

  // Wireframe for selected stand
  const selId = selectedSpace.value?.id
  const prevSelId = selectedWireframe?.parent?.userData?.sp?.id
  if (selId !== prevSelId) {
    setWireframe(selId ? meshGroupMap.get(selId) : null)
  }

  // Animate pedestrians
  peatones.forEach(p => {
    p.group.position.x += p.speed * p.dir * 60 * delta
    if (p.group.position.x > 56) { p.dir = -1; p.group.rotation.y = Math.PI }
    if (p.group.position.x < -56) { p.dir = 1; p.group.rotation.y = 0 }
    const cycle = Math.sin(elapsed * 4.5 + p.phase)
    p.legL.rotation.x  =  cycle * 0.45
    p.legR.rotation.x  = -cycle * 0.45
    p.armL.rotation.x  = -cycle * 0.3
    p.armR.rotation.x  =  cycle * 0.3
    p.group.position.y = p.baseY + Math.abs(Math.sin(elapsed * 4.5 + p.phase)) * 0.025
  })

  // Animate grandstand crowd
  gradaFigures.forEach(gf => {
    gf.mesh.position.y = gf.baseY + Math.sin(elapsed * 2.0 + gf.phase) * 0.03
  })

  // Flow arrows pulse
  scene.children.forEach(obj => {
    if (obj.userData.isArrow) {
      obj.material.opacity = 0.4 + Math.sin(elapsed * 3 + obj.userData.arrowPhase) * 0.3
    }
  })

  // Throttled hover raycast
  rayThrottle += delta
  if (rayThrottle > 0.05 && needsRaycast) {
    updateHover(); rayThrottle = 0
  }

  renderer.render(scene, camera)
}

// ─── RESIZE ──────────────────────────────────────────────────────────────────
function onResize() {
  windowW.value = window.innerWidth
  windowH.value = window.innerHeight
  if (!renderer || !camera) return
  const canvas = canvasRef.value
  const w = canvas.clientWidth, h = canvas.clientHeight
  renderer.setSize(w, h)
  camera.aspect = w / h
  camera.fov = isMobilePortrait.value ? 55 : 45
  camera.updateProjectionMatrix()
}

// ─── LIFECYCLE ───────────────────────────────────────────────────────────────
onMounted(async () => {
  let p = 0
  const pInterval = setInterval(() => {
    p += Math.random() * 20
    if (p >= 95) { clearInterval(pInterval); p = 95 }
    progress.value = p
  }, 100)

  try {
    await Promise.all([
      loadScript('https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js'),
      loadScript('https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js'),
    ])
    THREE = window.THREE
    GSAP  = window.gsap
    await new Promise(r => setTimeout(r, 250))
    progress.value = 100
    await new Promise(r => setTimeout(r, 350))
    loading.value = false
    initScene()
  } catch(e) {
    console.warn('Error cargando Three.js / GSAP:', e)
    webglFailed.value = true
    loading.value = false
  }
})

onUnmounted(() => {
  if (rafId) cancelAnimationFrame(rafId)
  if (renderer) renderer.dispose()
  window.removeEventListener('resize', onResize)
})
</script>

<style scoped>
@import url('https://fonts.bunny.net/css?family=figtree:400,600,700,800,900&display=swap');

* { box-sizing: border-box; margin: 0; padding: 0; }

/* ── THEME VARS ─────────────────────────────────────────────────────────────── */
.demo-dark {
  --bg-panel:      #0d1117;
  --bg-header:     rgba(0,0,0,0.88);
  --text-primary:  #f0f0f0;
  --text-secondary:#9ca3af;
  --border-subtle: #1f2937;
  --canvas-bg:     #0a0a0a;
}
.demo-light {
  --bg-panel:      #ffffff;
  --bg-header:     rgba(255,255,255,0.93);
  --text-primary:  #111827;
  --text-secondary:#6b7280;
  --border-subtle: #e5e7eb;
  --canvas-bg:     #f4f4f4;
}

/* ── ROOT ───────────────────────────────────────────────────────────────────── */
.demo-root {
  font-family: 'Figtree', sans-serif;
  width: 100vw;
  height: 100dvh;
  overflow: hidden;
  position: relative;
  color: var(--text-primary);
  background: var(--canvas-bg);
  transition: background 0.5s, color 0.5s;
}

/* ── LOADING SCREEN ─────────────────────────────────────────────────────────── */
.loading-screen {
  position: fixed; inset: 0; z-index: 300;
  background: #0a0a0a;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 16px;
}
.loading-logo {
  font-size: 72px; font-weight: 900; color: #f59e0b;
  letter-spacing: 6px;
  text-shadow: 0 0 40px rgba(245,158,11,0.5);
  animation: pulse-logo 1s ease-in-out infinite;
}
@keyframes pulse-logo { 0%,100%{opacity:1} 50%{opacity:0.6} }
.loading-text { font-size: 14px; font-weight: 700; color: rgba(255,255,255,0.5); letter-spacing: 4px; text-transform: uppercase; }
.loading-bar { width: 280px; height: 4px; background: #1f2937; border-radius: 4px; overflow: hidden; }
.loading-fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #8b1028); border-radius: 4px; transition: width 0.15s ease; }
.loading-pct { color: rgba(255,255,255,0.35); font-size: 13px; font-weight: 600; }

/* ── NAVBAR ─────────────────────────────────────────────────────────────────── */
.demo-nav {
  position: fixed; top: 0; left: 0; right: 0; height: 62px;
  z-index: 100; display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
  background: var(--bg-header);
  backdrop-filter: blur(14px);
  border-bottom: 2px solid #f59e0b;
  transition: background 0.5s;
}
.nav-left { display: flex; align-items: center; gap: 14px; }
.btn-volver {
  color: #f59e0b; text-decoration: none; font-weight: 700; font-size: 13px;
  border: 1px solid #f59e0b; padding: 5px 12px; border-radius: 6px; transition: background 0.2s;
}
.btn-volver:hover { background: rgba(245,158,11,0.15); }
.logo-y200 { font-size: 22px; font-weight: 900; color: #8b1028; letter-spacing: 2px; }
.nav-title { font-size: 12px; font-weight: 700; color: var(--text-secondary); letter-spacing: 1px; text-transform: uppercase; }
.nav-right { display: flex; align-items: center; gap: 14px; }
.nav-avail { display: flex; align-items: baseline; gap: 4px; }
.nav-avail-num { font-size: 26px; font-weight: 900; color: #f59e0b; line-height: 1; }
.nav-avail-sep { font-size: 13px; color: var(--text-secondary); }
.nav-avail-lbl { font-size: 10px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px; }
.nav-avail-track { width: 80px; height: 5px; background: var(--border-subtle); border-radius: 5px; overflow: hidden; }
.nav-avail-fill { height: 100%; background: linear-gradient(90deg, #8b1028, #f59e0b); transition: width 0.4s; }
.theme-toggle {
  font-size: 20px; background: none; border: 1px solid var(--border-subtle);
  border-radius: 20px; padding: 4px 10px; cursor: pointer; transition: border-color 0.3s, transform 0.2s;
}
.theme-toggle:hover { border-color: #f59e0b; transform: scale(1.1); }

/* ── MAIN LAYOUT ────────────────────────────────────────────────────────────── */
.demo-main {
  display: flex; flex-direction: column;
  height: 100dvh; overflow: hidden;
}
.demo-main.has-nav { height: calc(100dvh - 62px); margin-top: 62px; }

/* ── CANVAS WRAPPER ─────────────────────────────────────────────────────────── */
.canvas-wrapper {
  flex: 1 1 auto; position: relative; overflow: hidden;
  background: var(--canvas-bg); transition: background 0.5s;
}
.portrait-canvas { flex: 0 0 58vh !important; }

.three-canvas { width: 100%; height: 100%; display: block; }

/* ── FALLBACK 2D ────────────────────────────────────────────────────────────── */
.fallback-2d { position: absolute; inset: 0; overflow: auto; padding: 12px; background: #0a0a0a; }
.fallback-scroll { min-width: 700px; }
.fallback-svg { width: 100%; max-width: 1200px; display: block; cursor: default; }

/* ── TOOLTIP ────────────────────────────────────────────────────────────────── */
.tooltip {
  position: fixed; z-index: 160; pointer-events: none;
  background: var(--bg-panel); border: 1.5px solid #f59e0b;
  border-radius: 8px; padding: 8px 12px; font-size: 12px; max-width: 230px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.4);
  color: var(--text-primary); line-height: 1.5;
}
.sold-tag { color: #ef4444; font-weight: 700; margin-left: 4px; }
.avail-tag { color: #f59e0b; font-size: 11px; margin-left: 4px; }

/* ── LEGEND DESKTOP ─────────────────────────────────────────────────────────── */
.legend {
  position: absolute; bottom: 20px; left: 16px; z-index: 50;
  background: var(--bg-panel); border: 1px solid var(--border-subtle);
  border-radius: 10px; padding: 10px 14px;
  display: flex; flex-wrap: wrap; gap: 6px 14px;
  backdrop-filter: blur(8px);
  transition: background 0.5s, border-color 0.5s;
}
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-secondary); white-space: nowrap; }
.ldot { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }

/* ── AVAILABILITY BAR DESKTOP ───────────────────────────────────────────────── */
.avail-bar-wrap {
  position: absolute; bottom: 20px; right: 16px; z-index: 50;
  background: var(--bg-panel); border: 1px solid var(--border-subtle);
  border-radius: 10px; padding: 10px 14px; min-width: 200px;
  backdrop-filter: blur(8px);
  transition: background 0.5s;
}
.avail-bar-lbl { font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; }
.avail-track { height: 6px; background: var(--border-subtle); border-radius: 6px; overflow: hidden; }
.avail-fill { height: 100%; background: linear-gradient(90deg, #8b1028, #f59e0b); border-radius: 6px; transition: width 0.5s; }

/* ── ZOOM BUTTONS ───────────────────────────────────────────────────────────── */
.cam-btns {
  position: absolute; top: 16px; right: 16px; z-index: 50;
  display: flex; flex-direction: column; gap: 6px;
}
.cam-btns-portrait { bottom: 16px; top: auto; right: 14px; }
.cam-btn {
  width: 42px; height: 42px; border-radius: 50%;
  background: #8b1028; color: white; border: none;
  font-size: 20px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 10px rgba(0,0,0,0.4); transition: transform 0.15s, opacity 0.15s;
}
.cam-btn:hover { transform: scale(1.12); }
.cam-reset-btn { font-size: 16px; }

/* ── MOBILE CANVAS OVERLAYS ─────────────────────────────────────────────────── */
.legend-toggle-btn {
  position: absolute; top: 14px; left: 14px; z-index: 50;
  font-size: 22px; background: var(--bg-panel); border: 1px solid var(--border-subtle);
  border-radius: 50%; width: 42px; height: 42px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.theme-toggle-mobile {
  position: absolute; top: 14px; left: 62px; z-index: 50;
  font-size: 20px; background: var(--bg-panel); border: 1px solid var(--border-subtle);
  border-radius: 50%; width: 42px; height: 42px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.pinch-hint {
  position: absolute; bottom: 80px; left: 50%; transform: translateX(-50%);
  background: rgba(0,0,0,0.75); color: white; font-size: 13px; font-weight: 600;
  padding: 8px 16px; border-radius: 20px; pointer-events: none; z-index: 60;
}

/* ── MOBILE EVENT HEADER ────────────────────────────────────────────────────── */
.mobile-event-header {
  flex: 0 0 auto; padding: 8px 16px;
  background: var(--bg-header); border-top: 1px solid var(--border-subtle);
  display: flex; align-items: center; gap: 12px;
  backdrop-filter: blur(10px); transition: background 0.5s;
}
.meh-brand { display: flex; align-items: center; gap: 8px; flex: 1; }
.meh-logo { font-size: 18px; font-weight: 900; color: #8b1028; letter-spacing: 2px; }
.meh-title { font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
.meh-avail { display: flex; align-items: baseline; gap: 3px; font-size: 13px; color: var(--text-secondary); white-space: nowrap; }
.meh-num { font-size: 20px; font-weight: 900; color: #f59e0b; line-height: 1; }
.meh-lbl { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 2px; }
.meh-theme-btn {
  font-size: 18px; background: none; border: 1px solid var(--border-subtle);
  border-radius: 16px; padding: 3px 8px; cursor: pointer;
}

/* ── BOTTOM PANEL (MOBILE) ──────────────────────────────────────────────────── */
.bottom-panel {
  flex: 1 1 auto; overflow-y: auto; -webkit-overflow-scrolling: touch;
  padding: 14px 16px; background: var(--bg-panel);
  border-top: 2px solid var(--border-subtle);
  transition: background 0.5s, border-color 0.3s;
}
.bp-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.bp-id { font-size: 20px; font-weight: 900; color: var(--text-primary); }
.bp-cat { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
.bp-size { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }
.bp-price-wrap { text-align: right; }
.bp-price { font-size: 24px; font-weight: 900; color: #f59e0b; line-height: 1; }
.bp-price-mxn { font-size: 11px; color: rgba(245,158,11,0.7); }
.bp-actions { display: flex; gap: 8px; align-items: center; }
.btn-reservar-bp {
  flex: 1; display: block; background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #000; text-decoration: none; text-align: center; padding: 12px;
  border-radius: 8px; font-size: 14px; font-weight: 900; letter-spacing: 1px;
}
.bp-close-btn {
  background: none; border: 1px solid var(--border-subtle);
  color: var(--text-secondary); padding: 12px 14px; border-radius: 8px;
  font-size: 12px; cursor: pointer; font-family: 'Figtree', sans-serif;
  white-space: nowrap;
}
.bp-empty {
  display: flex; align-items: center; gap: 10px; padding: 8px 0;
  font-size: 14px; color: var(--text-secondary);
}
.bp-empty-icon { font-size: 22px; }
.bp-legend-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
.bp-leg-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--text-secondary); }
.bp-ldot { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }

/* ── SIDE PANEL (DESKTOP) ───────────────────────────────────────────────────── */
.side-panel {
  position: fixed; top: 62px; right: 0; bottom: 0; width: 370px; z-index: 120;
  background: var(--bg-panel); border-left: 3px solid #f59e0b;
  overflow-y: auto; padding: 24px 20px;
  display: flex; flex-direction: column; gap: 12px;
  transition: background 0.5s;
}
.panel-close {
  align-self: flex-end; background: transparent; border: 1px solid var(--border-subtle);
  color: var(--text-primary); width: 32px; height: 32px; border-radius: 50%;
  cursor: pointer; font-size: 14px; transition: background 0.2s;
}
.panel-close:hover { background: rgba(255,255,255,0.08); }
.panel-badge {
  display: inline-block; padding: 4px 12px; border-radius: 20px;
  font-size: 11px; font-weight: 700; letter-spacing: 1px; color: #fff; align-self: flex-start;
}
.panel-status { font-size: 12px; color: #10b981; font-weight: 700; letter-spacing: 2px; }
.panel-divider { height: 1px; background: var(--border-subtle); }
.panel-name { font-size: 28px; font-weight: 900; letter-spacing: 1px; }
.panel-sub { font-size: 13px; color: var(--text-secondary); margin-top: -8px; }
.panel-details { display: flex; flex-direction: column; gap: 8px; }
.panel-drow { display: flex; justify-content: space-between; font-size: 13px; }
.panel-drow span:first-child { color: var(--text-secondary); }
.panel-section-title { font-size: 11px; color: var(--text-secondary); letter-spacing: 2px; text-align: center; margin: 4px 0; }
.panel-includes { list-style: none; display: flex; flex-direction: column; gap: 6px; }
.panel-includes li { font-size: 13px; color: var(--text-secondary); }
.panel-price { font-size: 36px; font-weight: 900; color: #f59e0b; line-height: 1; }
.panel-price span { font-size: 18px; font-weight: 600; color: rgba(245,158,11,0.7); }
.panel-price-note { font-size: 12px; color: var(--text-secondary); margin-top: -6px; }
.btn-reservar {
  display: block; background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #000; text-decoration: none; text-align: center; padding: 16px;
  border-radius: 10px; font-size: 15px; font-weight: 900; letter-spacing: 2px;
  transition: transform 0.2s, box-shadow 0.2s; margin-top: 4px;
}
.btn-reservar:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(245,158,11,0.4); }
.panel-redirect-note { font-size: 11px; color: var(--text-secondary); text-align: center; margin-top: -6px; }

/* ── SIDE PANEL TRANSITION ──────────────────────────────────────────────────── */
.slide-panel-enter-active, .slide-panel-leave-active { transition: transform 0.3s ease; }
.slide-panel-enter-from, .slide-panel-leave-to { transform: translateX(100%); }

/* ── LEGEND MODAL (MOBILE) ──────────────────────────────────────────────────── */
.legend-modal-overlay {
  position: fixed; inset: 0; z-index: 400;
  background: rgba(0,0,0,0.7); backdrop-filter: blur(6px);
  display: flex; align-items: flex-end; justify-content: center; padding: 0 0 20px;
}
.legend-modal {
  background: var(--bg-panel); border-radius: 16px 16px 12px 12px;
  padding: 24px 20px; width: calc(100% - 32px); max-width: 420px;
  display: flex; flex-direction: column; gap: 12px;
  border: 1px solid var(--border-subtle);
}
.lm-title { font-size: 16px; font-weight: 900; color: var(--text-primary); }
.lm-item { display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--text-secondary); }
.lm-dot { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }
.lm-close {
  background: var(--border-subtle); border: none; border-radius: 8px;
  padding: 12px; font-size: 14px; font-weight: 700; cursor: pointer;
  font-family: 'Figtree', sans-serif; color: var(--text-primary); margin-top: 4px;
}

/* ── TRANSITIONS ────────────────────────────────────────────────────────────── */
.fade-load-enter-active { transition: opacity 0.4s ease; }
.fade-load-leave-active { transition: opacity 0.5s ease; }
.fade-load-enter-from, .fade-load-leave-to { opacity: 0; }

/* ── RESPONSIVE DESKTOP ─────────────────────────────────────────────────────── */
@media (max-width: 900px) {
  .nav-title { display: none; }
}
@media (min-width: 769px) {
  .mobile-event-header, .bottom-panel { display: none !important; }
}
</style>
