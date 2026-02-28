<?php

namespace App\Makers\Stubs;

use Tempest\Console\Console;
use Tempest\Console\ConsoleCommand;
use Tempest\Discovery\SkipDiscovery;

#[SkipDiscovery]
final readonly class CommandStub
{
    public function __construct(private Console $console) { }

    #[ConsoleCommand(name: "dummy-command-slug")]
    public function __invoke(): void
    {
        $this->console->success("Successfully generated the command!");
    }
}