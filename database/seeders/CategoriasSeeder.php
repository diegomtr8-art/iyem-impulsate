<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriasSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            'Alimentos y Bebidas',
            'Tecnología',
            'Salud y Bienestar',
            'Servicios Profesionales',
            'Manufactura',
            'Comercio',
            'Construcción',
            'Educación',
            'Turismo',
            'Logística',
            'Finanzas',
            'Textiles',
            'Artesanías',
            'Muebles y decoración',
            'Servicios impresos',
            'Vinos y licores',
            'Entretenimiento',
            // NUEVAS:
            'Blancos',
            'Limpieza e higiene',
            'Equipos, mantenimiento y reparación',
            'Cristalería, plásticos y utensilios',
            'Otro',
        ];

        foreach ($categorias as $i => $nombre) {
            Categoria::updateOrCreate(
                ['nombre' => $nombre],
                ['orden' => $i, 'activo' => true]
            );
        }

        $this->command->info('  ✅ ' . count($categorias) . ' categorías registradas.');
    }
}
