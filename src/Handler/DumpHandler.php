<?php
declare(strict_types=1);

namespace Tg\FiberServer\Handler;

use Tg\FiberServer\Component\HttpCore\HttpStatus;
use Tg\FiberServer\Component\HttpCore\Request;
use Tg\FiberServer\Component\HttpCore\Response;
use Tg\FiberServer\Component\HttpCore\Memory\DumpStorage;

class DumpHandler extends AbstractHandler {

    public function getRoutePrefix(): string {
        return "/_dump";
    }

    protected function doInvoke(Request $request): Response {
        if ($request->getMethod() === 'POST' && $request->getPath() === '/_dump') {
            $content = $request->getContent();
            if ($content !== '') {
                DumpStorage::add($content);
            }
            return new Response('Dump added', HttpStatus::OK);
        }

        if ($request->getPath() === '/_dump/clear') {
            DumpStorage::clear();
            
            $response = new Response('', HttpStatus::SeeOther);
            $response->setHeader('Location', '/_dump');
            return $response;
        }

        $dumps = DumpStorage::getAll();
        
        $body = "<html><head><title>FiberServer Dumps</title>
        <style>
            body { background: #18171B; color: #aaa; font-family: sans-serif; padding: 2em; }
            .dump-entry { border-bottom: 1px solid #333; padding: 1em 0; margin-bottom: 1em; }
            .dump-time { font-size: 0.8em; color: #555; margin-bottom: 0.5em; }
            .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #444; padding-bottom: 1em; margin-bottom: 1em;}
            .btn-clear { color: #f44; text-decoration: none; border: 1px solid #f44; padding: 0.2em 0.5em; border-radius: 3px; font-size: 0.8em; }
            .btn-clear:hover { background: #f44; color: #fff; }
        </style></head>
        <body>
            <div class='header'>
                <h1>Stacked Dumps</h1>
                <a href='/_dump/clear' class='btn-clear'>Clear All</a>
            </div>";
        
        if (empty($dumps)) {
            $body .= "<p>No dumps collected yet. Try calling dump() in your code.</p>";
        } else {
            foreach ($dumps as $dump) {
                $body .= "<div class='dump-entry'>
                            <div class='dump-time'>{$dump['time']}</div>
                            <div class='dump-content'>{$dump['content']}</div>
                          </div>";
            }
        }
        
        $body .= "</body></html>";
        
        $response = new Response($body);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        return $response;
    }
}
