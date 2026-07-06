<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function mintransAuto()
    {
        return view('mintrans_auto');
    }

    public function mintrans()
    {
        return view('mintrans_scrape');
    }

    public function belarus()
    {
        return view('belarus');
    }
    
    public function turkey()
    {
        return view('turkey');
    }
    public function qozoq()
    {
        return view('qozoq');
    }

    public function checkQozoq()
    {
        return view('check-qozoq');
    }

    public function zitic()
    {
        return view('zitic');
    }

    public function orginfo()
    {
        return view('orginfo');
    }

    public function scrapeZanjeer()
    {
        return view('scrape-zanjeer');
    }

    public function uploadZanjeer()
    {
         return view('upload-zanjeer');
    }

    public function eOmborConverter()
    {
        return view('e-ombor-converter');
    }

    public function turkiyaConverter()
    {
        return view('turkiya-converter');
    }

    public function autos()
    {
        return view('autos');
    }

}
