<?php
// funciones/GPT/banco_comparacion/motores.php
declare(strict_types=1);

// Config de los 4 motores a comparar.
// 'api' define el formato de payload/parseo en el driver: openai | anthropic | xai
return [
    'grok' => [
        'label'    => 'Grok 4.3',
        'api'      => 'xai',
        'endpoint' => 'https://api.x.ai/v1/chat/completions',
        'model'    => 'grok-4.3',
        'key_var'  => 'XAI_API_KEY',
        'params'   => [
            'temperature' => 0.1,
            'max_tokens'  => 1500,
        ],
    ],
    'claude' => [
        'label'    => 'Claude Sonnet 4.6',
        'api'      => 'anthropic',
        'endpoint' => 'https://api.anthropic.com/v1/messages',
        'model'    => 'claude-sonnet-4-6',
        'key_var'  => 'ANTHROPIC_API_KEY',
        'params'   => [
            'temperature' => 0.1,
            'max_tokens'  => 3000,
        ],
    ],
    'gpt54' => [
        'label'    => 'GPT-5.4',
        'api'      => 'openai',
        'endpoint' => 'https://api.openai.com/v1/chat/completions',
        'model'    => 'gpt-5.4',
        'key_var'  => 'OPENAI_API_KEY',
        'params'   => [
            'reasoning_effort'      => 'low',
            'max_completion_tokens' => 4000,
        ],
    ],
];