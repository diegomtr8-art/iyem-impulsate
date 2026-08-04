<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte de Encuestas</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
  .page-break { page-break-after: always; }
  h1 { font-size: 20px; color: #ffffff; margin: 0; }
  h2 { font-size: 15px; color: #8B1028; margin: 16px 0 8px; border-bottom: 2px solid #8B1028; padding-bottom: 4px; }
  h3 { font-size: 12px; color: #374151; margin: 0 0 6px; }
  table { border-collapse: collapse; width: 100%; }
  .header-box { background-color: #8B1028; padding: 24px 30px; }
  .header-sub { color: #fbc4cd; font-size: 12px; margin-top: 4px; }
  .kpi-table td { text-align: center; padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; }
  .kpi-num { font-size: 22px; font-weight: bold; color: #8B1028; display: block; }
  .kpi-label { font-size: 9px; color: #6b7280; display: block; margin-top: 2px; }
  .pregunta-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 12px; }
  .tipo-badge { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
  .chart-table td { vertical-align: top; }
  .legend-item { font-size: 10px; margin-bottom: 4px; }
  .legend-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }
  .avg-num { font-size: 28px; font-weight: bold; color: #8B1028; }
  .bar-bg { background: #e5e7eb; height: 10px; border-radius: 4px; }
  .bar-fill { background: #8B1028; height: 10px; border-radius: 4px; }
  .persona-card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; margin-bottom: 10px; }
  .persona-header { background: #8B1028; color: white; padding: 6px 10px; border-radius: 4px; margin-bottom: 8px; font-size: 11px; font-weight: bold; }
  .resp-row td { padding: 3px 0; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
  .resp-q { color: #6b7280; width: 55%; }
  .resp-a { font-weight: bold; text-align: right; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: bold; }
  .badge-prov { background: #fce7eb; color: #8B1028; }
  .badge-comp { background: #d1fae5; color: #065f46; }
</style>
</head>
<body>

{{-- ── PORTADA / KPIs ── --}}
<table class="header-box" width="100%">
  <tr>
    <td>
      <h1>📊 Reporte de Encuestas de Satisfacción</h1>
      <p class="header-sub">{{ $plantilla->nombre ?? 'Encuesta de Satisfacción e Impacto' }}</p>
      @if($evento)<p class="header-sub">Evento: {{ $evento->nombre }}</p>@endif
      <p class="header-sub">Generado: {{ $fecha }}</p>
    </td>
  </tr>
</table>

<table class="kpi-table" style="margin-top: 16px;">
  <tr>
    <td><span class="kpi-num">{{ $totalEnviadas }}</span><span class="kpi-label">ENVIADAS</span></td>
    <td><span class="kpi-num" style="color:#059669;">{{ $totalRespondidas }}</span><span class="kpi-label">RESPONDIDAS</span></td>
    <td><span class="kpi-num">{{ $tasa }}%</span><span class="kpi-label">TASA RESPUESTA</span></td>
    <td><span class="kpi-num" style="color:#2563eb;">{{ $compradores }}</span><span class="kpi-label">COMPRADORES</span></td>
    <td><span class="kpi-num" style="color:#7c3aed;">{{ $proveedores }}</span><span class="kpi-label">PROVEEDORES</span></td>
  </tr>
</table>

{{-- ── SECCIÓN GENERAL: gráficas por pregunta ── --}}
<div class="page-break"></div>
<h2>Resultados Generales por Pregunta</h2>

@php
  $colores = ['#8B1028','#c2435d','#e8899a','#f3b8c3','#2563eb','#7c3aed','#059669','#d97706','#374151','#9ca3af'];
@endphp

@foreach($datosGenerales as $preguntaTxt => $data)
<div class="pregunta-card">
  <span class="tipo-badge">{{ $data['tipo'] }}</span>
  <h3>{{ $preguntaTxt }}</h3>

  @if(in_array($data['tipo'], ['opciones', 'multiple', 'binario']))
    @php
      $cx = 60; $cy = 60; $r = 55;
      $total = $data['total'];
      $startAngle = -90;
      $paths = [];
      $colorIdx = 0;
      foreach ($data['opciones'] as $opcion => $stat) {
          $pct = $stat['porcentaje'];
          $angle = ($pct / 100) * 360;
          $endAngle = $startAngle + $angle;
          $largeArc = $angle > 180 ? 1 : 0;
          $x1 = $cx + $r * cos(deg2rad($startAngle));
          $y1 = $cy + $r * sin(deg2rad($startAngle));
          $x2 = $cx + $r * cos(deg2rad($endAngle));
          $y2 = $cy + $r * sin(deg2rad($endAngle));
          $paths[] = [
              'path'   => "M {$cx},{$cy} L {$x1},{$y1} A {$r},{$r} 0 {$largeArc},1 {$x2},{$y2} Z",
              'color'  => $colores[$colorIdx % count($colores)],
              'opcion' => $opcion,
              'pct'    => $pct,
              'count'  => $stat['count'],
          ];
          $startAngle = $endAngle;
          $colorIdx++;
      }
    @endphp
    <table class="chart-table">
      <tr>
        <td width="130">
          <svg width="120" height="120" viewBox="0 0 120 120">
            @if($total > 0 && count($paths) === 1)
              <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="{{ $paths[0]['color'] }}"/>
            @else
              @foreach($paths as $p)
                <path d="{{ $p['path'] }}" fill="{{ $p['color'] }}" stroke="white" stroke-width="1"/>
              @endforeach
            @endif
          </svg>
        </td>
        <td>
          @foreach($paths as $p)
            <div class="legend-item">
              <span class="legend-dot" style="background:{{ $p['color'] }};"></span>
              {{ Str::limit($p['opcion'], 55) }} — <strong>{{ $p['pct'] }}%</strong> ({{ $p['count'] }})
            </div>
          @endforeach
          <p style="color:#9ca3af; font-size:9px; margin-top:4px;">Total respuestas: {{ $total }}</p>
        </td>
      </tr>
    </table>
  @elseif(in_array($data['tipo'], ['escala', 'nps']))
    <table class="chart-table">
      <tr>
        <td width="80"><span class="avg-num">{{ $data['promedio'] }}</span></td>
        <td>
          @foreach($data['opciones'] as $valor => $stat)
            <div style="margin-bottom:4px;">
              <div style="font-size:9px; color:#6b7280;">{{ $valor }} — {{ $stat['count'] }}</div>
              <div class="bar-bg"><div class="bar-fill" style="width:{{ $stat['porcentaje'] }}%"></div></div>
            </div>
          @endforeach
        </td>
      </tr>
    </table>
  @endif
</div>
@endforeach

{{-- ── SECCIÓN POR PERSONA ── --}}
<div class="page-break"></div>
<h2>Respuestas por Persona ({{ $respuestasPorPersona->count() }} respondientes)</h2>

@foreach($respuestasPorPersona as $persona)
<div class="persona-card">
  <div class="persona-header">
    {{ $persona['nombre'] }} &nbsp;|&nbsp; {{ $persona['email'] }} &nbsp;
    <span class="badge {{ $persona['tipo'] === 'proveedor' ? 'badge-prov' : 'badge-comp' }}">
      {{ ucfirst($persona['tipo']) }}
    </span>
    &nbsp; {{ $persona['fecha'] }}
  </div>
  <table>
    @foreach($persona['respuestas'] as $resp)
    <tr class="resp-row">
      <td class="resp-q">{{ $resp['pregunta'] }}</td>
      <td class="resp-a">{{ $resp['respuesta'] ?: '—' }}</td>
    </tr>
    @endforeach
  </table>
</div>
@if(!$loop->last && $loop->iteration % 4 === 0)<div class="page-break"></div>@endif
@endforeach

</body>
</html>
