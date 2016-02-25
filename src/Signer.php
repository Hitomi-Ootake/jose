<?php

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2016 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Jose;

use Jose\Algorithm\JWAManagerInterface;
use Jose\Algorithm\SignatureAlgorithmInterface;
use Jose\Behaviour\HasJWAManager;
use Jose\Behaviour\HasKeyChecker;
use Jose\Behaviour\HasLogger;
use Jose\Object\JWKInterface;
use Jose\Object\JWSInterface;
use Jose\Object\Signature;
use Psr\Log\LoggerInterface;

/**
 */
final class Signer implements SignerInterface
{
    use HasKeyChecker;
    use HasJWAManager;
    use HasLogger;

    /**
     * Signer constructor.
     *
     * @param \Jose\Algorithm\JWAManagerInterface $jwa_manager
     * @param \Psr\Log\LoggerInterface|null       $logger
     */
    public function __construct(JWAManagerInterface $jwa_manager,
                                LoggerInterface $logger = null
    ) {
        $this->setJWAManager($jwa_manager);

        if (null !== $logger) {
            $this->setLogger($logger);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addSignatureWithDetachedPayload(JWSInterface &$jws, JWKInterface $key, $detached_payload, array $protected_headers = [], array $headers = [])
    {
        $signature = new Signature();
        if (!empty($protected_headers)) {
            $signature = $signature->withProtectedHeaders($protected_headers);
        }
        if (!empty($headers)) {
            $signature = $signature->withHeaders($headers);
        }

        $signature_algorithm = $this->getSignatureAlgorithm($signature->getAllHeaders(), $key);
        $value = $signature_algorithm->sign($key, $signature->getEncodedProtectedHeaders().'.'.$detached_payload);

        $signature = $signature->withSignature($value);

        $jws = $jws->addSignature($signature);
    }

    /**
     * {@inheritdoc}
     */
    public function addSignature(JWSInterface &$jws, JWKInterface $key, array $protected_headers = [], array $headers = [])
    {
        if (null === $jws->getEncodedPayload()) {
            throw new \InvalidArgumentException('No payload.');
        }
        $this->checkKeyUsage($key, 'signature');

        $this->addSignatureWithDetachedPayload($jws, $key, $jws->getEncodedPayload(), $protected_headers, $headers);
    }

    /**
     * @param array                     $complete_header The complete header
     * @param \Jose\Object\JWKInterface $key
     *
     * @return \Jose\Algorithm\SignatureAlgorithmInterface
     */
    private function getSignatureAlgorithm(array $complete_header, JWKInterface $key)
    {
        if (!array_key_exists('alg', $complete_header)) {
            throw new \InvalidArgumentException('No "alg" parameter set in the header.');
        }
        if ($key->has('alg') && $key->get('alg') !== $complete_header['alg']) {
            throw new \InvalidArgumentException(sprintf('The algorithm "%s" is allowed with this key.', $complete_header['alg']));
        }

        $signature_algorithm = $this->getJWAManager()->getAlgorithm($complete_header['alg']);
        if (!$signature_algorithm instanceof SignatureAlgorithmInterface) {
            throw new \InvalidArgumentException(sprintf('The algorithm "%s" is not supported.', $complete_header['alg']));
        }

        return $signature_algorithm;
    }
}
