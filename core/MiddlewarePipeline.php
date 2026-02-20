<?php

namespace Core;

class MiddlewarePipeline
{
    private Request $request;
    private array   $middleware;

    public function __construct(Request $request, array $middleware)
    {
        $this->request    = $request;
        $this->middleware = $middleware;
    }

    public function run(): void
    {
        foreach ($this->middleware as $middlewareClass) {
            if (!class_exists($middlewareClass)) {
                Response::serverError("Middleware {$middlewareClass} not found");
                exit;
            }

            $instance = new $middlewareClass();
            $instance->handle($this->request);
        }
    }
}
