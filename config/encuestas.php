<?php

// TODO: reemplazar estas preguntas con el cuestionario oficial cuando el usuario lo proporcione.
return [
    'preguntas' => [
        [
            'id'   => 'experiencia_general',
            'tipo' => 'escala',
            'texto' => '¿Cómo calificarías tu experiencia general en el evento?',
        ],
        [
            'id'   => 'expectativas_citas',
            'tipo' => 'escala',
            'texto' => '¿Las citas agendadas cumplieron tus expectativas?',
        ],
        [
            'id'   => 'organizacion',
            'tipo' => 'escala',
            'texto' => '¿La organización y logística del evento fue adecuada?',
        ],
        [
            'id'   => 'lo_mejor',
            'tipo' => 'texto',
            'texto' => '¿Qué fue lo que más te gustó del evento?',
        ],
        [
            'id'   => 'mejoras',
            'tipo' => 'texto',
            'texto' => '¿Qué podríamos mejorar para futuros eventos?',
        ],
        [
            'id'   => 'recomendaria',
            'tipo' => 'binario',
            'texto' => '¿Recomendarías participar en futuros eventos de Impúlsate?',
        ],
    ],
];
