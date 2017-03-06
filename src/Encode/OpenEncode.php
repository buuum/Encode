<?php

namespace Buuum\Encoding;

use Buuum\Encoding\Exception\DelayException;
use Buuum\Encoding\Exception\ExpiresException;

class OpenEncode
{

    /**
     * @var
     */
    public static $key;

    /**
     * @var string
     */
    private static $method = 'AES-256-CBC';

    /**
     * @param array $data
     * @param array $head
     * @param boolean $response_hash_fixed
     * @return string
     */
    public static function encode(array $data, array $head = [], $response_hash_fixed = false)
    {
        $header = [
            'expires' => 0,
            'delay'   => 0
        ];

        $header = array_merge($header, $head);

        if ($header['expires'] > 0) {
            $header['expires'] += time();
        }
        if ($header['delay'] > 0) {
            $header['delay'] += time();
        }

        $segments = [];
        $segments[] = json_encode($header);
        $segments[] = json_encode($data);

        return self::sign(implode('.', $segments), $response_hash_fixed);
    }

    /**
     * @param $data
     * @return mixed
     * @throws DelayException
     * @throws ExpiresException
     */
    public static function decode($data)
    {
        $encrypted = self::base64_url_decode($data);
        list($data, $iv) = explode(':', $encrypted);

        $decrypted = openssl_decrypt($data, self::$method, self::$key, 0, $iv);
        list($headers, $data) = explode('.', json_decode($decrypted), 2);
        $headers = json_decode($headers, true);

        if ($headers['expires'] > 0 && $headers['expires'] < time()) {
            $time = date(\DateTime::ISO8601, $headers['expires']);
            throw new ExpiresException('This token expired on ' . $time, $headers['expires']);
        }
        if ($headers['delay'] > 0 && $headers['delay'] > time()) {
            $time = date(\DateTime::ISO8601, $headers['delay']);
            throw new DelayException('Cannot handle token prior to ' . $time, $headers['delay']);
        }

        return json_decode($data, true);
    }

    /**
     * @param $method
     * @throws \Exception
     */
    public static function setMethod($method)
    {
        if (!in_array($method, openssl_get_cipher_methods())) {
            throw new \Exception('Method not allowed');
        }

        self::$method = $method;
    }

    /**
     * @param $data
     * @param $response_hash_fixed
     * @return string
     */
    private static function sign($data, $response_hash_fixed)
    {
        $iv = self::getIv($response_hash_fixed);
        $encrypted = openssl_encrypt(json_encode($data), self::$method, self::$key, 0, $iv);
        return self::base64_url_encode($encrypted . ':' . $iv);
    }

    /**
     * @param $response_hash_fixed
     * @return string
     */
    private static function getIv($response_hash_fixed)
    {
        $size = openssl_cipher_iv_length(self::$method);
        $key = (!$response_hash_fixed) ? time() : self::$key;

        return substr(hash('sha256', $key), 0, $size);
    }

    /**
     *
     * @param $input
     * @return string
     *
     */
    private static function base64_url_encode($input)
    {
        return strtr(base64_encode($input), '+/', '-_');
    }

    /**
     *
     * @param $input
     * @return string
     *
     */
    private static function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }

}