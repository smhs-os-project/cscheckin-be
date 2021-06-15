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

    public function getInfo(Request $request, $org = null)
    {
        if ($org) {
            if (config('google.' . $org) == null) {
                return response()->json(['error' => 'org_not_found'], Response::HTTP_NOT_FOUND);
            }
            $info = config('google.' . $org);
            $file = fopen($info['client_id'], "r");
            $client_id = fgets($file, filesize($info['client_id']));
            return response()->json(['client_id' => $client_id, 'chinese_name' => $info['chinese_name']], Response::HTTP_OK);
        } else {
            $info = config('google');
            $result = array();
            foreach ($info as $key => $value) {
                if ($key == 'MAPPING')
                    continue;
                $file = fopen($value['client_id'], "r");
                $client_id = fgets($file, filesize($value['client_id']));
                $result[] = ['client_id' => $client_id, 'chinese_name' => $value['chinese_name']];
            }
            return response()->json($result, Response::HTTP_OK);
        }

    }
}
