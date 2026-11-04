<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PercakapanController extends Controller
{
    public function index()
    {
        return view('percakapan.index');
    }
}
