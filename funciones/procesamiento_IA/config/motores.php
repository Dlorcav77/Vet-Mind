<?php
declare(strict_types=1);

return [
    'transcripcion' => [
        'motor_a' => 'deepgram',
        'motor_b' => 'assembly_v3',
        'motor_por_defecto' => 'assembly_v3',
        'usar_keyterms' => '0',

        'parametros' => [
            'deepgram' => [
                'archivo' => 'transcripcion/motores/deepgram.php',
                'model' => 'nova-3',
                'language' => 'es',
                'smart_format' => true,
                'punctuate' => true,
                'timeouts' => [
                    'connect' => 15,
                    'total' => 180,
                ],
            ],

            'assembly_v3' => [
                'archivo' => 'transcripcion/motores/assembly_v3.php',
                'speech_models' => [
                    'universal-3-pro',
                    'universal-2',
                ],
                'language_code' => 'es',
                'format_text' => true,
                'poll_delays' => [2, 3, 5, 8, 8, 8, 8],
                'timeouts' => [
                    'upload' => ['connect' => 15, 'total' => 120],
                    'request' => ['connect' => 10, 'total' => 30],
                    'poll' => ['connect' => 10, 'total' => 15],
                ],
            ],

            'grok' => [
                'archivo' => 'transcripcion/motores/grok.php',
                'model' => 'grok-stt',
                'language' => 'es-MX',
                'format' => 'true',
                'timeouts' => [
                    'connect' => 15,
                    'total' => 180,
                ],
            ],

            'openai_4o' => [
                'archivo' => 'transcripcion/motores/openai_4o.php',
                'model' => 'gpt-4o-transcribe',
                'language' => 'es',
                'response_format' => 'json',
                'timeouts' => [
                    'connect' => 15,
                    'total' => 180,
                ],
            ],
        ],
    ],


    'generacion' => [
        'motor_activo' => 'openai',

        'parametros' => [
            'openai' => [
                'archivo' => 'generacion/motores/openai.php',
                'model' => 'gpt-5.4',
                'reasoning_effort' => 'low',
                'max_output_tokens' => 8000,
                'max_prompt_bytes_soft' => 102400,
                'max_prompt_bytes_hard' => 307200,
                'max_attempts' => 3,
                'retry_delays' => [0, 2, 5],
                'timeouts' => [
                    'connect' => 10,
                    'total' => 120,
                ],
                'snapshot' => false,
            ],
            'claude' => [
                'archivo' => 'generacion/motores/claude.php',
                'model' => 'claude-sonnet-4-6',
                'max_output_tokens' => 1500,
                'max_prompt_bytes_soft' => 102400,
                'max_prompt_bytes_hard' => 307200,
                'temperature' => 0.1,
                'max_attempts' => 3,
                'retry_delays' => [0, 2, 5],
                'timeouts' => [
                    'connect' => 10,
                    'total' => 60,
                ],
                'snapshot' => false,
            ],
            'grok' => [
                'archivo' => 'generacion/motores/grok.php',
                'model' => 'grok-4.3',
                'max_output_tokens' => 1500,
                'max_prompt_bytes_soft' => 102400,
                'max_prompt_bytes_hard' => 307200,
                'temperature' => 0.1,
                'max_attempts' => 3,
                'retry_delays' => [0, 2, 5],
                'timeouts' => [
                    'connect' => 10,
                    'total' => 60,
                ],
                'snapshot' => false,
            ],
        ],
    ],

    'interpretacion' => [
        'motor_activo' => 'openai',
        'parametros' => [
            'openai' => [
                'archivo' => 'interpretacion/motores/openai.php',
                'model' => 'gpt-5-mini',
                'reasoning_effort' => 'low',
                'max_output_tokens' => 6000,
                'max_prompt_bytes_hard' => 307200,
                'max_attempts' => 3,
                'retry_delays' => [0, 2, 5],
                'timeouts' => [
                    'connect' => 10,
                    'total' => 60,
                ],
                'pricing' => [
                    'input_1m' => 0.25,
                    'cached_input_1m' => 0.025,
                    'output_1m' => 2.00,
                ],
                'version_prompt' => '1',
            ],
        ],
    ],

    'revision' => [
        'motor_activo' => 'grok',
        'parametros' => [
            'grok' => [
                'archivo' => 'revision/motores/grok.php',
                'model' => 'grok-4.3',
                'max_tokens' => 6000,
                'temperature' => 0.1,
                'response_format' => [
                    'type' => 'json_object',
                ],
                'timeouts' => [
                    'connect' => 10,
                    'total' => 120,
                ],
            ],
        ],
    ],
];
