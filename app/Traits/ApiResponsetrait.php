<?php
    namespace App\Traits;
    trait ApiResponseTrait{
        protected function success($data,$pagination = [], $status = 200){
            return response()->json(
                [
                    'sucess' => true,
                    'data' => $data,
                    'pagination' => $pagination,
                ], $status
            );
        }

        protected function error($message, $code, $details = [], $status = 500){
            return response()->json(
                [
                    'sucess' => false,
                    'error' => ['code' => $code, 'message' => $message,'details' => $details]
                ],$status
            );
        }
    }
