<?php

namespace App\Services\ReddyBot;


use Exception;
use GuzzleHttp\Exception\GuzzleException;

abstract class AbstractReddyBotService
{
    public string $token;


    /**
     * @param $httpMethod
     * @param $method
     * @param array $body
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    protected function sendBotHttpRequest($httpMethod, $method, array $body = [])
    {
        $url = $this->getServiceUrl($method);
        $client = new \GuzzleHttp\Client();
        $response = $client->request($httpMethod, $url, [
            'http_errors' => true,
            'body' => json_encode($body)
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('Not 200 ' . $response->getStatusCode());
        }

        return json_decode($response->getBody()) ?? [];
    }


    public function getServiceUrl($method): string
    {
        return "https://bot.reddy.team/v2{$this->token}/$method";
    }

    public static function createButton($data, $title, $isCommand = false): array
    {
        return [
            'type' => $isCommand ? 'command' : 'action',
            'data' => $data,
            'title' => $title
        ];
    }

    /**
     * @throws GuzzleException
     */
    public function getUpdate(): array
    {
        return $this->sendBotHttpRequest('GET', 'getupdate');
    }

    /**
     * @throws GuzzleException
     */
    public function sendMessage($message, $chatId, $keyBoard = [])
    {
        $body = [
            'msg' => $message,
            'chat' => $chatId,
        ];
        if ($keyBoard) {
            $body['keyboard'] = [$keyBoard];
        }
        return $this->sendBotHttpRequest('POST', 'send', $body);
    }
}
