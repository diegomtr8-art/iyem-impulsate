<template>
  <div class="paid-page">
    <div class="paid-container">
      <!-- COLUMNA IZQUIERDA: Resumen -->
      <div class="summary-col">
        <div class="summary-card" :style="{ borderColor: typeColor(spaceType) }">
          <div class="summary-header">
            <div class="logo-y200">Y·200</div>
            <div class="summary-title">RESUMEN DE COMPRA</div>
          </div>

          <div class="space-icon" :style="{ background: typeColor(spaceType) + '22', borderColor: typeColor(spaceType) }">
            <svg v-if="spaceType === 'C'" viewBox="0 0 48 48" fill="none">
              <path d="M8 36 Q24 20 40 36" stroke="currentColor" stroke-width="3" fill="none"/>
              <rect x="14" y="18" width="20" height="18" rx="2" fill="currentColor" opacity="0.3"/>
              <path d="M14 18 L24 10 L34 18" stroke="currentColor" stroke-width="3" fill="none"/>
              <rect x="10" y="36" width="28" height="4" rx="2" fill="currentColor" opacity="0.5"/>
            </svg>
            <svg v-else-if="spaceType === 'B'" viewBox="0 0 48 48" fill="none">
              <path d="M18 12 L16 36 Q16 40 24 40 Q32 40 32 36 L30 12 Z" fill="currentColor" opacity="0.3"/>
              <path d="M18 12 L30 12" stroke="currentColor" stroke-width="3"/>
              <ellipse cx="24" cy="24" rx="7" ry="5" fill="currentColor" opacity="0.2"/>
            </svg>
            <svg v-else viewBox="0 0 48 48" fill="none">
              <rect x="6" y="20" width="36" height="22" rx="2" fill="currentColor" opacity="0.3"/>
              <path d="M4 20 L24 8 L44 20" stroke="currentColor" stroke-width="3" fill="none"/>
              <rect x="18" y="28" width="12" height="14" rx="1" fill="currentColor" opacity="0.6"/>
              <rect x="10" y="26" width="8" height="6" rx="1" fill="currentColor" opacity="0.4"/>
              <rect x="30" y="26" width="8" height="6" rx="1" fill="currentColor" opacity="0.4"/>
            </svg>
          </div>

          <div class="space-name">Espacio {{ spaceId }}</div>
          <div class="space-category" :style="{ color: typeColor(spaceType) }">{{ spaceTipo }}</div>
          <div class="space-size">{{ spaceSize }}</div>

          <div class="price-display">${{ formatPrice(spacePrice) }} <span>MXN</span></div>
          <div class="price-note">Precio por día de evento</div>

          <div class="includes-title">── INCLUYE ──</div>
          <ul class="includes-list">
            <li>✓ Espacio delimitado con señalética</li>
            <li>✓ 1 mesa y 2 sillas</li>
            <li>✓ Conexión eléctrica 110v</li>
            <li>✓ Acceso peatonal desde ambos lados</li>
            <li>✓ Visibilidad directa a la pista</li>
          </ul>

          <div class="demo-warning">
            <span class="warn-icon">⚠</span>
            Esta es una demostración. No se realizará ningún cargo real.
          </div>

          <a href="/demo-prueba/layout" class="btn-back-layout">← Ver otros espacios</a>
        </div>
      </div>

      <!-- COLUMNA DERECHA: Formulario -->
      <div class="form-col">
        <div class="form-header">
          <div class="form-title">FORMULARIO DE RESERVA</div>
          <div class="form-sub">Completa tus datos y un asesor se pondrá en contacto contigo</div>
        </div>

        <form @submit.prevent="submitForm" class="reservation-form" novalidate>
          <div class="form-group">
            <label>Nombre completo <span class="req">*</span></label>
            <input v-model="form.nombre" type="text" placeholder="Ej. Juan Pérez García" :class="{ error: errors.nombre }" />
            <span v-if="errors.nombre" class="error-msg">{{ errors.nombre }}</span>
          </div>

          <div class="form-group">
            <label>Empresa / Marca <span class="req">*</span></label>
            <input v-model="form.empresa" type="text" placeholder="Ej. Alimentos del Sur S.A." :class="{ error: errors.empresa }" />
            <span v-if="errors.empresa" class="error-msg">{{ errors.empresa }}</span>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Correo electrónico <span class="req">*</span></label>
              <input v-model="form.email" type="email" placeholder="correo@empresa.com" :class="{ error: errors.email }" />
              <span v-if="errors.email" class="error-msg">{{ errors.email }}</span>
            </div>
            <div class="form-group">
              <label>Teléfono <span class="req">*</span></label>
              <input v-model="form.telefono" type="tel" placeholder="10 dígitos" maxlength="10" :class="{ error: errors.telefono }" />
              <span v-if="errors.telefono" class="error-msg">{{ errors.telefono }}</span>
            </div>
          </div>

          <div class="form-group">
            <label>Giro del negocio <span class="req">*</span></label>
            <select v-model="form.giro" :class="{ error: errors.giro }">
              <option value="">Selecciona una opción</option>
              <option>Alimentos</option>
              <option>Bebidas</option>
              <option>Moda</option>
              <option>Tecnología</option>
              <option>Servicios</option>
              <option>Artesanías</option>
              <option>Automotriz</option>
              <option>Otro</option>
            </select>
            <span v-if="errors.giro" class="error-msg">{{ errors.giro }}</span>
          </div>

          <div class="form-group">
            <label>¿Es primera vez en el evento? <span class="req">*</span></label>
            <div class="radio-group">
              <label class="radio-label">
                <input type="radio" v-model="form.primeraVez" value="si" />
                <span>Sí, es mi primera vez</span>
              </label>
              <label class="radio-label">
                <input type="radio" v-model="form.primeraVez" value="no" />
                <span>Ya he participado antes</span>
              </label>
            </div>
            <span v-if="errors.primeraVez" class="error-msg">{{ errors.primeraVez }}</span>
          </div>

          <div class="form-group">
            <label>Comentarios adicionales</label>
            <textarea v-model="form.comentarios" rows="3" placeholder="Necesidades especiales, preguntas, etc."></textarea>
          </div>

          <div class="form-group">
            <label class="checkbox-label" :class="{ error: errors.terminos }">
              <input type="checkbox" v-model="form.terminos" />
              <span>Acepto los <a href="#" @click.prevent>términos y condiciones</a> del evento</span>
            </label>
            <span v-if="errors.terminos" class="error-msg">{{ errors.terminos }}</span>
          </div>

          <button type="submit" class="btn-confirmar" :disabled="submitting">
            <span v-if="submitting" class="btn-loading">⟳ Procesando...</span>
            <span v-else>CONFIRMAR RESERVA</span>
          </button>
        </form>
      </div>
    </div>

    <!-- MODAL DE ÉXITO -->
    <transition name="fade-modal">
      <div v-if="showSuccess" class="success-overlay" @click.self="goToLayout">
        <div class="success-modal">
          <!-- Animated check -->
          <div class="check-container">
            <svg class="check-svg" viewBox="0 0 52 52">
              <circle class="check-circle" cx="26" cy="26" r="25" fill="none"/>
              <path class="check-mark" fill="none" d="M14 27 L22 35 L38 18"/>
            </svg>
          </div>
          <div class="success-title">¡Tu reserva ha sido recibida!</div>
          <div class="success-sub">Un asesor se pondrá en contacto contigo en menos de 24 horas.</div>
          <div class="success-details">
            <div class="success-row">
              <span>Espacio reservado</span>
              <strong>{{ spaceId }}</strong>
            </div>
            <div class="success-row">
              <span>Folio</span>
              <strong>DEMO-{{ folio }}</strong>
            </div>
            <div class="success-row">
              <span>Precio</span>
              <strong>${{ formatPrice(spacePrice) }} MXN</strong>
            </div>
          </div>
          <div class="success-actions">
            <a href="/demo-prueba/layout" class="btn-success-layout">Ver otro espacio</a>
            <a href="/" class="btn-success-home">Ir al inicio</a>
          </div>
          <canvas ref="confettiCanvas" class="confetti-canvas"></canvas>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

// ─── QUERY PARAMS ─────────────────────────────────────────────────────────────
const urlParams    = new URLSearchParams(window.location.search)
const spaceId      = urlParams.get('espacio') ?? 'L1'
const spacePrice   = parseInt(urlParams.get('precio') ?? '8500')
const spaceTipo    = urlParams.get('tipo') ?? 'Espacio Comercial'

const spaceType = computed(() => {
  if (spaceId.startsWith('C')) return 'C'
  if (spaceId.startsWith('B')) return 'B'
  if (spaceId.startsWith('M')) return 'M'
  return 'L'
})

const spaceSize = computed(() => {
  return (spaceType.value === 'M') ? '6 × 4 metros' : '9 × 4 metros'
})

// ─── FORM STATE ───────────────────────────────────────────────────────────────
const form = ref({
  nombre:     '',
  empresa:    '',
  email:      '',
  telefono:   '',
  giro:       '',
  primeraVez: '',
  comentarios:'',
  terminos:   false,
})
const errors    = ref({})
const submitting = ref(false)
const showSuccess = ref(false)
const folio      = ref(Date.now())
const confettiCanvas = ref(null)

// ─── HELPERS ──────────────────────────────────────────────────────────────────
function typeColor(type) {
  return { L:'#7c3aed', M:'#dc2626', C:'#16a34a', B:'#0891b2' }[type] ?? '#4b5563'
}

function formatPrice(p) {
  return Number(p).toLocaleString('es-MX')
}

// ─── VALIDATION ───────────────────────────────────────────────────────────────
function validate() {
  const e = {}
  if (!form.value.nombre.trim())   e.nombre    = 'El nombre es requerido'
  if (!form.value.empresa.trim())  e.empresa   = 'La empresa es requerida'
  if (!form.value.email.trim())    e.email     = 'El correo es requerido'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) e.email = 'Correo inválido'
  if (!form.value.telefono.trim()) e.telefono  = 'El teléfono es requerido'
  else if (!/^\d{10}$/.test(form.value.telefono)) e.telefono = 'Debe tener 10 dígitos'
  if (!form.value.giro)            e.giro      = 'Selecciona un giro'
  if (!form.value.primeraVez)      e.primeraVez = 'Selecciona una opción'
  if (!form.value.terminos)        e.terminos  = 'Debes aceptar los términos'
  errors.value = e
  return Object.keys(e).length === 0
}

// ─── SUBMIT ───────────────────────────────────────────────────────────────────
function submitForm() {
  if (!validate()) return
  submitting.value = true
  folio.value = Date.now()
  setTimeout(() => {
    submitting.value = false
    showSuccess.value = true
    setTimeout(launchConfetti, 300)
  }, 900)
}

function goToLayout() {
  window.location.href = '/demo-prueba/layout'
}

// ─── CONFETTI ─────────────────────────────────────────────────────────────────
function launchConfetti() {
  if (typeof window.confetti !== 'undefined') {
    const end = Date.now() + 2500
    const colors = ['#f59e0b','#8b1028','#ffffff','#fbbf24','#dc2626']
    const frame = () => {
      window.confetti({ particleCount: 3, angle: 60,  spread: 55, origin: { x: 0 }, colors })
      window.confetti({ particleCount: 3, angle: 120, spread: 55, origin: { x: 1 }, colors })
      if (Date.now() < end) requestAnimationFrame(frame)
    }
    frame()
  }
}

onMounted(() => {
  const script = document.createElement('script')
  script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js'
  document.head.appendChild(script)
})
</script>

<style scoped>
@import url('https://fonts.bunny.net/css?family=figtree:400,600,700,800,900&display=swap');

* { box-sizing: border-box; }

.paid-page {
  font-family: 'Figtree', sans-serif;
  min-height: 100vh;
  background: linear-gradient(135deg, #0a0a0a 0%, #111827 100%);
  padding: 40px 20px;
  color: white;
}

.paid-container {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1.4fr;
  gap: 32px;
  align-items: start;
}

/* ── SUMMARY ── */
.summary-col {}
.summary-card {
  background: #111827;
  border: 2px solid #f59e0b;
  border-radius: 16px;
  padding: 28px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  position: sticky;
  top: 20px;
}
.summary-header {
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  padding-bottom: 14px;
}
.logo-y200 {
  font-size: 20px;
  font-weight: 900;
  color: #f59e0b;
  letter-spacing: 2px;
}
.summary-title {
  font-size: 11px;
  font-weight: 700;
  color: rgba(255,255,255,0.5);
  letter-spacing: 2px;
}

.space-icon {
  border: 1.5px solid;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.space-icon svg {
  width: 64px;
  height: 64px;
  color: white;
}

.space-name {
  font-size: 32px;
  font-weight: 900;
  letter-spacing: 1px;
  line-height: 1;
}
.space-category {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-top: -8px;
}
.space-size {
  font-size: 13px;
  color: rgba(255,255,255,0.5);
}
.price-display {
  font-size: 40px;
  font-weight: 900;
  color: #f59e0b;
  line-height: 1;
}
.price-display span {
  font-size: 18px;
  font-weight: 600;
  color: rgba(245,158,11,0.6);
}
.price-note {
  font-size: 11px;
  color: rgba(255,255,255,0.35);
  margin-top: -10px;
}
.includes-title {
  font-size: 11px;
  color: rgba(255,255,255,0.35);
  letter-spacing: 2px;
  text-align: center;
}
.includes-list {
  list-style: none;
  padding: 0; margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.includes-list li {
  font-size: 13px;
  color: rgba(255,255,255,0.65);
}
.demo-warning {
  background: rgba(245,158,11,0.1);
  border: 1px solid rgba(245,158,11,0.3);
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 12px;
  color: #fbbf24;
  display: flex;
  gap: 8px;
  align-items: flex-start;
}
.warn-icon { font-size: 14px; flex-shrink: 0; }
.btn-back-layout {
  display: block;
  text-align: center;
  color: rgba(255,255,255,0.5);
  text-decoration: none;
  font-size: 13px;
  font-weight: 600;
  padding: 10px;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  transition: border-color 0.2s, color 0.2s;
}
.btn-back-layout:hover {
  border-color: rgba(245,158,11,0.5);
  color: #f59e0b;
}

/* ── FORM ── */
.form-col {}
.form-header {
  margin-bottom: 24px;
}
.form-title {
  font-size: 22px;
  font-weight: 900;
  letter-spacing: 2px;
  color: white;
}
.form-sub {
  font-size: 13px;
  color: rgba(255,255,255,0.45);
  margin-top: 6px;
}
.reservation-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.form-group label {
  font-size: 13px;
  font-weight: 700;
  color: rgba(255,255,255,0.7);
  letter-spacing: 0.5px;
}
.req { color: #f59e0b; }
.form-group input,
.form-group select,
.form-group textarea {
  background: #1f2937;
  border: 1.5px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  color: white;
  font-family: 'Figtree', sans-serif;
  font-size: 14px;
  padding: 11px 14px;
  transition: border-color 0.2s, box-shadow 0.2s;
  outline: none;
  width: 100%;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: #f59e0b;
  box-shadow: 0 0 0 3px rgba(245,158,11,0.15);
}
.form-group input.error,
.form-group select.error { border-color: #ef4444; }
.form-group select option { background: #1f2937; }
.error-msg {
  font-size: 11px;
  color: #ef4444;
  font-weight: 600;
}
.radio-group {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.radio-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: rgba(255,255,255,0.7);
  cursor: pointer;
  font-weight: 400 !important;
}
.radio-label input[type="radio"] {
  width: auto;
  accent-color: #f59e0b;
}
.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: rgba(255,255,255,0.7);
  cursor: pointer;
  font-weight: 400 !important;
}
.checkbox-label.error { color: #ef4444; }
.checkbox-label input[type="checkbox"] {
  width: auto;
  margin-top: 2px;
  accent-color: #f59e0b;
  flex-shrink: 0;
}
.checkbox-label a { color: #f59e0b; }

.btn-confirmar {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #000;
  border: none;
  border-radius: 10px;
  padding: 18px;
  font-family: 'Figtree', sans-serif;
  font-size: 16px;
  font-weight: 900;
  letter-spacing: 2px;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
  width: 100%;
  margin-top: 4px;
}
.btn-confirmar:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(245,158,11,0.4);
}
.btn-confirmar:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
.btn-loading { animation: spin 0.8s linear infinite; display: inline-block; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── MODAL ── */
.success-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.85);
  backdrop-filter: blur(6px);
  z-index: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.success-modal {
  background: #111827;
  border: 2px solid #f59e0b;
  border-radius: 20px;
  padding: 40px 32px;
  max-width: 440px;
  width: 100%;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.confetti-canvas {
  position: absolute;
  inset: 0;
  pointer-events: none;
  width: 100%;
  height: 100%;
}
.check-container {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}
.check-svg {
  width: 72px;
  height: 72px;
}
.check-circle {
  stroke: #f59e0b;
  stroke-width: 3;
  stroke-dasharray: 166;
  stroke-dashoffset: 166;
  animation: stroke-circle 0.6s ease forwards;
}
.check-mark {
  stroke: #10b981;
  stroke-width: 4;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 48;
  stroke-dashoffset: 48;
  animation: stroke-check 0.4s 0.5s ease forwards;
}
@keyframes stroke-circle {
  to { stroke-dashoffset: 0; }
}
@keyframes stroke-check {
  to { stroke-dashoffset: 0; }
}
.success-title {
  font-size: 24px;
  font-weight: 900;
  color: white;
  margin-bottom: 8px;
}
.success-sub {
  font-size: 14px;
  color: rgba(255,255,255,0.55);
  margin-bottom: 24px;
  line-height: 1.5;
}
.success-details {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  padding: 14px 18px;
  margin-bottom: 24px;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.success-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: rgba(255,255,255,0.6);
}
.success-row strong { color: #f59e0b; font-weight: 700; }
.success-actions {
  display: flex;
  gap: 12px;
}
.btn-success-layout,
.btn-success-home {
  flex: 1;
  display: block;
  text-align: center;
  padding: 12px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 13px;
  text-decoration: none;
  transition: transform 0.2s;
}
.btn-success-layout {
  background: transparent;
  border: 1.5px solid rgba(245,158,11,0.5);
  color: #f59e0b;
}
.btn-success-layout:hover { transform: translateY(-1px); }
.btn-success-home {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #000;
  border: none;
}
.btn-success-home:hover { transform: translateY(-1px); }

.fade-modal-enter-active, .fade-modal-leave-active { transition: opacity 0.3s; }
.fade-modal-enter-from, .fade-modal-leave-to { opacity: 0; }

/* RESPONSIVE */
@media (max-width: 768px) {
  .paid-container {
    grid-template-columns: 1fr;
  }
  .summary-card { position: static; }
  .form-row { grid-template-columns: 1fr; }
  .paid-page { padding: 20px 16px; }
}
</style>
