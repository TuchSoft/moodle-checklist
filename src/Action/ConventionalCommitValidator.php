<?php

namespace Tuchsoft\MoodleChecklist\Action;

use Exception;
use Ramsey\ConventionalCommits\Configuration\Configuration;
use Ramsey\ConventionalCommits\Configuration\DefaultConfiguration;
use Ramsey\ConventionalCommits\Parser;

class ConventionalCommitValidator extends AbstractAction {

    private Configuration $configuration;
    private Parser $parser;

    public function __construct() {
        parent::__construct();
        $this->configuration = new DefaultConfiguration([
            'typeCase' => 'snake'
        ]);
        $this->parser = new Parser($this->configuration);
    }

    public function validate($msg): bool {
        try {
            $this->parser->parse($msg);
            return true;
        } catch (Exception $e) {
            $this->lastError = "{$e->getMessage()}: ($msg)";
            return false;
        }
    }
}