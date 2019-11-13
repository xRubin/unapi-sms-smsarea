<?php
namespace unapi\sms\smsarea;

use GuzzleHttp\Client;

class SmsareaClient extends Client
{
    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (!array_key_exists('base_uri', $config))
            $config['base_uri'] = 'http://sms-area.org/';

        if (!array_key_exists('delay', $config))
            $config['delay'] = 2000;

        parent::__construct($config);
    }
}