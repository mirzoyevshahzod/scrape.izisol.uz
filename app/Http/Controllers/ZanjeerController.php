<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ZanjeerController extends Controller
{
    public function index()
    {
        return view('scrape-zanjeer');
    }
}
