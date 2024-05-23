<?php

namespace DiePHP\LaravelCloudWatchLog;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use DiePHP\LaravelCloudWatchLog\Exceptions\ConfigLaravelCloudWatchException;
use DiePHP\LaravelCloudWatchLog\Exceptions\LaravelCloudWatchException;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\LineFormatter;

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
     * @throws \DiePHP\LaravelCloudWatchLog\Exceptions\ConfigLaravelCloudWatchException
     * @throws \Exception
     */
    public function __invoke(array $config)
    {
        if ($this->app === null) {
            $this->app = \app();
        }

        if (!$this->app) {
            throw new LaravelCloudWatchException('This vendor available only for Laravel');
        }

        $loggingConfig = $config;
        $cwClient = new CloudWatchLogsClient($this->getCredentials());

        $streamName = $loggingConfig['stream_name'];
        $retentionDays = $loggingConfig['retention'];
        $groupName = $loggingConfig['group_name'];
        $batchSize = isset($loggingConfig['batch_size']) ? $loggingConfig['batch_size'] : 10000;

        $logHandler = new CloudWatch($cwClient, $groupName, $streamName, $retentionDays, $batchSize);
        $logger = new \Monolog\Logger($loggingConfig['name']);

        $formatter = $this->resolveFormatter($loggingConfig);
        $logHandler->setFormatter($formatter);
        $logger->pushHandler($logHandler);

        return $logger;
    }

    /**
     * This is the way config should be defined in config/logging.php
     * in key cloudwatch.
     * 'cloudwatch' => [
     *      'driver' => 'custom',
     *     'name' => env('CLOUDWATCH_LOG_NAME', ''),
     *     'region' => env('CLOUDWATCH_LOG_REGION', ''),
     *     'credentials' => [
     *         'key' => env('CLOUDWATCH_LOG_KEY', ''),
     *         'secret' => env('CLOUDWATCH_LOG_SECRET', '')
     *     ],
     *     'stream_name' => env('CLOUDWATCH_LOG_STREAM_NAME', 'laravel_app'),
     *     'retention' => env('CLOUDWATCH_LOG_RETENTION_DAYS', 14),
     *     'group_name' => env('CLOUDWATCH_LOG_GROUP_NAME', 'laravel_app'),
     *     'version' => env('CLOUDWATCH_LOG_VERSION', 'latest'),
     *     'via' => \Pagevamp\Logger::class,
     * ]
     * @return array
     * @throws \DiePHP\LaravelCloudWatchLog\Exceptions\ConfigLaravelCloudWatchException
     */
    protected function getCredentials()
    {
        $loggingConfig = $this->app->make('config')->get('logging.channels');

        if (!isset($loggingConfig['cloudwatch'])) {
            throw new ConfigLaravelCloudWatchException('Configuration Missing for Cloudwatch Log');
        }

        $cloudWatchConfigs = $loggingConfig['cloudwatch'];

        if (!isset($cloudWatchConfigs['region'])) {
            throw new ConfigLaravelCloudWatchException('Missing region key-value');
        }

        $awsCredentials = [
            'region'  => $cloudWatchConfigs['region'],
            'version' => $cloudWatchConfigs['version'],
        ];

        if ($cloudWatchConfigs['credentials']['key']) {
            $awsCredentials['credentials'] = $cloudWatchConfigs['credentials'];
        }

        return $awsCredentials;
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

        if (\is_string($formatter) && class_exists($formatter)) {
            return $this->app->make($formatter);
        }

        if (\is_callable($formatter)) {
            return $formatter($configs);
        }

        throw new ConfigLaravelCloudWatchException('Formatter is missing for the logs');
    }

}
