<?php

function mayorista_get_api_key()
{
    $apiKey = getenv('MAYORISTA_API_KEY');
    if ($apiKey !== false && trim($apiKey) !== '') {
        return trim($apiKey);
    }

    return 'cambiar-esta-api-key-en-produccion';
}

