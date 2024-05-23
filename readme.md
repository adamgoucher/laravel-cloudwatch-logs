## Secure Logger for Aws Cloud Watch

### Breaking Change for version dev-master

Here is the documentation for the PHP vendor to work with Laravel for sending logs to AWS CloudWatch use security policy without full access to CloudWatch.
### Installation

`composer require diephp/laravel-cloudwatch-logs`

or manual add to composer.json:
`"diephp/laravel-cloudwatch-logs": "dev-master"`
```json
...
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/diephp/laravel-cloudwatch-logs"
    }
],
...
```

### Example use in Laravel

You can use laravel's default `\Log` class to use this

`\Log::error('Service error', ['message' => 'Message details', 'user_id' => \Auth()?->user_id]);`

### CConfiguration AWS policy

Create an IAM role -> Users -> appName or select exists

Set the Permissions policies: (This example recommends for full access for test\dev env)

Log group and log stream will be created automatically (not recommended to use on production)
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

Recommendation config policies: (more security)

But you must create log group and log stream manually and set in config ` 'createGroup' => false,`
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


### Laravel Config
Open file config/logging.php and find channels array, then add key `cloudwatch` with
minimal configuration:
```
'channels' =>  [
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

Or Full configuration:
```
'channels' =>  [
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
            'batch_size'  => env('CLOUDWATCH_LOG_BATCH_SIZE', 10000), // max buffer size to send in cloudetach
            'level'       => env('LOG_LEVEL', 'debug'),
            'createGroup' => true, // it related from aws policy wtich you choose
            'bubble' => true, //Whether the messages that are handled can bubble up the stack or not
            'extra' => [
                'env' => env('APP_ENV'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'tags' => ['tag1','tag2'],
            'via'         => \DiePHP\LaravelCloudWatchLog\Logger::class,
        ],
    ...    
]
```

And set the `LOG_CHANNEL` in your environment variable to `cloudwatch`.

