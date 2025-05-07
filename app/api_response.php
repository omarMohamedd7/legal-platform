<?php

namespace App;

/**
 * Format API responses in a consistent way
 * 
 * @param mixed $data The data to return
 * @param string $message Optional message
 * @param bool $success Whether the request was successful
 * @param int $status HTTP status code
 * @return \Illuminate\Http\JsonResponse
 */
function api_response($data = null, string $message = '', bool $success = true, int $status = 200)
{
    return response()->json([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], $status);
}

/**
 * Format an error API response
 * 
 * @param string $message Error message
 * @param mixed $errors Validation or other errors
 * @param int $status HTTP status code
 * @return \Illuminate\Http\JsonResponse
 */
function api_error(string $message, $errors = null, int $status = 400)
{
    $response = [
        'success' => false,
        'message' => $message,
    ];
    
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    
    return response()->json($response, $status);
} 