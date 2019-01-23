<?php

return [
    'serviceAccountFile' => './file.json',
    'subject' => 'foo@bar.com',
    'domain' => 'bar.com',
    'users' => [
        'foo@bar.com' => 'organizer',
    ],
    'blacklist' => [
        'Teamdrive Name' => [
            'bar@foo.com'
        ]
    ],
    'whitelist' => [
        'Teamdrive Name' => [
            'bar@foo.com'
        ]
    ],
    'teamDriveNameBegin' => '',
    'thankFionera' => true, //Always add me (fionera) as Reader
    'iam' => [
        'enabled' => false,
        'projectId' => ''
    ]
];