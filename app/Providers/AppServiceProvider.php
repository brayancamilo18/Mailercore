<?php

namespace App\Providers;

use App\Services\Auditoria\Comprobaciones\CertificadoCaduca;
use App\Services\Auditoria\Comprobaciones\GeneradorObsoleto;
use App\Services\Auditoria\Comprobaciones\H1Incorrecto;
use App\Services\Auditoria\Comprobaciones\HtmlPesado;
use App\Services\Auditoria\Comprobaciones\ImagenesSinAlt;
use App\Services\Auditoria\Comprobaciones\PaginaContactoRota;
use App\Services\Auditoria\Comprobaciones\PsiAccesibilidadBaja;
use App\Services\Auditoria\Comprobaciones\PsiLcpLento;
use App\Services\Auditoria\Comprobaciones\PsiPesoExcesivo;
use App\Services\Auditoria\Comprobaciones\PsiRendimientoMalo;
use App\Services\Auditoria\Comprobaciones\PsiSeoBajo;
use App\Services\Auditoria\Comprobaciones\RespuestaLenta;
use App\Services\Auditoria\Comprobaciones\SinAvisoLegal;
use App\Services\Auditoria\Comprobaciones\SinCarrito;
use App\Services\Auditoria\Comprobaciones\SinCookies;
use App\Services\Auditoria\Comprobaciones\SinFormularioContacto;
use App\Services\Auditoria\Comprobaciones\SinHttps;
use App\Services\Auditoria\Comprobaciones\SinJsonLd;
use App\Services\Auditoria\Comprobaciones\SinMetaDescription;
use App\Services\Auditoria\Comprobaciones\SinRedesSociales;
use App\Services\Auditoria\Comprobaciones\SinReservas;
use App\Services\Auditoria\Comprobaciones\SinViewport;
use App\Services\Auditoria\Comprobaciones\SinWhatsapp;
use App\Services\Auditoria\Comprobaciones\TitleMalo;
use App\Services\Auditoria\Comprobaciones\WebAbandonada;
use App\Services\Auditoria\MotorAuditoria;
use App\Services\Clasificacion\CatalogoSectores;
use App\Services\Inbox\LectorBandeja;
use App\Services\Inbox\LectorBandejaImap;
use App\Services\Overpass\OverpassClient;
use App\Services\Soporte\ComprobadorRobots;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CatalogoSectores::class);

        $this->app->singleton(OverpassClient::class, function (): OverpassClient {
            return new OverpassClient(config('outreach.overpass'));
        });

        $this->app->bind(ComprobadorRobots::class, function (): ComprobadorRobots {
            return new ComprobadorRobots(config('outreach.scraper'));
        });

        $this->app->bind(LectorBandeja::class, LectorBandejaImap::class);

        $this->app->singleton(MotorAuditoria::class, function (): MotorAuditoria {
            return new MotorAuditoria([
                new SinHttps, new PsiRendimientoMalo, new PsiLcpLento, new SinViewport,
                new SinReservas, new SinCarrito, new CertificadoCaduca,
                new PsiPesoExcesivo, new WebAbandonada, new PsiSeoBajo,
                new SinAvisoLegal, new RespuestaLenta, new PsiAccesibilidadBaja,
                new SinJsonLd, new TitleMalo, new PaginaContactoRota,
                new SinMetaDescription, new SinFormularioContacto,
                new GeneradorObsoleto, new HtmlPesado, new H1Incorrecto,
                new ImagenesSinAlt, new SinCookies, new SinRedesSociales,
                new SinWhatsapp,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
