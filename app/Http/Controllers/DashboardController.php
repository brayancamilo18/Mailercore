<?php

namespace App\Http\Controllers;

use App\Jobs\RunSearchJob;
use App\Jobs\RunSendJob;
use App\Models\Lead;
use App\Models\Suppression;
use App\Services\EmailScraper;
use App\Services\EmailVerifier;
use App\Services\HarvestStatusService;
use App\Services\LeadCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Panel principal con estadísticas, filtros y listado de leads.
     */
    public function index(Request $request, HarvestStatusService $harvestStatus): View
    {
        $query = Lead::query()->withEmail()->latest();

        if ($request->filled('status') && array_key_exists($request->string('status')->toString(), Lead::ESTADOS)) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('segmento') && array_key_exists($request->string('segmento')->toString(), Lead::SEGMENTOS)) {
            $query->where('segmento', $request->string('segmento'));
        }

        $leads = $query->paginate(50)->withQueryString();

        $stats = [
            'total' => Lead::withEmail()->count(),
            'con_email' => Lead::withEmail()->count(),
            'nuevo' => Lead::withEmail()->where('status', 'nuevo')->count(),
            'contactado' => Lead::withEmail()->where('status', 'contactado')->count(),
            'contactado_hoy' => Lead::withEmail()->whereDate('contacted_at', today())->count(),
            'respondido' => Lead::withEmail()->where('status', 'respondido')->count(),
            'cliente' => Lead::withEmail()->where('status', 'cliente')->count(),
        ];

        $jobStatus = [
            'search_running' => (bool) Cache::get('outreach:search_running'),
            'send_running' => (bool) Cache::get('outreach:send_running'),
        ];

        $harvest = $harvestStatus->snapshot();

        return view('dashboard', compact('leads', 'stats', 'jobStatus', 'harvest'));
    }

    /**
     * Estado de jobs en segundo plano (para el spinner del panel).
     */
    public function jobStatus(HarvestStatusService $harvestStatus): JsonResponse
    {
        return response()->json([
            'search_running' => (bool) Cache::get('outreach:search_running'),
            'send_running' => (bool) Cache::get('outreach:send_running'),
            'leads_total' => Lead::withEmail()->count(),
            'leads_con_email' => Lead::withEmail()->count(),
            'harvest' => $harvestStatus->snapshot(),
        ]);
    }

    /**
     * JSON de vivacidad y avance de la cosecha (móvil / polling).
     */
    public function harvestStatus(HarvestStatusService $harvestStatus): JsonResponse
    {
        return response()->json($harvestStatus->snapshot());
    }

    /**
     * Alta manual de un lead.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $email = Suppression::normalizeEmail($validated['email']);
        $email = $email === '' ? null : $email;

        if ($email === null) {
            return redirect()->route('dashboard')->with('error', 'El email es obligatorio.');
        }

        $capture = new LeadCaptureService(
            new EmailScraper(config('outreach.scraper')),
            new EmailVerifier(config('outreach.verifier')),
        );

        if ($capture->debeOmitirPorEmailODominio($email, $validated['website'] ?? null)) {
            return redirect()->route('dashboard')->with(
                'error',
                'No se creó el lead: email o dominio ya conocido, o presente en suppressions.'
            );
        }

        Lead::create([
            ...$validated,
            'email' => $email,
            'status' => 'nuevo',
            'segmento' => 'agencia',
            'captured_at' => now(),
        ]);

        return redirect()->route('dashboard')->with('ok', 'Lead creado correctamente.');
    }

    /**
     * Actualiza el estado de un lead.
     */
    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Lead::ESTADOS))],
        ]);

        $lead->update(['status' => $request->string('status')]);

        return redirect()->back()->with('ok', 'Estado actualizado.');
    }

    /**
     * Encola la búsqueda de agencias en Overpass.
     */
    public function runSearch(): RedirectResponse
    {
        Cache::put('outreach:search_running', true, now()->addMinutes(45));
        RunSearchJob::dispatch();

        return redirect()->route('dashboard')->with(
            'ok',
            'Búsqueda encolada. Overpass + scraping de webs puede tardar varios minutos; el panel se actualizará solo.'
        );
    }

    /**
     * Encola el envío de correos de outreach.
     */
    public function runSend(Request $request): RedirectResponse
    {
        $limit = $request->filled('limit') ? (int) $request->input('limit') : null;

        Cache::put('outreach:send_running', true, now()->addMinutes(90));
        RunSendJob::dispatch($limit);

        $mensaje = $limit !== null
            ? "Envío encolado con límite de {$limit} correos. El panel se actualizará al terminar."
            : 'Envío encolado. El panel se actualizará al terminar.';

        return redirect()->route('dashboard')->with('ok', $mensaje);
    }
}
