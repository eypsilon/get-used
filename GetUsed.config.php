<?php

return [

    'info' => [
        'lib' => 'Many\Dev\Used',
    ],

    'config' => [
        'args' => [
            'project_dir',
            'autoload',
            'include',
            'file',
            'class',
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
            'project_dir' => 'Path to project',
            'autoload' => "Path to './vendor/autoload.php' file",
            'include' => 'Comma separated paths to include additionally files',
            'file' => 'File to get the use keywords for',
            'class' => 'Set a Namespace to select a Class from availables',
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
