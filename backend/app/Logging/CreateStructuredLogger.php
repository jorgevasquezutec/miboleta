<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\WebProcessor;

class CreateStructuredLogger
{
    /**
     * Create a custom Monolog instance for structured JSON logging.
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('miboleta');

        // Determine log level
        $level = Level::fromName($config['level'] ?? 'debug');

        // Create handler based on config
        if (isset($config['path'])) {
            // File handler with rotation
            $handler = new RotatingFileHandler(
                $config['path'],
                $config['days'] ?? 14,
                $level
            );
        } else {
            // Stdout for Docker/Kubernetes
            $handler = new StreamHandler('php://stdout', $level);
        }

        // JSON formatter
        $formatter = new JsonFormatter(
            JsonFormatter::BATCH_MODE_JSON,
            true // appendNewline
        );
        $handler->setFormatter($formatter);

        $logger->pushHandler($handler);

        // Add processors for context
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        $logger->pushProcessor(new ContextProcessor());

        return $logger;
    }
}
