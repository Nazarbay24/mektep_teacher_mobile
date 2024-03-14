<?php
namespace App\Payloads;

class UnauthorizedPayload extends Payload
{
    protected $status = 401;
    protected $data = ['message' => 'unauthorized attempt.'];
}
