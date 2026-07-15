<?php

namespace App\Providers;

use App\Services\EmailScraper;
use App\Services\EmailVerifier;
use App\Services\LeadCaptureService;
use App\Services\ScrapeRateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ScrapeRateLimiter::class);

        $this->app->singleton(EmailScraper::class, function ($app): EmailScraper {
            return new EmailScraper(
                config('outreach.scraper'),
                null,
                $app->make(ScrapeRateLimiter::class),
            );
        });

        $this->app->singleton(EmailVerifier::class, function (): EmailVerifier {
            return new EmailVerifier(config('outreach.verifier'));
        });

        $this->app->singleton(LeadCaptureService::class, function ($app): LeadCaptureService {
            return new LeadCaptureService(
                $app->make(EmailScraper::class),
                $app->make(EmailVerifier::class),
            );
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
