<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Holiday;

class HolidayController extends Controller
{
    
    public function all()
    {
        return response()->json(
            Holiday::orderBy('holiday_date')->get()
        );
    }

    public function list()
    {
        return response()->json(Holiday::all());
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'name' => 'nullable|string|max:255',
        ]);

        $date = $validated['date'];

        $holiday = Holiday::where('holiday_date', $date)->first();
    
        if ($holiday) {
            $holiday->delete();
    
            return response()->json([
                'status' => 'removed'
            ]);
        }
    
        $holiday = Holiday::create([
            'holiday_date' => $date,
            'name' => $validated['name'] ?? null,
        ]);
    
        return response()->json([
            'status' => 'added',
            'name' => $holiday->name
        ]);
    }
}