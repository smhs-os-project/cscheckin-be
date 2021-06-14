<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GeneralController extends Controller
{
    public function welcome()
    {
        return view('welcome');
    }

    public function isOk()
    {
        return response()->json(['success' => true]);
    }
}
