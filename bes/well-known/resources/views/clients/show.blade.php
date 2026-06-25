@extends('layouts.app')

@section('title', 'Client Details')

@section('content')
<div class="card">
    <div class="card-header"><h3>{{ $client->name }}</h3></div>
    <div class="card-body">
        <p><strong>Phone:</strong> {{ $client->phone }}</p>
        <p><strong>Address:</strong> {{ $client->address }}</p>
        <a href="{{ route('clients.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>
@endsection
