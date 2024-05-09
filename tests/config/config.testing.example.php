<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | Flysystem Adapter for GIT configurations
    |--------------------------------------------------------------------------
    |
    | These configurations will be used in all the tests to bootstrap
    | a Client object.
    |
    */
    
    /**
     * Personal access token
     *
     * @see https://docs.spacemade.com/user/profile/personal_access_tokens.html
     */
    'personal-access-token' => 'your-access-token',
    
    /**
     * Project id of your repo
     */
    'project-id'            => 'your-project-id',
    
    /**
     * Branch that should be used
     */
    'branch'                => 'master',
    
    /**
     * Base URL of GIT server you want to use
     */
    'base-url'              => 'https://spacemade.com',
];
