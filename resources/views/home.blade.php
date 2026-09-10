{{-- Scaffolding: proves the Blade -> React -> axios -> API chain works. Delete
     this view, its route, the Ping island and the /api/ping endpoint once real
     screens exist. --}}
@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <main class="mx-auto flex min-h-screen max-w-xl flex-col items-center justify-center gap-6 px-6">
        <div class="text-center">
            <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Blade renders this page. The card below is a React island that reads its
                data from the API.
            </p>
        </div>

        <div data-react-component="Ping"></div>
    </main>
@endsection
