<?php

namespace Buuum\Encoding;

use Buuum\Encoding\Exception\DelayException;
use Buuum\Encoding\Exception\ExpiresException;

class Encode
{

    /**
     * @var
     */
    public static $key;

    /**
     * @var string
     */
    private static $alg = 'GOST';

    /**
     * @var array
     */
    private static $supported_algs = [
        'RIJNDAELE' => [MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB, 32],
        'RIJNDAELC' => [MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC, 32],
        'BLOWFISH'  => [MCRYPT_BLOWFISH, MCRYPT_MODE_ECB, false],
        '3DES'      => [MCRYPT_3DES, MCRYPT_MODE_ECB, 24],
        'GOST'      => [MCRYPT_GOST, MCRYPT_MODE_CBC, 32]
    ];

    /**
     * @param array $data
     * @param array|null $head
     * @return string
     */
    public static function encode(array $data, array $head = null)
    {
        $header = [
            'expires' => 0,
            'delay'   => 0
        ];
        if (isset($head) && is_array($head)) {
            $header = array_merge($header, $head);
        }

        if ($header['expires'] > 0) {
            $header['expires'] += time();
        }
        if ($header['delay'] > 0) {
            $header['delay'] += time();
        }

        $segments = [];
        $segments[] = self::jsonEncode($header);
        $segments[] = self::jsonEncode($data);

        return self::sign(implode('.', $segments));
    }

    /**
     * @param $data
     * @return string
     */
    private static function sign($data)
    {
        list($cipher, $mode, $size) = self::$supported_algs[self::$alg];

        $iv_size = mcrypt_get_iv_size($cipher, $mode);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

        return self::base64_url_encode($iv . mcrypt_encrypt(
                $cipher,
                self::getKey($size), $data,
                $mode,
                $iv
            ));
    }

    /**
     * @param $size
     * @return string
     */
    private static function getKey($size)
    {
        $key = sha1(self::$key);
        if ($size) {
            $key = substr($key, 0, $size);
        }

        return $key;
    }

    /**
     * @param $alg
     */
    public static function setAlgorithm($alg)
    {
        if (empty(self::$supported_algs[$alg])) {
            throw new \DomainException('Algorithm not supported');
        }

        self::$alg = $alg;
    }

    /**
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    static public function decode($data)
    {
        list($cipher, $mode, $size) = self::$supported_algs[self::$alg];

        $data = self::base64_url_decode($data);
        $iv_size = mcrypt_get_iv_size($cipher, $mode);
        $iv_dec = substr($data, 0, $iv_size);
        $data = substr($data, $iv_size);


        $datainfo = trim(mcrypt_decrypt(
            $cipher,
            self::getKey($size),
            $data,
            $mode,
            $iv_dec
        ));
        $segments = explode('.', $datainfo, 2);

        $info = self::jsonDecode($segments[0]);

        if ($info['expires'] > 0 && $info['expires'] < time()) {
            throw new ExpiresException(
                'This token expired on ' . date(\DateTime::ISO8601, $info['expires']),
                $info['expires']
            );
        }
        if ($info['delay'] > 0 && $info['delay'] > time()) {
            throw new DelayException(
                'Cannot handle token prior to ' . date(\DateTime::ISO8601, $info['delay']),
                $info['delay']
            );
        }

        return self::jsonDecode($segments[1]);
    }

    /**
     * @param array $input
     * @return string
     */
    private static function jsonEncode(array $input)
    {
        $json = json_encode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            self::handleJsonError($errno);
        } elseif ($json === 'null' && $input !== null) {
            throw new \DomainException('Null result with non-null input');
        }
        return $json;
    }

    /**
     * @param $input
     * @return mixed
     */
    private static function jsonDecode($input)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
            /** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
             * to specify that large ints (like Steam Transaction IDs) should be treated as
             * strings, rather than the PHP default behaviour of converting them to floats.
             */
            $obj = json_decode($input, true, 512, JSON_BIGINT_AS_STRING);
        } else {
            /** Not all servers will support that, however, so for older versions we must
             * manually detect large ints in the JSON string and quote them (thus converting
             *them to strings) before decoding, hence the preg_replace() call.
             */
            $max_int_length = strlen((string)PHP_INT_MAX) - 1;
            $json_without_bigints = preg_replace('/:\s*(-?\d{' . $max_int_length . ',})/', ': "$1"', $input);
            $obj = json_decode($json_without_bigints, true);
        }
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            self::handleJsonError($errno);
        } elseif ($obj === null && $input !== 'null') {
            throw new \DomainException('Null result with non-null input');
        }
        return $obj;
    }


    /**
     * @param $errno
     */
    private static function handleJsonError($errno)
    {
        $messages = array(
            JSON_ERROR_DEPTH     => 'Maximum stack depth exceeded',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX    => 'Syntax error, malformed JSON'
        );
        throw new \DomainException(
            isset($messages[$errno])
                ? $messages[$errno]
                : 'Unknown JSON error: ' . $errno
        );
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