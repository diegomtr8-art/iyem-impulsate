<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Mueve los INE/CSF ya subidos de storage/app/public a storage/app/private
     * y les cambia el nombre a uno aleatorio no adivinable.
     * Actualiza users.ine_path / users.csf_path con la ruta nueva.
     */
    public function up(): void
    {
        $usuarios = DB::table('users')
            ->select('id', 'ine_path', 'csf_path')
            ->where(function ($q) {
                $q->whereNotNull('ine_path')->orWhereNotNull('csf_path');
            })
            ->get();

        $movidos = 0;
        $faltantes = 0;

        foreach ($usuarios as $u) {
            $cambios = [];

            foreach (['ine' => $u->ine_path, 'csf' => $u->csf_path] as $tipo => $rutaVieja) {
                if (!$rutaVieja) continue;

                // Si ya esta en privado, no hacer nada
                if (Storage::disk('local')->exists($rutaVieja) && !Storage::disk('public')->exists($rutaVieja)) {
                    continue;
                }

                if (!Storage::disk('public')->exists($rutaVieja)) {
                    $faltantes++;
                    Log::warning("[mover_documentos] No se encontro {$rutaVieja} (user {$u->id})");
                    continue;
                }

                $extension = pathinfo($rutaVieja, PATHINFO_EXTENSION) ?: 'pdf';
                $rutaNueva = "documentos/{$u->id}/{$tipo}_" . Str::random(40) . ".{$extension}";

                Storage::disk('local')->put($rutaNueva, Storage::disk('public')->get($rutaVieja));
                Storage::disk('public')->delete($rutaVieja);

                $cambios[$tipo . '_path'] = $rutaNueva;
                $movidos++;
            }

            if ($cambios) {
                DB::table('users')->where('id', $u->id)->update($cambios);
            }
        }

        Log::info("[mover_documentos] {$movidos} archivos movidos a disco privado. {$faltantes} no encontrados.");
    }

    /**
     * Irreversible por diseño: volver a exponer documentos de identidad en el
     * disco público sería reintroducir la vulnerabilidad.
     */
    public function down(): void
    {
        // no-op intencional
    }
};
