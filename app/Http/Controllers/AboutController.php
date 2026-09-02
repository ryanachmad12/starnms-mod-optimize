<?php

namespace App\Http\Controllers;

class AboutController extends Controller
{
    public function index()
    {
        $build = config('app.build');

        return view('about', compact('build'));
    }
}
