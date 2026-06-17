<?php

namespace App\Http\Controllers;

use App\Models\Cook;
use Illuminate\Http\Request;

class CookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('pages::cooks.index', [
            'cooks' => Cook::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Cook $cook)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cook $cook)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cook $cook)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cook $cook)
    {
        //
    }
}
