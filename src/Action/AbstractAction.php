<?php

namespace Tuchsoft\MoodleChecklist\Action;

abstract class AbstractAction
{

    protected ?string $lastError = null;
    protected ?string $initError = null;


    public function __construct()
    {
        $this->initError = $this->lastError;
    }

    protected function checkForError() {
        if ($this->initError) {
            return null;
        }
        // Reset error for each parse operation.
        $this->lastError = null;
        $this->initError = null;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }


}