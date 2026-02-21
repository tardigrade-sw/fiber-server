<?php
declare(strict_types=1);

namespace Tg\FiberServer\SslBridge;

class CertGenerator {

    public function __construct(
        private string $certPath,
        private string $keyPath,
        private string $commonName = 'localhost'
    ) {
        $certDir = dirname($certPath);
        $keyDir = dirname($keyPath);
        if(!\is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }
        if(!\is_dir($keyDir)) {
            mkdir($keyDir, 0755, true);
        }
    }

    public function ensureCertificates(): void {
        if (\file_exists($this->certPath) && \file_exists($this->keyPath)) {
            return;
        }

        

        $dn = ["commonName" => $this->commonName];
        $privkey = \openssl_pkey_new([
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = \openssl_csr_new($dn, $privkey, ['digest_alg' => 'sha256']);
        $x509 = \openssl_csr_sign($csr, null, $privkey, $days = 365, ['digest_alg' => 'sha256']);

        \openssl_x509_export($x509, $certString);
        \openssl_pkey_export($privkey, $keyString);

        \file_put_contents($this->certPath, $certString);
        \file_put_contents($this->keyPath, $keyString);
        
        \chmod($this->keyPath, 0600);
    }

    public function getCertPath(): string {
        return $this->certPath;
    }

    public function getKeyPath(): string {
        return $this->keyPath;
    }
}