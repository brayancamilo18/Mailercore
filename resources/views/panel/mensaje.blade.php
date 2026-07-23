@extends('panel.layout')

@section('title', 'Mensaje #'.$mensaje->id)

@section('content')
    <div class="mb-6">
        <a href="{{ route('cola.indice') }}" class="text-sm text-slate-500 hover:underline">← Cola</a>
        <h1 class="text-2xl font-semibold tracking-tight mt-1">Mensaje #{{ $mensaje->id }}</h1>
        <p class="text-sm text-slate-600">Estado: {{ $mensaje->estado }} · paso {{ $mensaje->paso }}</p>
    </div>

    <section class="bg-white border border-slate-200 p-5 mb-6">
        <h2 class="font-semibold mb-3">Cabeceras</h2>
        <dl class="grid grid-cols-1 gap-2 text-sm">
            <div><dt class="text-slate-500">Asunto</dt><dd class="font-medium">{{ $mensaje->asunto }}</dd></div>
            <div><dt class="text-slate-500">Destinatario</dt><dd class="font-mono">{{ $mensaje->destinatario }}</dd></div>
            <div><dt class="text-slate-500">List-Unsubscribe</dt><dd class="font-mono text-xs break-all">{{ $listUnsubscribe }}</dd></div>
            <div><dt class="text-slate-500">Message-ID</dt><dd class="font-mono text-xs">{{ $mensaje->message_id ?? '— (se asigna al enviar)' }}</dd></div>
        </dl>
    </section>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="bg-white border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">Texto plano (guardado)</h2>
            <pre class="text-sm whitespace-pre-wrap font-mono bg-slate-50 p-3 max-h-[32rem] overflow-auto">{{ $mensaje->cuerpo_texto }}</pre>
        </section>

        <section class="bg-white border border-slate-200 p-5">
            <h2 class="font-semibold mb-3">HTML (guardado)</h2>
            <iframe
                class="w-full h-[32rem] border border-slate-200 bg-white"
                sandbox=""
                srcdoc="{{ e($mensaje->cuerpo_html ?? '') }}"
                title="Vista previa HTML"
            ></iframe>
        </section>
    </div>
@endsection
