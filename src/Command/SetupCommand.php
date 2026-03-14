<?php

namespace VISU\Command;

use VISU\Setup\ProjectSetup;

class SetupCommand extends Command
{
    protected ?string $descriptionShort = 'Initialize project structure for a new VISU project';

    protected string $description = 'Checks and creates all required directories, config files, and scaffolding for a VISU project. Safe to run repeatedly — existing files are never overwritten.';

    /**
     * @var array<string, array<string, mixed>>
     */
    protected $expectedArguments = [
        'non-interactive' => [
            'prefix'      => 'n',
            'longPrefix'  => 'non-interactive',
            'description' => 'Skip prompts and create all missing files automatically.',
            'noValue'     => true,
        ],
    ];

    public function execute(): void
    {
        $interactive = !$this->cli->arguments->get('non-interactive');

        $setup = new ProjectSetup(
            projectRoot: VISU_PATH_ROOT,
            interactive: $interactive,
            output: function (string $line): void {
                $this->cli->out($line);
            },
            confirm: function (string $question): bool {
                $input = $this->cli->input($question . ' [Y/n]');
                $answer = trim((string) $input->prompt());
                return $answer === '' || strtolower($answer) === 'y';
            },
        );

        $setup->run();
    }
}
