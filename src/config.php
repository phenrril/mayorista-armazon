<?php

function mayorista_get_api_key()
{
    $apiKey = getenv('MAYORISTA_API_KEY');
    if ($apiKey !== false && trim($apiKey) !== '') {
        return trim($apiKey);
    }

    return '';
}

function mayorista_api_key_configurada()
{
    return mayorista_get_api_key() !== '';
}

