@extends('panel.layout')

@section('title', 'Entrar')

@section('content')
    <div class="max-w-sm mx-auto bg-white border border-slate-200 p-6">
        <h1 class="text-xl font-semibold mb-4">Acceso al panel</h1>

        @if ($errors->any())
            <p class="text-sm text-red-700 mb-3">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="password" class="block text-sm mb-1">Contraseña</label>
                <input id="password" type="password" name="password" required
                       class="w-full border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full bg-slate-900 text-white py-2 text-sm">Entrar</button>
        </form>
    </div>
@endsection
