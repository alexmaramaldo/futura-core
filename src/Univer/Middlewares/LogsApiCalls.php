<?php

namespace Univer\Middlewares;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LogsApiCalls
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $connParameters = $request->header('conn');
        if(!$connParameters){
            $connParameters = $request->header('deviceid');
        }

        if(strpos($connParameters,':')!= false){
            list($id_perfil,$token) = explode(':',$connParameters);
        } else{
            $id_perfil = $request->header('perfilid') ?  $request->header('perfilid') :  $request->header('conn_perfil');
            $token = $connParameters;
        }

        if(!defined('DEVICE_ID')){
            define('DEVICE_ID',$token);
        }

        if(!defined('ID_PERFIL')){
            define('ID_PERFIL',$id_perfil);
        }

        $response = $next($request);

//        if($response instanceof JsonResponse){
//            $textResponse = $response->getData();
//        } else{
//            $textResponse = null;
//        }
//        try{
//
//            DB::table('api_request_log')->insert([
//                'device_id'=>$deviceId,
//                'ip'=>$request->ip(),
//                'url'=>$request->fullUrl(),
//                'request'=>json_encode($request->except('password')),
//                'response'=>json_encode($textResponse),
//                'created_at'=>Carbon::now(),
//                'updated_at'=>Carbon::now()
//            ]);
//        } catch(\Exception $ex){
//            \Log::error($ex);
//            return response()->json([
//               'error'=>'falha na requisição '.$ex->getMessage()
//            ]);
//        }

        return $response;

    }
}
