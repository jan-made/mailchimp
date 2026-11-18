<?php
declare(strict_types=1);

namespace Trejjam\MailChimp;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Schematic;
use Trejjam\MailChimp\Exception\RequestException;

final class Request
{
    public const VERSION = '3.0';
    public const API_USER = 'apikey';

    public function __construct(
        private readonly Client $httpClient,
        private readonly string $apiUrl,
        private readonly string $apiKey
    ) {
    }

    /**
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function get(string $endpointPath) : array
    {
        return $this->makeRequest(__FUNCTION__, $endpointPath);
    }

    /**
     * @template T
     * @param class-string<T> $endpointClass
     * @return T
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function getTyped(string $endpointPath, string $endpointClass, ?PaginationOption $paginationOption = null)
    {
        return $this->makeTypedRequest('get', $endpointPath, $endpointClass, [], $paginationOption);
    }

    /**
     * @template T
     * @param class-string<T> $endpointClass
     * @return T
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function putTyped(string $endpointPath, array $body, string $endpointClass)
    {
        return $this->makeTypedRequest('put', $endpointPath, $endpointClass, [
            RequestOptions::BODY => Json::encode($body),
        ]);
    }

    /**
     * @template T
     * @param class-string<T> $endpointClass
     * @return T
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function patchTyped(string $endpointPath, array $body, string $endpointClass)
    {
        return $this->makeTypedRequest('patch', $endpointPath, $endpointClass, [
            RequestOptions::BODY => Json::encode($body),
        ]);
    }

    /**
     * @return array
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function post(string $endpointPath, array $body)
    {
        return $this->makeRequest(__FUNCTION__, $endpointPath, [
            RequestOptions::BODY => Json::encode($body),
        ]);
    }

    /**
     * @template T
     * @param class-string<T> $endpointClass
     * @return T
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function postTyped(string $endpointPath, array $body, string $endpointClass)
    {
        return $this->makeTypedRequest('post', $endpointPath, $endpointClass, [
            RequestOptions::BODY => Json::encode($body),
        ]);
    }

    /**
     * @return array
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function delete(string $endpointPath)
    {
        return $this->makeRequest(__FUNCTION__, $endpointPath);
    }

    /**
     * @template T
     * @param class-string<T> $endpointClass
     * @return T
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    public function deleteTyped(string $endpointPath, string $endpointClass)
    {
        return $this->makeTypedRequest('delete', $endpointPath, $endpointClass);
    }

    /**
     * @return array
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    private function makeRequest(
        string            $method,
        string            $endpointPath,
        array             $requestOptions = [],
        ?PaginationOption $paginationOption = null
    ) {
        $mergedRequestOptions = array_merge_recursive(
            [
                RequestOptions::AUTH => [self::API_USER, $this->apiKey],
            ],
            $requestOptions
        );

        if ($paginationOption !== null) {
            $endpointPath .= "?offset={$paginationOption->getOffset()}&count={$paginationOption->getCount()}";
        }

        $response = $this->httpClient->request(
            $method,
            $this->apiUrl . $endpointPath,
            $mergedRequestOptions
        );

        if ($response->getStatusCode() !== 200) {
            throw (new RequestException(
                $response->getReasonPhrase(),
                $response->getStatusCode()
            ))->setResponse($response);
        }

        return (array)Json::decode($response->getBody()->getContents(), forceArrays: true);
    }

    /**
     * @template T
     * @param 'get'|'post'|'put'|'patch'|'delete' $method
     * @param class-string<T> $endpointClass
     * @return T
     * @throws JsonException
     * @throws RequestException
     * @throws GuzzleException
     */
    private function makeTypedRequest(
        string            $method,
        string            $endpointPath,
        string            $endpointClass,
        array             $requestOptions = [],
        ?PaginationOption $paginationOption = null
    ) {
        $returnArray = $this->makeRequest($method, $endpointPath, $requestOptions, $paginationOption);

        if (is_a($endpointClass, Schematic\Entry::class, true)) {
            return new $endpointClass($returnArray, Entity\SchematicEntries::class);
        }

        return new $endpointClass($returnArray);
    }
}
