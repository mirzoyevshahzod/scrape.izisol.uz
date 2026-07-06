<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ZiticController extends Controller
{
    public function index()
    {
        return view('zitic');
    }
}
