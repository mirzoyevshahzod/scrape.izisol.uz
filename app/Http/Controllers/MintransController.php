<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessMintransExcel;

class MintransController extends Controller
{
    public function index()
    {
        try {
            Log::info('MintransController: Index sahifasi ochildi');
            return view('mintrans_auto');
        } catch (\Exception $e) {
            Log::error('MintransController: Index sahifasini ochishda xato: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Sahifani ochishda xato yuz berdi.');
        }
    }
}
