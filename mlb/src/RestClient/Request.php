<?php

namespace RestClient;

use RestClient\Response;

class Request
{
    public const METHODS = [
        'HEAD' => 'HEAD',
        'GET' => 'GET',
        'POST' => 'POST',
        // and others
    ];
    public const AUTH_METHODS = [
        'Basic' => 'basic',
        'JWT' => 'JWT',
        // and others
    ];
    protected $curlRef;
    protected string $url, $method, $user, $password, $authMethod;
    protected array $headers = [], $fields = [];

    public function __construct(string $url)
    {
        $this->setUrl($url);
        $this->authMethod = static::AUTH_METHODS['Basic'];
        $this->method = static::METHODS['GET'];
    }

    public function setUrl(string $url): self
    {
        $url = filter_var(trim($url), FILTER_VALIDATE_URL);
        if ($url === false) {
            throw new \Exception('Empty or invalid URL');
        }
        $this->url = $url;
        return $this;
    }

    public function authBasic(string $user, string $password): self
    {
        $this->user = $user;
        $this->password = $password;
        $this->authMethod = static::AUTH_METHODS['Basic'];
        return $this;
    }

    public function authByToken(string $token): self
    {
        $this->user = $token;
        $this->authMethod = static::AUTH_METHODS['JWT'];
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    public function setMethod(string $method): self
    {
        $method = strtoupper($method);
        if (!isset(static::METHODS[$method])) {
            throw new \Exception('Method not supported');
        }
        $this->method = $method;
        return $this;
    }

    public function send(): ?Response
    {
        $resp = new Response();

        try {
            if (!isset($this->curlRef)) {
                $this->curlRef = curl_init($this->url);
            }
            $this->prepareReq();

            $resp->body = curl_exec($this->curlRef);
            if (curl_errno($this->curlRef) > 0) {
                $resp->body = curl_error($this->curlRef);
                return $resp;
            }

            if ($resp->body === false) {
                return null;
            }
            $resp->status = curl_getinfo($this->curlRef, CURLINFO_HTTP_CODE);

        } catch (\Exception) {
            return null;
        }

        return $resp;
    }

    protected function prepareReq(): void
    {
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $this->url,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HEADER => false,
        ];
        switch ($this->method) {
            case static::METHODS['HEAD']:
                $opts[CURLOPT_CUSTOMREQUEST] = $this->method;
                $opts[CURLOPT_NOBODY] = true;
                break;
            case static::METHODS['POST']:
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_NOBODY] = false;
                break;
            default:
                $opts[CURLOPT_HTTPGET] = true;
                $opts[CURLOPT_NOBODY] = false;
        }
        switch ($this->authMethod) {
            case static::AUTH_METHODS['Basic']:
                if (!empty($this->user) && !empty($this->password)) {
                    $opts[CURLOPT_USERPWD] = $this->user . ':' . $this->password;
                }
                break;
            case static::AUTH_METHODS['JWT']:
                if (!empty($this->user)) {
                    $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BEARER;
                    $opts[CURLOPT_XOAUTH2_BEARER] = $this->user;
                }
                break;
        }

        if (!empty($this->headers)) {
            $headers = [];
            foreach ($this->headers as $header => $value) {
                $headers[] = $header . ': ' . $value;
            }
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_reset($this->curlRef);
        curl_setopt_array($this->curlRef, $opts);
    }

    public function __destruct()
    {
        if (isset($this->curlRef)) {
            curl_close($this->curlRef);
        }
    }
}