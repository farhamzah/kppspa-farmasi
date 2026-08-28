@extends('layouts.app')
@section('title','Input Nilai Ujian - '.config('app.name'))
@section('page_title','Input Nilai Ujian')
@section('content')
@include('shared.assessments.score-form', ['assignment' => $assignment, 'components' => $components, 'saveRoute' => route('examiner.assessments.save',$exam), 'submitRoute' => route('examiner.assessments.submit',$exam)])
@endsection
