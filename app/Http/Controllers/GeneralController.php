<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function getInfo(Request $request, $org)
    {
        if (config('google.' . $org) == null) {
            return response()->json(['error' => 'org_not_found'], Response::HTTP_NOT_FOUND);
        }
        $info = config('google.' . $org);
        return response()->json(['client_id' => $info['client_id'], 'chinese_name' => $info['chinese_name']], Response::HTTP_OK);
    }
}
