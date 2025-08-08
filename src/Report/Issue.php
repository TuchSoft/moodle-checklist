<?php

namespace Tuchsoft\MoodleChecklist\Report;

use Exception;
use phpDocumentor\Reflection\Types\Scalar;

class Issue implements \JsonSerializable
{
    /**
     * @var string The issue code.
     */
    private string $code;

    /**
     * @var int One of AbstractCheck::SEVERITY_* constants.
     */
    private int $severity;

    /**
     * @var string The issue message.
     */
    private string $message;

    /**
     * @var string The file path where the issue was found (relative to plugin root).
     */
    private string $path;

    /**
     * @var int The line number where the issue was found.
     */
    private int $line;

    /**
     * @var string An optional reference for the issue.
     */
    private string $ref;

    /**
     * @var string An optional help message for the issue.
     */
    private string $help;

    /**
     * @var array Additional data for the message.
     */
    private array $messageData = [];

    /**
     * @var bool Flag indicating if the issue has been reported.
     */
    private bool $reported = false;

    /**
     * Issue constructor.
     *
     * @param string $code The issue code.
     * @param int $severity One of AbstractCheck::SEVERITY_* constants.
     * @param string $message The issue message.
     * @param string|null $path The file path where the issue was found (relative to plugin root).
     * @param int|null $line The line number where the issue was found.
     */
    public function __construct(string $code, int $severity, string $message, ?string $path = null, ?int $line = 1)
    {
        $this->code = $code;
        $this->severity = $severity;
        $this->message = $message;
        $this->path = $path ?? '.';
        $this->line = $line ?? 1;
        $this->ref = ''; // Initialize optional properties
        $this->help = ''; // Initialize optional properties
    }

    /**
     * Creates an Issue object from a parsed array.
     *
     * This method iterates through the provided array and assigns values
     * to properties only if the property exists in the class.
     *
     * @param array $data The associative array to create the object from.
     * @return Issue
     * @throws Exception If a required key is missing from the array.
     */
    public static function fromJson(array $data): Issue
    {
        // First, check for required keys to ensure the object can be created.
        $requiredKeys = ['code', 'severity', 'message', 'path', 'line'];
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                throw new Exception("Missing required key: '$key' in array data.");
            }
        }

        // Use the constructor for initial creation with required properties.
        $issue = new self(
            $data['code'],
            $data['severity'],
            $data['message'],
            $data['path'],
            $data['line']
        );

        // Now, loop through the remaining data and assign to existing properties.
        foreach ($data as $key => $value) {
            if (property_exists($issue, $key)) {
                // Use a dedicated setter if one exists, otherwise set directly.
                $setterMethod = 'set' . ucfirst($key);
                if (method_exists($issue, $setterMethod)) {
                    // Call the setter to use its validation logic.
                    $issue->$setterMethod($value);
                } else {
                    // Direct assignment for properties without a specific setter.
                    // Note: This bypasses encapsulation and is less safe.
                    $issue->$key = $value;
                }
            }
        }

        return $issue;
    }

    /**
     * Throws an exception if the issue has already been reported.
     *
     * @throws Exception If the issue has already been reported.
     */
    private function ensureNotReported(): void
    {
        if ($this->reported) {
            throw new Exception('The issue has already been reported and cannot be modified.');
        }
    }

    /**
     * Get the issue code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Set the issue code.
     *
     * @param string $code
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function setCode(string $code): self
    {
        $this->ensureNotReported();
        $this->code = $code;
        return $this;
    }

    /**
     * Get the issue severity.
     *
     * @return int
     */
    public function getSeverity(): int
    {
        return $this->severity;
    }

    /**
     * Set the issue severity.
     *
     * @param int $severity
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function setSeverity(int $severity): self
    {
        $this->ensureNotReported();
        $this->severity = $severity;
        return $this;
    }

    /**
     * Get the issue message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set the issue message.
     *
     * @param string $message
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function setMessage(string $message): self
    {
        $this->ensureNotReported();
        $this->message = $message;
        return $this;
    }

    /**
     * Get the file path where the issue was found.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Set the file path where the issue was found.
     *
     * @param string $path
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function setPath(string $path): self
    {
        $this->ensureNotReported();
        $this->path = $path;
        return $this;
    }

    /**
     * Get the line number where the issue was found.
     *
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Set the line number where the issue was found.
     *
     * @param int $line
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function setLine(int $line): self
    {
        $this->ensureNotReported();
        $this->line = $line;
        return $this;
    }

    /**
     * Get the issue reference.
     *
     * @return string
     */
    public function getRef(): string
    {
        return $this->ref;
    }

    /**
     * Set the issue reference.
     *
     * @param string $ref
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function setRef(string $ref): self
    {
        $this->ensureNotReported();
        $this->ref = $ref;
        return $this;
    }

    /**
     * Get the issue help message.
     *
     * @return string
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * Set the issue help message.
     *
     * @param string $help
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function setHelp(string $help): self
    {
        $this->ensureNotReported();
        $this->help = $help;
        return $this;
    }

    /**
     * Get the message data.
     *
     * @return array
     */
    public function getMessageData(): array
    {
        return $this->messageData;
    }

    /**
     * Set the message data.
     *
     * @param array $messageData
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function setMessageData(array $messageData): self
    {
        $this->ensureNotReported();
        $this->messageData = $messageData;
        return $this;
    }

    /**
     * Add data to the message data array.
     *
     * @param string $key
     * @param scalar $value
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function addMessageData(string $key, scalar $value): self
    {
        $this->ensureNotReported();
        $this->messageData[$key] = $value;
        return $this;
    }

    /**
     * Check if the issue has been reported.
     *
     * @return bool
     */
    public function isReported(): bool
    {
        return $this->reported;
    }

    /**
     * Set the reported status to true.
     * This method can only be called once.
     *
     * @return self
     */
    public function setReported(): self
    {
        // No check here, as this is the method that *sets* the reported status.
        // Once true, no other setters can be called.
        $this->reported = true;
        return $this;
    }

    /**
     * Add a prefix to the issue code.
     *
     * @param string $codePrefix
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function addCode(string $codePrefix): self
    {
        $this->ensureNotReported();
        $this->code = $this->code ? "$codePrefix.$this->code" : $codePrefix;
        return $this;
    }


    /**
     * Add data to the message
     *
     * @param string $key The key of the data to be replaced in the message
     * @param string $value The value of the data to be replaced in the message
     * @return self
     * @throws Exception If the issue has already been reported.
     */
    public function addMessage(string $key, string $value): self
    {
        $this->ensureNotReported();
        $this->messageData[$key] = $value;
        return $this;
    }



    /**
     * Specify data which should be serialized to JSON.
     * @return array
     */
    public function jsonSerialize(): array
    {
        // Return an array of all properties that should be serialized.
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
            'path' => $this->path,
            'line' => $this->line,
            'ref' => $this->ref,
            'help' => $this->help,
            'messageData' => $this->messageData,
            'reported' => $this->reported,
        ];
    }
}