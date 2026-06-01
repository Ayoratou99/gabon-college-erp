@extends('layouts.public')

@section('title', 'Documents officiels')

@section('content')
<section class="container py-5">
    <div class="text-center mb-4">
        <h1 class="h3 mb-2"><i class="far fa-file-lines text-primary me-2"></i>Documents officiels</h1>
        <p class="text-muted">Procès-verbaux, textes réglementaires et documents de référence du concours.</p>
    </div>

    @if(empty($documents))
        <div class="alert alert-info text-center mx-auto" style="max-width: 560px;">
            Aucun document officiel disponible pour le moment.
        </div>
    @else
        <div class="row g-3 justify-content-center">
            @foreach($documents as $doc)
                <div class="col-md-6 col-lg-5">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="display-6 {{ $doc['type'] === 'pdf' ? 'text-danger' : 'text-secondary' }}">
                                <i class="far {{ $doc['type'] === 'pdf' ? 'fa-file-pdf' : 'fa-file' }}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h2 class="h6 mb-3">{{ $doc['title'] }}</h2>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ route('documents.officiels.view', $doc['index']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary">
                                        <i class="far fa-eye me-1"></i>Consulter
                                    </a>
                                    <a href="{{ route('documents.officiels.download', $doc['index']) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-download me-1"></i>Télécharger
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
