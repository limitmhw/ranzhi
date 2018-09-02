<?php

/**
 * XML Formatted DSA Key Handler
 *
 * While XKMS defines a private key format for RSA it does not do so for DSA. Quoting that standard:
 *
 * "[XKMS] does not specify private key parameters for the DSA signature algorithm since the algorithm only
 *  supports signature modes and so the application of server generated keys and key recovery is of limited
 *  value"
 *
 * PHP version 5
 *
 * @category  Crypt
 * @package   DSA
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

namespace phpseclib\Crypt\DSA\Keys;

use ParagonIE\ConstantTime\Base64;
use phpseclib\Math\BigInteger;

/**
 * XML Formatted DSA Key Handler
 *
 * @package DSA
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
abstract class XML
{
    /**
     * Break a public or private key down into its constituent components
     *
     * @access public
     * @param string $key
     * @param string $password optional
     * @return array
     */
    public static function load($key, $password = '')
    {
        if (!is_string($key)) {
            return false;
        }

        $use_errors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        if (!$dom->loadXML('<xml>' . $key . '</xml>')) {
            return false;
        }
        $xpath = new \DOMXPath($dom);
        $keys = ['p', 'q', 'g', 'y', 'j', 'seed', 'pgencounter'];
        foreach ($keys as $key) {
            // $dom->getElementsByTagName($key) is case-sensitive
            $temp = $xpath->query("//*[translate(local-name(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='$key']");
            if (!$temp->length) {
                continue;
            }
            $value = new BigInteger(Base64::decode($temp->item(0)->nodeValue), 256);
            switch ($key) {
                case 'p': // a prime modulus meeting the [DSS] requirements
                    // Parameters P, Q, and G can be public and common to a group of users. They might be known
                    // from application context. As such, they are optional but P and Q must either both appear
                    // or both be absent
                    $components['p'] = $value;
                    break;
                case 'q': // an integer in the range 2**159 < Q < 2**160 which is a prime divisor of P-1
                    $components['q'] = $value;
                    break;
                case 'g': // an integer with certain properties with respect to P and Q
                    $components['g'] = $value;
                    break;
                case 'y': // G**X mod P (where X is part of the private key and not made public)
                    $components['y'] = $value;
                    // the remaining options do not do anything
                case 'j': // (P - 1) / Q
                    // Parameter J is available for inclusion solely for efficiency as it is calculatable from
                    // P and Q
                case 'seed': // a DSA prime generation seed
                    // Parameters seed and pgenCounter are used in the DSA prime number generation algorithm
                    // specified in [DSS]. As such, they are optional but must either both be present or both
                    // be absent
                case 'pgencounter': // a DSA prime generation counter
            }
        }

        libxml_use_internal_errors($use_errors);

        if (!isset($components['y'])) {
            return false;
        }

        switch (true) {
            case !isset($components['p']):
            case !isset($components['q']):
            case !isset($components['g']):
                return ['y' => $components['y']];
        }

        return $components;
    }

    /**
     * Convert a public key to the appropriate format
     *
     * See https://www.w3.org/TR/xmldsig-core/#sec-DSAKeyValue
     *
     * @access public
     * @param \phpseclib\Math\BigInteger $p
     * @param \phpseclib\Math\BigInteger $q
     * @param \phpseclib\Math\BigInteger $g
     * @param \phpseclib\Math\BigInteger $y
     * @return string
     */
    public static function savePublicKey(BigInteger $p, BigInteger $q, BigInteger $g, BigInteger $y)
    {
        return "<DSAKeyValue>\r\n" .
               '  <P>' . Base64::encode($p->toBytes()) . "</P>\r\n" .
               '  <Q>' . Base64::encode($q->toBytes()) . "</Q>\r\n" .
               '  <G>' . Base64::encode($g->toBytes()) . "</G>\r\n" .
               '  <Y>' . Base64::encode($y->toBytes()) . "</Y>\r\n" .
               '</DSAKeyValue>';
    }
}
