<?php

namespace App\Domain\Entity;

class StackTrace
{
    private ?int $id = null;
    private ?Bug $bug = null;
    private ?DateTime $timestamp = null;
    private ?string $type = null;
    private ?string $message = null;
    private ?int $sequenceOrder = null;
    private ?string $url = null;
    private ?string $consoleLevel = null;
    private ?string $httpMethod = null;
    private ?int $httpStatus = null;
    private ?array $requestHeaders = null;
    private ?string $requestPayload = null;
    private ?string $responseBody = null;
    private ?array $responseHeaders = null;
    private ?string $errorName = null;
    private ?string $errorStack = null;
    private ?string $fileName = null;
    private ?int $lineNumber = null;
    private ?int $columnNumber = null;
    private ?string $userAgent = null;
    private ?array $browserInfo = null;
    private ?array $additionalData = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getBug(): ?Bug { return $this->bug; }
    public function setBug(Bug $bug): self { $this->bug = $bug; return $this; }

    public function getTimestamp(): ?DateTime { return $this->timestamp; }
    public function setTimestamp(DateTime $timestamp): self { $this->timestamp = $timestamp; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }

    public function getSequenceOrder(): ?int { return $this->sequenceOrder; }
    public function setSequenceOrder(int $sequenceOrder): self { $this->sequenceOrder = $sequenceOrder; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url; return $this; }

    public function getConsoleLevel(): ?string { return $this->consoleLevel; }
    public function setConsoleLevel(?string $consoleLevel): self { $this->consoleLevel = $consoleLevel; return $this; }

    public function getHttpMethod(): ?string { return $this->httpMethod; }
    public function setHttpMethod(?string $httpMethod): self { $this->httpMethod = $httpMethod; return $this; }

    public function getHttpStatus(): ?int { return $this->httpStatus; }
    public function setHttpStatus(?int $httpStatus): self { $this->httpStatus = $httpStatus; return $this; }

    public function getRequestHeaders(): ?array { return $this->requestHeaders; }
    public function setRequestHeaders(?array $requestHeaders): self { $this->requestHeaders = $requestHeaders; return $this; }

    public function getRequestPayload(): ?string { return $this->requestPayload; }
    public function setRequestPayload(?string $requestPayload): self { $this->requestPayload = $requestPayload; return $this; }

    public function getResponseBody(): ?string { return $this->responseBody; }
    public function setResponseBody(?string $responseBody): self { $this->responseBody = $responseBody; return $this; }

    public function getResponseHeaders(): ?array { return $this->responseHeaders; }
    public function setResponseHeaders(?array $responseHeaders): self { $this->responseHeaders = $responseHeaders; return $this; }

    public function getErrorName(): ?string { return $this->errorName; }
    public function setErrorName(?string $errorName): self { $this->errorName = $errorName; return $this; }

    public function getErrorStack(): ?string { return $this->errorStack; }
    public function setErrorStack(?string $errorStack): self { $this->errorStack = $errorStack; return $this; }

    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(?string $fileName): self { $this->fileName = $fileName; return $this; }

    public function getLineNumber(): ?int { return $this->lineNumber; }
    public function setLineNumber(?int $lineNumber): self { $this->lineNumber = $lineNumber; return $this; }

    public function getColumnNumber(): ?int { return $this->columnNumber; }
    public function setColumnNumber(?int $columnNumber): self { $this->columnNumber = $columnNumber; return $this; }

    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): self { $this->userAgent = $userAgent; return $this; }

    public function getBrowserInfo(): ?array { return $this->browserInfo; }
    public function setBrowserInfo(?array $browserInfo): self { $this->browserInfo = $browserInfo; return $this; }

    public function getAdditionalData(): ?array { return $this->additionalData; }
    public function setAdditionalData(?array $additionalData): self { $this->additionalData = $additionalData; return $this; }

    public function getCreatedAt(): ?DateTime { return $this->createdAt; }
    public function setCreatedAt(DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?DateTime $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    // Optional: lifecycle callbacks
    public function setCreatedAtIfNotSet(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new DateTime();
        }
    }

    public function updateTimestamp(): void
    {
        $this->updatedAt = new DateTime();
    }
}
