<?php
declare(strict_types=1);

namespace Tg\FiberServer\Handler;

use Ds\Map;
use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Encoding\EncoderInterface;
use Tg\FiberServer\Map\BaseMap;

abstract class AbstractHandler implements HandlerInterface {

    protected BaseMap $encoderMap;

    public function __construct()
    {
        $this->encoderMap = new BaseMap();
    }

    public function addEncoder(EncoderInterface $encoder): static {
        $this->encoderMap->put($encoder::getAlgo(), $encoder);

        return $this;
    }

    public static function getPriority(): int
    {
        return 1;
    }

    public function getRoutePattern(): ?string
    {
        return null;
    }

    static function hasOutput(): bool
    {
        return true;
    }

    final public function __invoke(Request $request): Response
    {

        $response = $this->doInvoke($request);

        $encHeader = $request->headers->get('Accept-Encoding', '');

        $encArray = \explode(',', \str_replace(' ', '', $encHeader));

        $encodings = new Map(\array_combine($encArray, $encArray));

        $validEncodings = $this->encoderMap->intersect($encodings);
        if(!$validEncodings->isEmpty()) {
            foreach($validEncodings as $encoder) {
                $response->addEncoder($encoder);
            }
        }

        return $response;
    }

    abstract protected function doInvoke(Request $request): Response;
}