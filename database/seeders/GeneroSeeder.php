<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class GeneroSeeder extends Seeder
{
    // Diccionario de primeros nombres
    private array $hombres = [
        'aaron','abel','abelardo','abraham','adan','adolfo','adrian','agustin',
        'alberto','alejandro','alex','alexis','alfredo','alonso','alvaro',
        'anibal','antonio','armando','armin','arturo','axel','benjamin','brayan',
        'carlos','cesar','christian','claudio','cristian','cristobal','cristopher',
        'darwin','daniel','dario','david','diego','dominic','donovan','edgar',
        'edgard','eduardo','emilio','emmanuel','enrique','ernesto','erick',
        'esteban','evandro','fabian','fabricio','federico','felipe','felix',
        'fidel','francisco','franklin','fredy','freddy','gabriel','gerardo',
        'german','gilberto','gilmer','gonzalo','gregorio','guillermo','gustavo',
        'hector','heriberto','hernando','hugo','humberto','ignacio','irak','ivan',
        'jaime','jafet','javier','jerry','jesus','jhonatan','joel','jorge','jose',
        'juan','julio','kevin','lazaro','leon','leonel','leonardo','lino',
        'lisandro','lorenzo','lucas','luis','manuel','marco','marcos','mario',
        'martin','mauricio','max','miguel','moises','nicolas','omar','orlando',
        'oscar','osvaldo','pablo','patricio','pedro','rafael','raul','raymundo',
        'rene','ricardo','roberto','rodolfo','rodrigo','rogelio','roman','ruben',
        'samuel','santiago','saul','sergio','simon','thomas','tomas','victor',
        'vicente','virgilio','walter','william','weyler','xavier','yair',
        'lalo','migue','mike','rolando','leopoldo','roger','gersayn',
    ];

    private array $mujeres = [
        'abril','adriana','alejandra','alexandra','alexia','alicia','alison',
        'alondra','amalia','ammy','ana','andrea','angelica','angie','anita',
        'anna','antonieta','araceli','aurora','beatriz','berenice','blanca',
        'brisa','carla','carlota','caro','carmen','carolina','cecilia','celia',
        'claudia','concepcion','cristina','cynthia','dana','daniela','debora',
        'diana','dinorah','dolores','elena','elisa','elizabeth','emilia','emma',
        'elsa','esmeralda','esperanza','esther','eugenia','evelyn','fabiola',
        'fernanda','flor','francisca','gabriela','genoveva','georgina','geraldine',
        'gina','giselle','gladys','gleni','gloria','gracia','graciela','guadalupe',
        'guillie','irene','iris','isabel','isabela','ivette','jacqueline',
        'jeniffer','jessica','julia','juliana','julieta','julissa','karen',
        'karina','karla','kitty','lany','laura','leidy','lelia','leticia',
        'ligia','lilia','liliana','linda','lorena','lucia','lluvia','luz',
        'lupita','magali','margarita','mariana','maricela','marina','marisa',
        'marisela','marisol','marta','martha','mary','mayte','mercedes','miriam',
        'monica','nancy','natalia','nataly','nai','nelia','neydy','noelia',
        'norma','ofelia','olivia','paola','patricia','paulina','perla','priscila',
        'raquel','rebeca','rosa','rosalia','rosario','roxana','ruth','samara',
        'sandra','sara','sasha','seydi','sharon','sharis','silvia','sonia',
        'stephanie','sugery','susana','tania','teresa','tracy','valentina',
        'valeria','vanessa','veronica','virginia','viviana','wendy','yamile',
        'yamilet','yanet','yareli','yolanda','ximena','xitlali','xiomara',
        'yara','yoselin','zuleyma','zulema','namy','melba','melissa','pamela',
        'maricruz','cinthia','cinthya','christie','fani','nelly','nelida',
    ];

    private function normalizarNombre(string $nombre): string
    {
        $nombre = preg_replace('/\s*—.*$/', '', $nombre);
        $nombre = preg_replace('/\s*\|.*$/', '', $nombre);
        $mapa = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
                 'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
        return strtolower(strtr(trim($nombre), $mapa));
    }

    private function detectarGenero(string $nombreCompleto): ?string
    {
        $palabras = explode(' ', $this->normalizarNombre($nombreCompleto));
        foreach (array_slice($palabras, 0, 3) as $palabra) {
            if (in_array($palabra, $this->mujeres)) return 'mujer';
            if (in_array($palabra, $this->hombres)) return 'hombre';
        }
        return null; // no identificado → queda en null
    }

    public function run(): void
    {
        User::whereNull('genero')->chunk(100, function ($users) {
            foreach ($users as $user) {
                $genero = $this->detectarGenero($user->name);
                if ($genero) {
                    $user->update(['genero' => $genero]);
                }
            }
        });
    }
}
