<?php

declare(strict_types=1);

namespace App\Http;

class Response
{
    public function __construct(
        private mixed $content = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self($data, $status, array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
        ], $headers));
    }

    public static function make(mixed $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    public function status(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function withHeaders(array $headers): self
    {
        foreach ($headers as $key => $value) {
            $this->headers[$key] = $value;
        }

        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        if (is_array($this->content) || is_object($this->content)) {
            if (! isset($this->headers['Content-Type'])) {
                header('Content-Type: application/json; charset=utf-8');
            }

            echo json_encode($this->content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        echo (string) $this->content;
    }
}
