<?php

namespace app\common\model;

use AliyunMNS\Client;
use AliyunMNS\Requests\SendMessageRequest;
use AliyunMNS\Requests\BatchSendMessageRequest;
use AliyunMNS\Model\SendMessageRequestItem;
use AliyunMNS\Exception\MnsException;

class Mns extends Base
{

    private $accessId;
    private $accessKey;
    private $endPoint;
    private $client;

    public function __construct($accessId = null, $accessKey = null, $endPoint = null)
    {
        $this->accessId = $accessId ?? config('MNS_ACCESS_ID');
        $this->accessKey = $accessKey ?? config('MNS_ACCESS_KEY');
        $this->endPoint = $endPoint ?? config('MNS_END_POINT');
        $this->client = new Client($this->endPoint, $this->accessId, $this->accessKey);
    }

    public function sendMessage($message_body, $queue_name = 'yk-empty-number-api-insert')
    {
        $queue = $this->client->getQueueRef($queue_name);
        $request = new SendMessageRequest($message_body);
        try
        {
            $res = $queue->sendMessage($request);
            //echo "MessageSent! \n";
            return true;
        }
        catch (MnsException $e)
        {
            //echo "SendMessage Failed: " . $e;
            return false;
        }
    }

    public function batchSendMessage($messageItems, $queue_name = 'yk-empty-number-api-insert')
    {
        $items  = [];
        foreach ($messageItems as $item){
            is_string($item) || $item = json_encode($item);
            $items[] = new SendMessageRequestItem($item);
        }

        $queue = $this->client->getQueueRef($queue_name);
        $request = new BatchSendMessageRequest($items);
        try
        {
            $res = $queue->batchSendMessage($request);
            //echo "MessageSent! \n";
            return true;
        }
        catch (MnsException $e)
        {
            //echo "SendMessage Failed: " . $e;
            return false;
        }
    }
}