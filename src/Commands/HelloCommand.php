<?php

declare(strict_types=1);

namespace App\Commands;

use Tempest\Console\Console;
use Tempest\Console\ConsoleCommand;

final readonly class HelloCommand
{
    public function __construct(
        private Console $console,
    ) {
        // ...
    }

    #[ConsoleCommand(name: 'hello:world')]
    public function world(): void
    {
        $this->console->success('Hello, world!');
    }
}
