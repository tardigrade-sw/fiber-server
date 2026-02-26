<?php
declare(strict_types=1);

namespace Tg\FiberServer\Component\HttpCore;

use Tg\FiberServer\Encoding\EncoderInterface;
use Tg\FiberServer\Map\HttpHeadersMap;

class Response {

    protected string $content;
    protected int $status;
    protected HttpHeadersMap $headers; 


    public function __construct(string $content, int $status = HttpStatus::OK, ?HttpHeadersMap $headers = null)
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = ($headers ?? new HttpHeadersMap())->withDefault([
            'Content-Type' => 'text/html',
            'Date' => \gmdate('D, d M Y H:i:s') . ' GMT'
        ]);
    }

    public static function createEmpty(): Response {
        return new Response('');
    }

    public static function createNotFound(): Response {
        return new Response('', HttpStatus::NotFound);
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function dumpTcp(): string {
        $this->headers->put('Content-Length', \strlen($this->content));

        // Ensure we explicitly state connection status if it's set
        if (!$this->headers->has('Connection')) {
             $this->headers->put('Connection', 'keep-alive');
        }

        $output = "HTTP/1.1 " . $this->status . " " . HttpStatus::getMessage($this->status) . "\r\n";

        foreach ($this->headers as $key => $value) {
            if (\is_array($value)) {
                foreach($value as $v) {
                    $output .= "$key: $v\r\n";
                }
            } else {
                $output .= "$key: $value\r\n";
            }
        }

        if(!empty($this->content)) {

            return $output . "\r\n" . $this->content;
        } else {
            return $output . "\r\n";
        }

    }

    public function setHeader(string $key, mixed $value): void {
        $this->headers->put($key, $value);
    }

    public function getHeader(string $key, mixed $default): mixed {
        return $this->headers->get($key, $default);
    }

    public function addEncoder(EncoderInterface $encoder, int $level = -1) {
        $this->content = $encoder->encode($this->content, $level);

        $currentEncoding = $this->getHeader('Content-Encoding', '');

        $this->setHeader('Content-Encoding', empty($currentEncoding) ? $encoder::getAlgo() : $currentEncoding . ', ' . $encoder::getAlgo());
    }

    public function __clone() {
        $this->headers = clone $this->headers;
    }
}