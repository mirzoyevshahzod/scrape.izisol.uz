<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index()
    {
        $order = \DB::connection('zanjeer')
            ->table('orders_view as o')

            ->join(
                'contragents as c',
                'o.carrier_contragent_id',
                '=',
                'c.id'
            )

            ->leftJoin(
                'users as u',
                'o.operation_id',
                '=',
                'u.id'
            )

            ->where('c.company_name', 'like', '%MEGA STARLAYT TRANS%')

            ->select(
                'o.custom_id',
                'o.created_at',
                'o.operation_id',
                'c.company_name',
                'u.name as operation_manager',
                'u.email as operation_email'
            )

            ->orderBy('o.created_at', 'desc')

            ->first();

        dd($order);
    }

}
