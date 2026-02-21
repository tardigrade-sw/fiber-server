<?php
declare(strict_types=1);

namespace Tg\FiberServer\Middleware;

use Fiber;
use RuntimeException;
use Tg\FiberServer\SslBridge\CertGenerator;

class SslMiddleware implements ServerCycleInterface {
    public function __construct(
        private CertGenerator $certGenerator
    ){}

    public function beforeListen(array &$contextOptions, string &$address): void
    {
        $this->certGenerator->ensureCertificates();
        $contextOptions['ssl'] = [
            'local_cert' => $this->certGenerator->getCertPath(),
            'local_pk' => $this->certGenerator->getKeyPath(),
            'allow_self_signed' => true,
            'verify_peer' => false
        ];

        $addrClone = (string)$address;

        $addressRest = \array_pop(\explode("://", $addrClone, 2));

        $address = "ssl://$addressRest";
    }

    public function onConnection($socket): void
    {
        while((
            $result = @\stream_socket_enable_crypto(
                $socket, 
                true, 
                STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER
            )
        ) === 0) {
            Fiber::suspend();
        }

        if($result === false) {
            throw new RuntimeException("TLS handshake failed");
        }
    }
}