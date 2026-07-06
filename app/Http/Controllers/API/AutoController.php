<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Auto;
use Illuminate\Http\Request;

class AutoController extends Controller
{
    public function index()
    {
        $autos = Auto::query()
            ->latest()
            ->paginate(100);

        $autos->getCollection()->transform(function ($auto) {
            return [
                'id' => $auto->id,
                'model' => $auto->model,
                'volume' => $auto->volume,
                'license' => $auto->license,
                'trailer_license' => $auto->trailer_license,
                'trailer_type' => $auto->trailer_type,
                'car_number' => $auto->state_number,
                'company_name' => $auto->company_name,
                'tin' => $auto->tin,
                'phone' => $auto->phone,
                'new_phone' => $auto->new_phone,
                'type_of_activity' => $auto->type_of_activity,
                'transport_type' => $auto->transport_type,
                'cargo_type' => $auto->cargo_type,
                'given_date' => $auto->given_date,
                'expried_at' => $auto->expried_at,
                'status' => $auto->status,
                'regional_administration' => $auto->regional_administration,
                'created_by' => $auto->created_by,
                'driver_fio' => $auto->driver_fio,
                'driver_phones' => $auto->driver_phones,
                'deleted_at' => $auto->deleted_at,
                'created_at' => $auto->created_at,
                'updated_at' => $auto->updated_at,
            ];
        });

        return response()->json($autos);
    }
}
