@extends('panel.layout')

@section('title', 'Entrar')

@section('content')
    <div class="w-full max-w-sm bg-white border border-marca-bd rounded-xl shadow-[0_1px_2px_rgba(11,31,26,0.04)] p-7">
        <div class="mb-6 text-center">
            <div class="inline-flex items-baseline gap-0.5 justify-center">
                <span class="text-[22px] font-extrabold tracking-[0.14em] text-bosque leading-none">ONEZ</span>
                <span class="inline-block w-[7px] h-[7px] rounded-full bg-brote"></span>
            </div>
            <p class="mt-2 text-[11px] font-semibold tracking-[0.1em] uppercase text-marca-mut">Panel de captación</p>
        </div>

        <h1 class="text-lg font-extrabold text-bosque mb-4">Acceso al panel</h1>

        @if ($errors->any())
            <p class="text-sm font-semibold text-roj bg-roj-bg rounded-lg px-3 py-2 mb-3">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold text-marca-sec mb-1.5">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full border border-marca-bd rounded-lg px-3 py-2.5 text-sm text-marca-txt bg-white focus:outline-none focus:ring-2 focus:ring-brote/40 focus:border-savia">
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold text-marca-sec mb-1.5">Contraseña</label>
                <input id="password" type="password" name="password" required
                       class="w-full border border-marca-bd rounded-lg px-3 py-2.5 text-sm text-marca-txt bg-white focus:outline-none focus:ring-2 focus:ring-brote/40 focus:border-savia">
            </div>
            <button type="submit" class="btn-savia w-full" style="padding:10px 16px;font-size:14px">
                Entrar
            </button>
        </form>
    </div>
@endsection
