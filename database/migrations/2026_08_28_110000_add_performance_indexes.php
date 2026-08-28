<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Indices para las tres consultas calientes que hacian escaneo completo:
     *   page_visits.url    -> MetricasController agrupa por ahi
     *   restauranteros     -> el directorio publico filtra por activo/aprobado
     *   evento_usuario     -> el unique (evento_id,user_id,tipo) no cubre
     *                         where('estado', ...) por no ser prefijo izquierdo
     *
     * Se usa SQL crudo porque url es varchar(500) y excede el limite de
     * longitud de indice: hay que indexar un prefijo, y el Blueprint de
     * Laravel no expresa indices de prefijo.
     */
    private array $indices = [
        ['page_visits',    'page_visits_url_idx',           'url(191)'],
        ['restauranteros', 'restauranteros_directorio_idx', 'activo, aprobado, perfil_completo'],
        ['evento_usuario', 'evento_usuario_estado_idx',     'evento_id, estado'],
    ];

    public function up(): void
    {
        foreach ($this->indices as [$tabla, $nombre, $columnas]) {
            if ($this->existe($tabla, $nombre)) {
                continue;   // reanudable: un re-run no falla con "Duplicate key name"
            }

            DB::statement("CREATE INDEX {$nombre} ON {$tabla} ({$columnas})");
        }
    }

    public function down(): void
    {
        foreach ($this->indices as [$tabla, $nombre, $columnas]) {
            if ($this->existe($tabla, $nombre)) {
                DB::statement("DROP INDEX {$nombre} ON {$tabla}");
            }
        }
    }

    private function existe(string $tabla, string $nombre): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tabla)
            ->where('index_name', $nombre)
            ->exists();
    }
};
