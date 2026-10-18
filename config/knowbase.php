<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat providers
    |--------------------------------------------------------------------------
    |
    | The providers the assistant tries, in order. If one is busy (503),
    | rate limited (429) or unreachable, laravel/ai automatically asks the
    | next one. Set AI_FALLBACK_PROVIDER=ollama to use the local model as a
    | backup (run "ollama pull llama3.2:3b" first), or leave it empty.
    |
    */

    'chat' => [
        'providers' => array_values(array_unique(array_filter([
            env('AI_DEFAULT_PROVIDER', 'gemini'),
            env('AI_FALLBACK_PROVIDER'),
        ]))),
    ],

];
