<?php

return [
    'default' => 'comments',
    'configs' => [
        'comments' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'a[href|title],code,i,strong',
            'HTML.ForbiddenElements' => 'script,style,iframe,object,embed',
            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
            ],
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],
    ],
];
