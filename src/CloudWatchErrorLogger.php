<?php
// First, let's install the AWS SDK for PHP using Composer
// Run the following command in your project directory:
// composer require aws/aws-sdk-php
namespace Elleryleung\CustomCloudwatchLogger;

require 'vendor/autoload.php';

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Exception\AwsException;
use Aws\Command as AwsCommand;

class CloudWatchErrorLogger
{
    private $cloudWatchLogsClient;

    private $logGroupName = 'PHPApplicationErrors';
    private $logStreamName = 'ErrorLogStream';

    public function __construct($awsConfig, $customConfig = null)
    {
        $this->cloudWatchLogsClient = new CloudWatchLogsClient($awsConfig);

        $this->logGroupName = $customConfig['logGroupName'] ?? $this->logGroupName;
        $this->logStreamName = $customConfig['logStreamName'] ?? $this->logStreamName;

        $this->createLogGroupIfNotExists();
        $this->createLogStreamIfNotExists();
    }

    private function createLogGroupIfNotExists() {
        $logGroupName = $this->logGroupName;

        try {
            // Check if log group exists, if not, create it
            $result = $this->cloudWatchLogsClient->describeLogGroups([
                'logGroupNamePrefix' => $logGroupName
            ]);

            $logGroup = $result->get("logGroups");

            if (!is_array($logGroup) || empty($logGroup)) {
                throw new AwsException('Log group does not exist', new AwsCommand('describeLogGroups', []));
            }
        } catch (AwsException $e) {
            // Log the error message
            error_log( var_export( [ __METHOD__ => $e->getMessage()], true ) );

            // You can also log the error message to a file or external logging service
            // Log group does not exist, create it
            $this->cloudWatchLogsClient->createLogGroup([
                'logGroupName' => $logGroupName
            ]);
        }
    }

    private function createLogStreamIfNotExists() {
        $logStreamName = $this->logStreamName;

        try {
            // Check if log stream exists, if not, create it
            $result = $this->cloudWatchLogsClient->describeLogStreams([
                'logGroupName' => $this->logGroupName,
                'logStreamNamePrefix' => $logStreamName
            ]);

            $logStream = $result->get("logStreams");

            if (!is_array($logStream) || empty($logStream)) {
                throw new AwsException('Log stream does not exist', new AwsCommand('describeLogStreams', []));
            }
        } catch (AwsException $e) {
            // Log the error message
            error_log( var_export( [ __METHOD__ => $e->getMessage()], true ) );

            // Log stream does not exist, create it
            $this->cloudWatchLogsClient->createLogStream([
                'logGroupName' => $this->logGroupName,
                'logStreamName' => $logStreamName
            ]);
        }
    }

    public function logErrorToCloudWatch($errorData)
    {
        $logGroupName = $this->logGroupName;
        $logStreamName = $this->logStreamName;

        $errorString = $this->formatErrorData($errorData);

        try {
            $result = $this->cloudWatchLogsClient->putLogEvents([
                'logGroupName' => $logGroupName,
                'logStreamName' => $logStreamName,
                'logEvents' => [
                    [
                        'message' => $errorString,
                        'timestamp' => round(microtime(true) * 1000)
                    ],
                ],
            ]);
            // Optionally, you can handle the result here
        } catch (AwsException $e) {
            // Handle the exception or log it to a local log file
            error_log( var_export( [ __METHOD__ => $e->getMessage()], true ) );
        }
    }

    private function formatErrorData($errorData)
    {
        if (is_string($errorData)) {
            return $errorData;
        } elseif (is_array($errorData) || is_object($errorData)) {
            return json_encode($errorData);
        } else {
            return 'Unsupported error data type';
        }
    }
}