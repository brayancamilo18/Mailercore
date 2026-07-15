<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SendingScheduleGateTest extends TestCase
{
    /**
     * Por defecto el envío está en pausa (no se dispara solo).
     */
    public function test_envio_pausado_por_defecto(): void
    {
        $this->assertFalse((bool) config('outreach.sending.enabled'));
    }

    /**
     * El evento programado de agencies:send NO pasa el filtro con envío pausado.
     */
    public function test_schedule_de_envio_no_corre_si_pausado(): void
    {
        config(['outreach.sending.enabled' => false]);

        $event = $this->eventoDeEnvio();

        $this->assertNotNull($event, 'No se encontró el evento programado agencies:send.');
        $this->assertFalse($event->filtersPass($this->app));
    }

    /**
     * Si se activa explícitamente, el evento sí pasa el filtro (para más adelante).
     */
    public function test_schedule_de_envio_corre_si_activado(): void
    {
        config(['outreach.sending.enabled' => true]);

        $event = $this->eventoDeEnvio();

        $this->assertNotNull($event);
        $this->assertTrue($event->filtersPass($this->app));
    }

    private function eventoDeEnvio(): ?Event
    {
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains((string) $event->command, 'agencies:send')) {
                return $event;
            }
        }

        return null;
    }
}
