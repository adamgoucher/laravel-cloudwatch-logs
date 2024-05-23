<?php

namespace DiePHP\LaravelCloudWatchLog;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use DiePHP\LaravelCloudWatchLog\Exceptions\ConfigLaravelCloudWatchException;
use DiePHP\LaravelCloudWatchLog\Exceptions\LaravelCloudWatchException;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\LineFormatter;

/**
 * Class Logger
 * The Logger class is responsible for creating and configuring instances of \Monolog\Logger.
 * It uses the AWS CloudWatch service to store and manage log entries.
 */
class Logger
{

    private $app;

    public function __construct($app = null)
    {
        $this->app = $app;
    }

    /**
     * @param array $config
     * @return \Monolog\Logger
     * @throws \DiePHP\LaravelCloudWatchLog\Exceptions\LaravelCloudWatchException
     * @throws \DiePHP\LaravelCloudWatchLog\Exceptions\ConfigLaravelCloudWatchException
     */
    public function __invoke(array $config)
    {
        if ($this->app === null) {
            $this->app = \app();
        }

        if (!$this->app) {
            throw new LaravelCloudWatchException('This vendor available only for Laravel');
        }

        if (empty($config['region'])) {
            throw new ConfigLaravelCloudWatchException('CloudWatch require param: `region`. You must add `cloudwatch` to the channels array in `config/logging.php`');
        }

        $awsCredentials = [
            'region'  => $config['region'],
            'version' => isset($config['version']) ? $config['version'] : 'latest',
        ];

        if (isset($config['credentials']['key'])) {
            $awsCredentials['credentials'] = $config['credentials'];
        }

        $logHandler = new CloudWatch(
            new CloudWatchLogsClient($awsCredentials),
            isset($config['group_name']) ? $config['group_name'] : 'general',
            isset($config['stream_name']) ? $config['stream_name'] : 'default',
            isset($config['retention']) ? \intval($config['retention']) : 14,
            isset($config['batch_size']) ? $config['batch_size'] : 10000,
            (array) isset($config['tags']) ? $config['tags'] : [],
            isset($config['level']) ? $config['level'] : 'debug',
            isset($config['bubble']) ? \boolval($config['bubble']) : true,
            isset($config['createGroup']) ? \boolval($config['createGroup']) : true
        );
        $logger = new \Monolog\Logger(isset($config['name']) ? $config['name'] : 'logger');

        $formatter = $this->resolveFormatter($config);
        $logHandler->setFormatter($formatter);
        $logger->pushHandler($logHandler);

        return $logger;
    }


    /**
     * @param array $configs
     * @return \Monolog\Formatter\LineFormatter
     * @throws \DiePHP\LaravelCloudWatchLog\Exceptions\ConfigLaravelCloudWatchException
     */
    private function resolveFormatter(array $configs)
    {
        if (!isset($configs['formatter'])) {
            return new LineFormatter(
                '%channel%: %level_name%: %message% %context% %extra%',
                null,
                false,
                true
            );
        }

        $formatter = $configs['formatter'];

        if (\is_string($formatter) && \class_exists($formatter)) {
            return $this->app->make($formatter);
        }

        if (\is_callable($formatter)) {
            return $formatter($configs);
        }

        throw new ConfigLaravelCloudWatchException('Formatter is missing for the logs');
    }

}
