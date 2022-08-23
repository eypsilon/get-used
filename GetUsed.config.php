<?php

return [

    'info' => [
        'lib' => 'Many\Dev\Used',
    ],

    'config' => [
        'args' => [
            'file',
            'return',
            'comment_out',
        ],
        'options' => [
            'info' => '-i',
            'help' => '-h',
            'config' => '-c',
        ],
    ],

    'help' => [
        'args' => [
            'file' => 'File to get the use keywords for',
            'return' => 'Set return content type',
            'comment_out' => 'Avoid commenting out already defined use keywords found in the Class',
        ],
        'options' => [
            '-i' => 'Get info',
            '-h' => 'Get help',
            '-c' => 'Get Config',
        ],
    ],

];
