<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualCommissionController extends Controller
{
    public function index()
    {
        $commissions = DB::table('manual_commissions')
            ->leftJoin('sites', 'manual_commissions.site_id', '=', 'sites.id')
            ->select(
                'manual_commissions.*',
                'sites.name as site_name',
                'sites.domain as site_domain'
            )
            ->orderBy('manual_commissions.date', 'desc')
            ->get();

        $sites = DB::table('sites')
            ->orderBy('name')
            ->get();

        return view('manual-commission.index', compact('commissions', 'sites'));
    }

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

        return redirect()->back()->with('success', 'Manual commission added successfully');
    }

    public function destroy($id)
    {
        DB::table('manual_commissions')->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Manual commission deleted successfully');
    }
}
