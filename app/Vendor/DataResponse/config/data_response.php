<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Envelope Keys
    |--------------------------------------------------------------------------
    |
    | Top-level keys used in every JSON response built through DataResponse.
    | Change these if your frontend/API consumers expect different names —
    | every controller and the exception handler pick up the new keys
    | automatically, no code changes needed.
    |
    */

    'keys' => [
        'success' => 'success',
        'message' => 'message',
        'data' => 'data',
        'errors' => 'errors',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Messages
    |--------------------------------------------------------------------------
    |
    | Used when a success()/error() call doesn't pass an explicit message.
    |
    */

    'messages' => [
        'success' => 'Success',
        'error' => 'Error',
    ],

];
