
## Secure Laravel Logger for AWS CloudWatch

### Breaking Change for Version dev-master

This documentation explains how to use the PHP package with Laravel to send logs to AWS CloudWatch using a security policy that doesn't require full access to CloudWatch.

### Installation

You can install it via Composer:
```bash
composer require diephp/laravel-cloudwatch-logs
```

Or manually add this to your `composer.json`:
```json
{
    "require": {
        "diephp/laravel-cloudwatch-logs": "^1.0.0"
    }
}
```

### Usage in Laravel

You can use this package with Laravel's default `\Log` class. Example usage:

```php
\Log::error('Service error', ['message' => 'Message details', 'user_id' => \Auth()?->user_id]);
```

```php
\Log::debug("Check status", [
    "status"  => "ok",
    "ver"     => app()->version(),
    "env"     => env("APP_ENV"),
    "api_url" => env("APP_URL"),
]);
```

### AWS Policy Configuration

Create an IAM role -> Users -> appName or select an existing one.

Set the Permissions policies: (This example provides full access for test/dev environments)

Log group and log stream will be created automatically (not recommended for production)
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "CloudWatchLogsFullAccess",
            "Effect": "Allow",
            "Action": [
                "logs:CreateLogGroup",
                "logs:CreateLogStream",
                "logs:PutLogEvents",
                "logs:DescribeLogGroups",
                "logs:DescribeLogStreams"
            ],
            "Resource": "*"
        }
    ]
}
```

Recommended configuration policies (more secure):

You must create the log group and log stream manually and set in config `'createGroup' => false,`
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "logs:PutLogEvents",
                "logs:DescribeLogGroups",
                "logs:DescribeLogStreams"
            ],
            "Resource": "*"
        }
    ]
}
```

### Laravel Configuration

Open `config/logging.php` and find the `channels` array, then add the `cloudwatch` key with minimal configuration:

```php
'channels' => [
    ...
    'cloudwatch' => [
        'driver' => 'custom',
        'via' => \DiePHP\LaravelCloudWatchLog\Logger::class,
        'region' => env('AWS_REGION', 'eu-west-1'),
        'credentials' => [
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],
    ...    
]
```

For a more detailed configuration, you might want the following:

```php
'channels' => [
    ...
    'cloudwatch' => [
        'driver'      => 'custom',
        'region'      => env('AWS_REGION', 'eu-west-1'),
        'credentials' => [
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
        'stream_name' => env('CLOUDWATCH_LOG_STREAM', 'general'),
        'retention'   => env('CLOUDWATCH_LOG_RETENTION_DAYS', 31),
        'group_name'  => env('CLOUDWATCH_LOG_GROUP_NAME', env('AWS_SDK_LOG_GROUP_PREFIX', '')."general"),
        'version'     => env('CLOUDWATCH_LOG_VERSION', 'latest'),
        'formatter'   => \Monolog\Formatter\JsonFormatter::class,
        'batch_size'  => env('CLOUDWATCH_LOG_BATCH_SIZE', 10000), // max buffer size to send in one batch
        'level'       => env('LOG_LEVEL', 'debug'),
        'createGroup' => true, // This is related to the AWS policy you choose.
        'bubble'      => true, // Whether the messages that are handled can bubble up the stack or not
        'extra'       => [
            'env'     => env('APP_ENV'),
            'php'     => PHP_VERSION,
            'laravel' => app()->version(),
        ],
        'tags'        => ['tag1', 'tag2'],
        'via'         => \DiePHP\LaravelCloudWatchLog\Logger::class,
    ],
    ...    
]
```

If you use AWS infrastructure for deployment, you can remove the `credentials` section from the config because AWS containers already have credentials for aws-sdk.

Then, you should set the `LOG_CHANNEL` in your environment variables to `cloudwatch`.

Keep in mind that you should replace the `env` values with the actual ones you plan to use.
