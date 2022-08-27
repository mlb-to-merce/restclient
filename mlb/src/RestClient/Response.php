<?php

namespace RestClient;

class Response
{
    public array $headers = [];
    public int $status = 0;
    public string $body;

    /*
     * Function should check MIME and try to convert/adapt return type of response body.
     */
    public function getBody(): string|object
    {
        if(empty($this->body)) {
            return '';
        }

        $maybeJson = json_decode($this->body);

        if(json_last_error() === JSON_ERROR_NONE) {
            return $maybeJson;
        }
        return $this->body;
    }
}