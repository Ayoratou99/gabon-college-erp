@extends('layouts.public')

@section('title', 'Choisir un rôle — ' . config('app.name'))

@section('content')
<section class="container py-5" style="max-width:640px">

    <div class="text-center mb-4">
        <i class="fas fa-user-tag text-primary" style="font-size:3rem;"></i>
        <h1 class="h3 mt-3 mb-1">Bonjour {{ $user->prenom }} {{ $user->nom }}</h1>
        <p class="text-muted">
            Vous avez plusieurs rôles attribués.
            Choisissez celui sous lequel vous souhaitez vous connecter aujourd'hui.
        </p>
    </div>

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('role.select') }}">
        @csrf
        <div class="d-grid gap-3">
            @foreach($roles as $role)
                <button type="submit" name="role_id" value="{{ $role->id }}"
                        class="btn btn-outline-primary text-start p-3 d-flex align-items-center gap-3">
                    <span class="role-icon">
                        @switch($role->code)
                            @case('super-admin') <i class="fas fa-user-shield fs-3"></i> @break
                            @case('dg')          <i class="fas fa-user-tie fs-3"></i> @break
                            @case('de')          <i class="fas fa-chalkboard-user fs-3"></i> @break
                            @case('chef-centre') <i class="fas fa-building-columns fs-3"></i> @break
                            @case('candidat')    <i class="fas fa-user-graduate fs-3"></i> @break
                            @default             <i class="fas fa-circle-user fs-3"></i>
                        @endswitch
                    </span>
                    <span>
                        <span class="fw-semibold fs-5 d-block">{{ $role->name }}</span>
                        @if($role->description)
                            <span class="text-muted small">{{ $role->description }}</span>
                        @endif
                    </span>
                    <span class="ms-auto"><i class="fas fa-arrow-right text-muted"></i></span>
                </button>
            @endforeach
        </div>
    </form>

    <div class="text-center mt-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link text-muted small">
                <i class="fas fa-sign-out-alt me-1"></i>Se déconnecter
            </button>
        </form>
    </div>

</section>
@endsection
