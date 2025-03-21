<?php
namespace App\Traits;

use Illuminate\Database\QueryException;

trait GeneralTrait
{

 

    public function returnError($responseCode, $msg)
    {
        return response()->json([
            'status' => false,
            'responseCode' => $responseCode,
            'msg'    => $msg
        ]);
        // return response()->json($msg);
    }
    public function returnSuccessMessage($responseCode, $msg)
    {
        /*
        return response()->json([
            'status' => true,
            'responseCode' => $responseCode,
            'msg'    => $msg
        ]);
        */
        return response()->json($msg);
    }
    public function returnData($responseCode, $msg, $key, $value)
    {
        /*
        return response()->json([
            'status' => true,
            'responseCode' => $responseCode,
            'msg'    => $msg,
            $key   => $value,
        ]);
        */
        return response()->json($value);
    }
    public function returnValidations($responseCode, $validator)
    {
        /*
        return response()->json([
            'status' => false,
            'responseCode' => $responseCode,
            'validation'    => $validator->errors(),
        ]);
        */
        return response()->json($validator);
    }
}
