<?php
namespace App\Payloads;

class ErrorPayload extends Payload
{
    protected $status = 400;

    public function __construct(string $message = null)
    {
        parent::__construct(array('message' => $message), $this->status);
    }
}
