<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Models\VehicleData;
use App\Jobs\RunDeclarantScrapeJob;

class ScrapeController extends Controller
{
    public function index(Request $request)
    {
        try {
            Log::info('ScrapeController: Belarus index sahifasi ochildi');
            
            $query = VehicleData::query();

            // Filtrlarni qo'llash
            if ($request->search) {
                $query->where('reg_number', 'like', '%' . $request->search . '%');
            }
            if ($request->region_filter) {
                $query->where('region', $request->region_filter);
            }
            
            // Pagination bilan ma'lumotlarni olish
            $allData = $query->orderBy('created_at', 'DESC')->paginate(10);

            return view('belarus', compact('allData'));
        } catch (\Exception $e) {
            Log::error('ScrapeController: Index sahifasini ochishda xato: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Sahifani ochishda xato yuz berdi.');
        }
    }

     public function show($id)
    {
        $data = VehicleData::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

}