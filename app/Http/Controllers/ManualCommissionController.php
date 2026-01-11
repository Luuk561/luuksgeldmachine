<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualCommissionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'commission' => 'required|numeric|min:0',
            'date' => 'required|date',
            'platform' => 'required|string|max:255',
            'status' => 'required|in:Geaccepteerd,Open,Geweigerd',
            'note' => 'nullable|string|max:1000',
        ]);

        DB::table('manual_commissions')->insert([
            'site_id' => $validated['site_id'],
            'commission' => $validated['commission'],
            'date' => $validated['date'],
            'platform' => $validated['platform'],
            'status' => $validated['status'],
            'note' => $validated['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Manual commission added successfully'
        ]);
    }
}
