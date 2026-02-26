<?php
declare(strict_types=1);

namespace Tg\FiberServer\Bridge\Listener;

use Closure;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Stopwatch\Section;
use Symfony\Component\Stopwatch\Stopwatch;

class ServerTimingListener
{
    private $stopwatch;

    public function __construct(Stopwatch $stopwatch) {
        $this->stopwatch = $stopwatch;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $serverStopwatch = $request->attributes->get('_server_stopwatch');
        $token = $request->attributes->get('_stopwatch_token');

        if (!($serverStopwatch instanceof Stopwatch)) return;

        // 1. Extract events from the server-side stopwatch (recorded in FiberServer)
        $serverEvents = Closure::bind(function () {
            /** @var Stopwatch $this */
            $rootSection = $this->sections['__root__'] ?? null;
            if (!$rootSection) {
                return [];
            }

            return Closure::bind(function() {
                /** @var \Symfony\Component\Stopwatch\Section $this */
                return $this->events;
            }, $rootSection, \Symfony\Component\Stopwatch\Section::class)();
        }, $serverStopwatch, Stopwatch::class)();

        if (empty($serverEvents)) return;

        // 2. Merge these events into the local stopwatch (used by the Profiler)
        $merger = Closure::bind(function (array $serverEvents, ?string $token) {
            /** @var Stopwatch $this */
            
            // If the token is present but the section doesn't exist, we optionally create it 
            // or merge with ROOT. The Profiler usually creates the token section itself.
            $targetSection = null;
            if ($token) {
                if (isset($this->sections[$token])) {
                    $targetSection = $this->sections[$token];
                } else {
                    // Pre-create the section for the token if it's missing
                    $targetSection = new \Symfony\Component\Stopwatch\Section(null, $this->morePrecision);
                    $targetSection->setId($token);
                    $this->sections[$token] = $targetSection;
                }
            }

            if (!$targetSection) {
                $targetSection = $this->sections['__root__'] ?? null;
            }

            if (!$targetSection) {
                return;
            }

            // Inject the events into the target section
            Closure::bind(function(array $events) {
                /** @var \Symfony\Component\Stopwatch\Section $this */
                $this->events = array_merge($events, $this->events);
            }, $targetSection, \Symfony\Component\Stopwatch\Section::class)($serverEvents);

        }, $this->stopwatch, Stopwatch::class);

        $merger($serverEvents, $token);

        // dump($this->stopwatch);
    }
}