@extends('layouts.admin')

@section('title', $definition['title'])
@section('page-title', $definition['title'])

@section('content')
    <x-admin.registry-page
        :slug="$slug"
        :definition="$definition"
        :definitions="$definitions"
        :canManage="$canManage"
        :apiBase="$apiBase"
        :dataUrl="$dataUrl"
        tabRoute="admin.referentiels.index" />
@endsection
