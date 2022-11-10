<?php

namespace App\Exceptions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;
use App\Logs\Logging;
use App\Enumerations\LogTypes;

class ExceptionTypeHandler
{
    public static function generalResponse($exception)
    {
        $responseData = [
            'status' => false,
            'code' => 500,
            'message' => 'form-error',
            'data' => $exception->getMessage(),
        ];

        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $responseData = self::NotFoundHttpExceptionHandler($exception);
        } elseif ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            $responseData = self::HttpExceptionHandler($exception);
        } elseif ($exception instanceof \Illuminate\Validation\ValidationException) {
            $responseData = self::ValidationExceptionHandler($exception);
        } else {
            $responseData = self::FatalThrowableErrorHandler($exception);
        }

        $statusCode = $responseData['code'];
        unset($responseData['code']);

        return response()->json($responseData, $statusCode);
    }

    private static function NotFoundHttpExceptionHandler($exception)
    {
        $message = 'Not Found! The specific API could not be found.';
        return [
            'status' => false,
            'code' => $exception->getStatusCode(),
            'message' => 'form-error',
            'data' => $message
        ];
    }

    private static function HttpExceptionHandler($exception)
    {
        $className = get_class($exception);
        $className = explode('\\', $className);
        $message = end($className) . ' on ' . Request::url();

        if (!env('APP_DEBUG')) {
            switch ($exception->getStatusCode()) {
                case 400:
                    $message = 'Bad Request! Your API request is invalid.';
                    break;
                case 401:
                    $message = 'Unauthorized! Your API key is wrong.';
                    break;
            }
        }

        if ($exception->getMessage())
            $message = $exception->getMessage();

        return [
            'status' => false,
            'code' => $exception->getStatusCode(),
            'message' => 'form-error',
            'data' => $message
        ];
    }

    private static function FatalThrowableErrorHandler($exception)
    {
        if (env('APP_DEBUG')) {
            $message = $exception->getMessage()
                . ' in ' . $exception->getFile()
                . ' on Line ' . $exception->getLine();
        } else {
            $message = 'Internal Server Error! We had a problem with our server. Try again later.';
        }
        $statusCode = 500;

        return [
            'status' => false,
            'code' => $statusCode,
            'message' => 'form-error',
            'data' => $message
        ];
    }

    private static function ValidationExceptionHandler($exception)
    {
        $data = [];
        $statusCode = 400;
        foreach ($exception->errors() as $key => $error) {
            $data[$key] = $error[0];
        }
        
        return [
            'status' => false,
            'code' => $statusCode,
            'message' => 'form-error',
            'data' => $data
        ];
    }
}
