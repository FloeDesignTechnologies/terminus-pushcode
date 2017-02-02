<?php

namespace Floe\Terminus\Commands;


use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareTrait;

class PushCodeCommand extends TerminusCommand
{
    use SiteAwareTrait;

    /**
     * @command push-code
     * @authorize
     */
    public function pushCode() {
        $this->output()->write("Hello World");
    }
}