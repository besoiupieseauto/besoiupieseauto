@extends('layouts.app')
@section('title', 'Create Client')

@section('content')
<div class="container">
    <h1 class="mb-4">Create New Client</h1>
    <form action="{{ route('clients.store') }}" method="POST">
        @csrf
        <div class="row"> 
            <div class="col-md-4 mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="nume" class="form-control" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Address</label>
                <input type="text" name="adresa" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="telefon" class="form-control">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Car Brand</label>
                <input type="text" name="marca" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Chassis</label>
                <input type="text" name="sasiu" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Registration No</label>
                <input type="text" name="nr_inmat" class="form-control">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Company</label>
                <input type="text" name="companie" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">CIF</label>
                <input type="text" name="cif" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Bank Account</label>
                <input type="text" name="cont_banca" class="form-control">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('clients.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection
