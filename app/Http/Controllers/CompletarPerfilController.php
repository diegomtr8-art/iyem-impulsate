<?php

namespace App\Http\Controllers;

use App\Models\Restaurantero;
use App\Services\DocumentosUsuarioService;
use App\Services\OnboardingService;
use App\Services\PerfilCompradorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompletarPerfilController extends Controller
{
    public function __construct(
        private PerfilCompradorService $perfil,
        private OnboardingService $onboarding,
        private DocumentosUsuarioService $documentos,
    ) {}

    public const MUNICIPIOS = [
        'Abalá','Acanceh','Akil','Baca','Bokobá','Buctzotz','Calkiní',
        'Calotmul','Cansahcab','Cantamayec','Celestún','Cenotillo','Conkal',
        'Cuncunul','Cuzamá','Chacsinkín','Chankom','Chapab','Chemax',
        'Chicxulub Pueblo','Chichimilá','Chikindzonot','Chocholá','Chumayel',
        'Dzan','Dzemul','Dzidzantún','Dzilam de Bravo','Dzilam González',
        'Dzitás','Dzoncauich','Espita','Halachó','Hocabá','Hoctún','Homún',
        'Huhí','Hunucmá','Ixil','Izamal','Kanasín','Kantunil','Kaua',
        'Kinchil','Kopomá','Mama','Maní','Maxcanú','Mayapán','Mérida',
        'Mocochá','Motul','Muna','Muxupip','Opichén','Oxkutzcab','Panabá',
        'Peto','Progreso','Quintana Roo','Río Lagartos','Sacalum','Samahil',
        'Sanahcat','San Felipe','Santa Elena','Seyé','Sinanché','Sotuta',
        'Sucilá','Sudzal','Suma','Tahdziú','Tahmek','Teabo','Tecoh',
        'Tekal de Venegas','Tekantó','Tekax','Tekit','Tekom','Telchac Pueblo',
        'Telchac Puerto','Temax','Temozón','Tepakán','Tetiz','Teya','Ticul',
        'Timucuy','Tinum','Tixcacalcupul','Tixkokob','Tixmehuac','Tixpéhual',
        'Tizimín','Tunkás','Tzucacab','Uayma','Ucú','Umán','Valladolid',
        'Xocchel','Yaxcabá','Yaxkukul','Yobaín',
    ];

    public function create(Request $request)
    {
        $user = $request->user();

        // Los proveedores completan su perfil en el panel de Restaurantero, no aquí.
        if ($user->hasRole('restaurantero') && !$user->hasRole('cliente')) {
            return redirect()->route('restaurantero.completar-perfil');
        }

        $municipios = self::MUNICIPIOS;
        sort($municipios);

        return Inertia::render('Auth/CompletarPerfil', [
            'user'       => $user,
            'municipios' => $municipios,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(PerfilCompradorService::reglasCompletar($request->camara_asociacion));

        $this->perfil->completar($request->user(), [
            ...$request->only([
                'telefono', 'curp', 'rfc', 'municipio', 'nombre_empresa',
                'camara_asociacion', 'nombre_establecimiento', 'necesidades',
            ]),
            'es_restaurantero' => $request->boolean('es_restaurantero'),
        ]);

        return redirect()->route('dashboard')
            ->with('success', '¡Perfil completado! Ya puedes acceder a todas las funciones.');
    }

    public function actualizarComprador(Request $request)
    {
        $request->validate(PerfilCompradorService::reglasActualizar($request->camara_asociacion));

        $this->perfil->actualizar($request->user(), [
            ...$request->only([
                'name', 'telefono', 'curp', 'rfc', 'municipio', 'genero', 'nombre_empresa',
                'sitio_web', 'necesidades', 'camara_asociacion', 'nombre_establecimiento',
            ]),
            'es_restaurantero' => $request->boolean('es_restaurantero'),
        ]);

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    /** Guardar "necesidades" del comprador y retornar sugerencias */
    public function necesidades(Request $request)
    {
        $request->validate([
            'necesidades' => ['required', 'string', 'max:2000'],
        ]);

        $request->user()->update(['necesidades' => $request->necesidades]);

        // Buscar proveedores sugeridos por palabras clave
        $palabras = collect(preg_split('/\s+/', $request->necesidades))
            ->filter(fn($p) => strlen($p) >= 4)
            ->take(10);

        $query = Restaurantero::where('aprobado', true)->where('activo', true);

        foreach ($palabras as $palabra) {
            $query->orWhere('descripcion', 'LIKE', "%{$palabra}%")
                  ->orWhere('nombre_restaurante', 'LIKE', "%{$palabra}%")
                  ->orWhere('categoria', 'LIKE', "%{$palabra}%");
        }

        $sugeridos = $query->take(5)->get(['id', 'nombre_restaurante', 'categoria', 'descripcion', 'foto_path', 'logo_path', 'municipio']);

        return response()->json([
            'sugeridos' => $sugeridos,
        ]);
    }

    public function agregarRol(Request $request)
    {
        $request->validate(['rol' => 'required|in:' . implode(',', OnboardingService::ROLES_AGREGABLES)]);

        $this->onboarding->agregarRol($request->user(), $request->rol);

        return back()->with('success', 'Rol agregado correctamente. ¡Bienvenido!');
    }

    public function subirDocumentos(Request $request)
    {
        $request->validate(DocumentosUsuarioService::reglas(), DocumentosUsuarioService::mensajes());

        $this->documentos->subir(
            $request->user(),
            [
                'ine' => $request->hasFile('ine') ? $request->file('ine') : null,
                'csf' => $request->hasFile('csf') ? $request->file('csf') : null,
            ],
            $request->input('csf_fecha')
        );

        return back()->with('documentos_success', 'Documentos actualizados correctamente.');
    }
}
