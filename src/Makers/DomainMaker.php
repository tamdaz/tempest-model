<?php

declare(strict_types=1);

namespace App\Makers;

use Tempest\Console\ConsoleArgument;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\Stubs\CommandStub;
use Tempest\Core\PublishesFiles;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Generation\Php\ClassManipulator;
use Tempest\Generation\Php\DataObjects\StubFile;

use function Tempest\root_path;
use function Tempest\Support\Path\to_relative_path;

final class DomainMaker
{
    use PublishesFiles;

    #[ConsoleCommand(
        name: 'make:domain',
        description: 'Creates a new domain with a controller and command')
    ]
    public function __invoke(#[ConsoleArgument(description: 'The domain\'s name to create.')] string $domainName): void
    {
        $targetPath = $this->promptDirectoryPath($domainName);

        $this->stubFileGenerator->generateClassFile(
            stubFile: StubFile::from(Stubs\ControllerStub::class),
            targetPath: $targetPath . '/Controllers/' . $domainName . 'Controller.php',
            manipulations: [
                static fn (ClassManipulator $class) => $class->removeClassAttribute(SkipDiscovery::class)
            ]
        );

        $this->stubFileGenerator->generateClassFile(
            stubFile: StubFile::from(CommandStub::class),
            targetPath: $targetPath . '/Commands/' . $domainName . 'Command.php',
            manipulations: [
                static fn (ClassManipulator $class) => $class->removeClassAttribute(SkipDiscovery::class)
            ]
        );

        $this->console->writeln();
        $this->console->success(sprintf('Domain successfully created at the %s directory.', $targetPath));
    }

    /**
     * Prompt the user for the directory path where the domain should be created.
     *
     * @param string $domainName The domain name.
     *
     * @return string The directory path.
     */
    private function promptDirectoryPath(string $domainName): string
    {
        $path = str_replace('.php', '', $this->getSuggestedPath($domainName, pathPrefix: 'Domains'));

        $answer = $this->console->ask(
            question: 'Where would you like to create the domain?',
            default: to_relative_path(root_path(), $path)
        );

        return is_string($answer) ? $answer : '';
    }
}
