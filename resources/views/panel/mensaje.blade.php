@extends('panel.layout')

@section('title', 'Mensaje #'.$mensaje->id)

@section('content')
    @php
        $remitente = config('mail.from.address') ?: 'info@onez.es';
        $remitenteNombre = config('mail.from.name') ?: 'ONEZ';
        $inicial = mb_strtoupper(mb_substr($remitenteNombre, 0, 1));
    @endphp

    <div class="max-w-[760px] mx-auto">
        <a href="{{ route('cola.indice') }}"
           class="inline-block text-[12.5px] font-bold text-savia hover:text-bosque mb-3.5">
            ‹ Volver a la cola
        </a>

        {{-- Sobre / correo --}}
        <section class="bg-white border border-marca-bd rounded-xl overflow-hidden shadow-[0_2px_8px_rgba(11,31,26,0.06)]">
            <div class="px-5 py-5 sm:px-6 border-b border-[#EFF2EF]">
                <div class="text-lg font-extrabold text-bosque mb-3 leading-snug">{{ $mensaje->asunto }}</div>
                <div class="flex gap-3 items-center">
                    <span class="w-9 h-9 rounded-full bg-savia text-hueso inline-flex items-center justify-center text-[13px] font-extrabold flex-none">
                        {{ $inicial }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="text-[13px] font-bold text-marca-txt">
                            {{ $remitenteNombre }}
                            <span class="font-normal text-marca-mut">&lt;{{ $remitente }}&gt;</span>
                        </div>
                        <div class="text-xs text-marca-mut">
                            para <span class="text-[#4C5A54] font-semibold">{{ $mensaje->destinatario }}</span>
                        </div>
                    </div>
                    <x-marca.estado-correo :estado="$mensaje->estado" />
                </div>

                <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                    <dt class="font-semibold text-marca-mut">De</dt>
                    <dd class="m-0 text-marca-txt">{{ $remitenteNombre }} &lt;{{ $remitente }}&gt;</dd>
                    <dt class="font-semibold text-marca-mut">Para</dt>
                    <dd class="m-0 text-marca-txt font-mono">{{ $mensaje->destinatario }}</dd>
                    <dt class="font-semibold text-marca-mut">Asunto</dt>
                    <dd class="m-0 text-marca-txt font-semibold">{{ $mensaje->asunto }}</dd>
                </dl>
            </div>

            <div class="px-5 py-6 sm:px-6 sm:py-7">
                @if (filled($mensaje->cuerpo_html))
                    <iframe
                        class="w-full min-h-[28rem] border-0 bg-white"
                        sandbox=""
                        srcdoc="{{ e($mensaje->cuerpo_html) }}"
                        title="Vista previa del correo"
                    ></iframe>
                @else
                    <div class="text-[14px] leading-[1.65] text-marca-txt whitespace-pre-line max-w-[60ch]">{{ $mensaje->cuerpo_texto }}</div>
                @endif
            </div>
        </section>

        <details class="mt-3.5 bg-white border border-marca-bd rounded-xl px-5 py-3.5">
            <summary class="text-[11px] font-extrabold tracking-[0.08em] uppercase text-marca-mut cursor-pointer">
                Detalles técnicos
            </summary>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-xs font-mono text-[#4C5A54]">
                <dt class="font-sans font-semibold text-marca-mut">Message-ID</dt>
                <dd class="m-0 break-all">{{ $mensaje->message_id ?? '— (se asigna al enviar)' }}</dd>

                <dt class="font-sans font-semibold text-marca-mut">List-Unsubscribe</dt>
                <dd class="m-0 break-all">{{ $listUnsubscribe }}</dd>

                <dt class="font-sans font-semibold text-marca-mut">Plantilla</dt>
                <dd class="m-0 font-sans">{{ $mensaje->plantilla }} · paso {{ $mensaje->paso }}</dd>

                <dt class="font-sans font-semibold text-marca-mut">Estado</dt>
                <dd class="m-0 font-sans"><x-marca.estado-correo :estado="$mensaje->estado" /></dd>
            </dl>
        </details>
    </div>
@endsection
