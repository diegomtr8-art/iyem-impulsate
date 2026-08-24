<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    nombre_usuario: String,
    nombre_evento: String,
    puntaje_total: [Number, String],
    criterios: Array,
    notas_rechazo: { type: String, default: null },
});

const puntajeColor = (p) => {
    if (p >= 70) return '#16a34a';
    if (p >= 50) return '#d97706';
    return '#dc2626';
};

const barColor = (p) => {
    if (p >= 70) return '#22c55e';
    if (p >= 50) return '#f59e0b';
    return '#ef4444';
};

const nivel = (p) => {
    if (p >= 80) return { label: 'Excelente', color: '#16a34a' };
    if (p >= 60) return { label: 'Bueno', color: '#d97706' };
    if (p >= 40) return { label: 'En desarrollo', color: '#ea580c' };
    return { label: 'Requiere mejorar', color: '#dc2626' };
};
</script>

<template>
    <Head title="Resultado de tu participación — Impulsate" />

    <div style="min-height:100vh;background:linear-gradient(135deg,#fdf2f4 0%,#f9fafb 50%,#f0fdf4 100%);display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:40px 16px 60px;">

        <!-- Header -->
        <div style="text-align:center;margin-bottom:32px;max-width:480px;width:100%;">
            <img src="/images/logo_impulsate.png" alt="Impulsate" style="height:40px;margin:0 auto 20px;display:block;" />
            <div style="display:inline-flex;align-items:center;gap:6px;background:#fef2f4;border:1px solid #fbc4cd;border-radius:9999px;padding:6px 16px;margin-bottom:16px;">
                <span style="font-size:12px;font-weight:700;color:#8b1028;text-transform:uppercase;letter-spacing:1px;">Resultado de tu participación</span>
            </div>
            <h1 style="font-size:26px;font-weight:900;color:#111827;margin:0 0 6px;">{{ nombre_evento }}</h1>
            <p style="font-size:15px;color:#6b7280;margin:0;">Hola, <strong style="color:#374151;">{{ nombre_usuario }}</strong></p>
        </div>

        <!-- Card principal -->
        <div style="width:100%;max-width:520px;background:#ffffff;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,0.08);overflow:hidden;margin-bottom:16px;">

            <!-- Intro mensaje -->
            <div style="background:linear-gradient(135deg,#8b1028,#45060f);padding:24px 28px;color:#fff;">
                <p style="margin:0;font-size:15px;line-height:1.6;color:#fde8ec;">
                    Gracias por tu interés y dedicación. Sabemos que el proceso no es sencillo, y valoramos mucho que hayas participado. Aquí puedes ver el detalle de tu evaluación para ayudarte a crecer.
                </p>
            </div>

            <!-- Puntaje total -->
            <div style="padding:24px 28px;border-bottom:1px solid #f3f4f6;text-align:center;">
                <p style="font-size:11px;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;font-weight:600;margin:0 0 8px;">Tu puntaje total</p>
                <p style="font-size:52px;font-weight:900;margin:0;line-height:1;" :style="{ color: puntajeColor(puntaje_total) }">
                    {{ puntaje_total ?? '—' }}
                    <span style="font-size:24px;font-weight:600;color:#d1d5db;"> / 100</span>
                </p>
                <div style="margin-top:10px;">
                    <span style="display:inline-block;padding:4px 14px;border-radius:9999px;font-size:12px;font-weight:700;"
                        :style="{ background: nivel(puntaje_total).color + '20', color: nivel(puntaje_total).color }">
                        {{ nivel(puntaje_total).label }}
                    </span>
                </div>
            </div>

            <!-- Criterios -->
            <div v-if="criterios.length" style="padding:24px 28px;border-bottom:1px solid #f3f4f6;">
                <p style="font-size:11px;text-transform:uppercase;letter-spacing:1.5px;color:#9ca3af;font-weight:600;margin:0 0 16px;">Evaluación por criterio</p>
                <div v-for="(c, i) in criterios" :key="i" style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <p style="margin:0;font-size:13px;font-weight:600;color:#374151;">
                            {{ c.nombre }}
                            <span style="font-weight:400;color:#9ca3af;">({{ c.porcentaje }}%)</span>
                        </p>
                        <p style="margin:0;font-size:14px;font-weight:800;" :style="{ color: puntajeColor(c.puntaje) }">{{ c.puntaje }} / 100</p>
                    </div>
                    <div style="height:8px;background:#f3f4f6;border-radius:9999px;overflow:hidden;">
                        <div style="height:100%;border-radius:9999px;transition:width 0.5s;"
                            :style="{ width: c.puntaje + '%', background: barColor(c.puntaje) }"></div>
                    </div>
                    <p style="margin:4px 0 0;font-size:11px;color:#9ca3af;">Aportación: <strong style="color:#6b7280;">{{ c.puntaje_ponderado }}</strong> / {{ c.porcentaje }} pts</p>
                </div>
            </div>

            <!-- Notas del admin -->
            <div v-if="notas_rechazo" style="padding:24px 28px;border-bottom:1px solid #f3f4f6;">
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:18px 20px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                        <svg width="16" height="16" fill="none" stroke="#b45309" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        <p style="margin:0;font-size:12px;font-weight:700;color:#b45309;text-transform:uppercase;letter-spacing:0.5px;">Retroalimentación del equipo Impulsate</p>
                    </div>
                    <p style="margin:0;font-size:14px;color:#78350f;line-height:1.7;">{{ notas_rechazo }}</p>
                </div>
            </div>

            <!-- Mensaje de ánimo -->
            <div style="padding:24px 28px;">
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:18px 20px;text-align:center;">
                    <p style="margin:0 0 8px;font-size:20px;">🌱</p>
                    <p style="margin:0;font-size:14px;color:#15803d;font-weight:600;">¡Sigue adelante!</p>
                    <p style="margin:6px 0 0;font-size:13px;color:#16a34a;line-height:1.6;">Tu esfuerzo tiene valor. Te invitamos a prepararte y participar en futuras ediciones de los eventos de Impulsate.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <p style="font-size:12px;color:#9ca3af;text-align:center;max-width:400px;">
            Instituto Yucateco de Emprendedores · Gobierno del Estado de Yucatán<br>
            <span style="font-size:11px;">Esta página es de acceso personal mediante el enlace enviado a tu correo</span>
        </p>
    </div>
</template>
