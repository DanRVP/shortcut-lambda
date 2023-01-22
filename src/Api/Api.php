<?php

namespace Shortcut\Api;

use Exception;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Base API class
 */
class Api
{
    protected const SHORTCUT_BASE_URL = 'https://api.app.shortcut.com/api/v3/';

    /**
     * Guzzle Http Client to make requests
     */
    protected Client $client;

    /**
     * Array of errors.
     */
    protected array $errors = [];

    public function __construct(protected string $api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Get a scoped client. Creates a new one if one does not already exist.
     */
    protected function getClient(): Client
    {
        if (empty($this->client)) {
            $this->client = new Client([
                'base_uri' => self::SHORTCUT_BASE_URL,
                'timeout' => 4,
                'verify' => false,
                'http_errors' => true,
                'headers' => [
                    'Shortcut-Token' => $this->api_key,
                    'Content-Type' => 'application/json',
                ]
            ]);
        }

        return $this->client;
    }

    protected function makeRequest(callable $function): array|object|bool
    {
        try {
            return $this->processResponse(call_user_func($function));
        } catch (Exception $e) {
            return $this->processExceptions($e);
        }
    }

    /**
     * Process a successful Guzzle response.
     */
    protected function processResponse(ResponseInterface $response): array|bool|object
    {
        if ($response->getStatusCode() == 200) {
            if ($body = $response->getBody()) {
                $decoded = json_decode($body->getContents());
                if (!$decoded) {
                    $this->addError('Unable to parse JSON response.');
                } else {
                    return $decoded;
                }
            } else {
                $this->addError('No response body to read');
            }
        }

        return false;
    }

    /**
     * Add a message to errors array and return false to indicate failure.
     */
    protected function processExceptions($exception):bool
    {
        $this->addError($exception->getMessage());
        return false;
    }


    /**
     * Add a message to the error messages array.
     */
    protected function addError(string $message):void
    {
        $this->errors[] = $message;
    }

    /**
     * Get the full array of errors
     */
    public function getErrors():array
    {
        return $this->errors;
    }

    public function getLastError():string
    {
        return end($this->errors);
    }
}
