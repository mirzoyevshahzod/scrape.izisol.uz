<?php

namespace App\Http\Controllers;

use App\Models\JobRun;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Bosh sahifaning o'zi va Boshqaruv yuqorida alohida ko'rsatiladi
        $sections = array_values(array_filter(Navigation::for($user), fn ($group) => $group['title'] !== null));

        $operations = array_filter(
            config('operations.operations'),
            fn ($operation) => $user->can($operation['permission'])
        );

        $recentRuns = $operations
            ? JobRun::with('user:id,name')->whereIn('operation', array_keys($operations))->latest('id')->limit(5)->get()
            : collect();

        return view('index', compact('sections', 'operations', 'recentRuns'));
    }
}
